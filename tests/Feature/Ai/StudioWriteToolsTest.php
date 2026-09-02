<?php

namespace Tests\Feature\Ai;

use App\Domain\Ai\StudioAgentContext;
use App\Domain\Ai\StudioTools\AddBlockTool;
use App\Domain\Ai\StudioTools\AddPageTool;
use App\Domain\Ai\StudioTools\AddSectionTool;
use App\Domain\Ai\StudioTools\RemoveBlockTool;
use App\Domain\Ai\StudioTools\UpdateBlockTool;
use App\Models\DataIngestion\Dataset;
use App\Models\DataIngestion\DatasetColumn;
use App\Models\DataIngestion\DataSource;
use App\Models\User\User;
use Database\Factories\StudioContentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudioWriteToolsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function context(string $type = 'statsdata', array $attrs = []): StudioAgentContext
    {
        $content = StudioContentFactory::new()->create(['user_id' => $this->user->id, 'type' => $type, ...$attrs]);

        return new StudioAgentContext($this->user, $content->fresh());
    }

    private function dataset(string $name = 'Pop'): Dataset
    {
        $source = DataSource::create([
            'user_id' => $this->user->id, 'name' => $name, 'type' => 'csv', 'source_kind' => 'upload',
            'materialization' => 'snapshot', 'status' => 'ready', 'original_filename' => 'x.csv',
            'raw_storage_path' => 'data-sources/x.csv', 'file_size_bytes' => 1,
        ]);
        $dataset = Dataset::create([
            'data_source_id' => $source->id, 'user_id' => $this->user->id, 'name' => $name,
            'row_count' => 10, 'status' => 'ready',
        ]);
        DatasetColumn::create(['dataset_id' => $dataset->id, 'name' => 'region', 'type' => 'string', 'column_order' => 0]);
        DatasetColumn::create(['dataset_id' => $dataset->id, 'name' => 'population', 'type' => 'integer', 'column_order' => 1]);

        return $dataset;
    }

    public function test_add_page_rejects_a_non_simple_param_name(): void
    {
        $ctx = $this->context();

        $this->assertArrayHasKey('error', app(AddPageTool::class)->execute(
            ['ref' => 'p1', 'title' => 'Par région', 'params_json' => '[{"name":"nom ville"}]'],
            $ctx,
        ));
    }

    public function test_add_page_declares_params(): void
    {
        $dataset = $this->dataset();
        $ctx = $this->context('statsdata');

        $ok = app(AddPageTool::class)->execute([
            'ref' => 'p1',
            'title' => 'Détail par région',
            'params_json' => '[{"name":"region","dataset_id":'.$dataset->id.',"column":"region","default_value":"Bretagne","fan_out":true}]',
        ], $ctx);

        $this->assertTrue($ok['ok']);
        $op = $ctx->patchOps()[0];
        $this->assertSame('addPage', $op['op']);
        $this->assertArrayNotHasKey('isTemplate', $op);
        $this->assertSame([[
            'name' => 'region',
            'datasetId' => (string) $dataset->id,
            'column' => 'region',
            'slugColumn' => 'region',
            'defaultValue' => 'Bretagne',
            'fanOut' => true,
        ]], $op['params']);
    }

    public function test_add_page_rejects_an_unknown_param_column(): void
    {
        $dataset = $this->dataset();
        $ctx = $this->context('statsdata');

        $res = app(AddPageTool::class)->execute([
            'ref' => 'p1', 'title' => 'X',
            'params_json' => '[{"name":"ville","dataset_id":'.$dataset->id.',"column":"ville"}]',
        ], $ctx);

        $this->assertArrayHasKey('error', $res);
        $this->assertStringContainsString('ville', $res['error']);
    }

    public function test_add_and_configure_a_param_block(): void
    {
        $dataset = $this->dataset();
        $ctx = $this->context('statsdata');
        app(AddPageTool::class)->execute(['ref' => 'p1', 'title' => 'X'], $ctx);
        (new AddSectionTool)->execute(['ref' => 's1', 'page_ref' => 'p1', 'layout' => '1-col'], $ctx);

        $res = app(AddBlockTool::class)->execute([
            'ref' => 'pm', 'section_ref' => 's1', 'col' => 0, 'type' => 'param',
            'dataset_id' => $dataset->id,
            'field_mapping_json' => '{"paramColumn":"region","paramName":"region"}',
        ], $ctx);

        $this->assertTrue($res['ok']);
        $op = collect($ctx->patchOps())->firstWhere('type', 'param');
        $this->assertSame(['paramColumn' => 'region', 'paramName' => 'region'], $op['fieldMapping']);

        // Colonne inconnue → refusée.
        $bad = app(AddBlockTool::class)->execute([
            'ref' => 'pm2', 'section_ref' => 's1', 'col' => 0, 'type' => 'param',
            'dataset_id' => $dataset->id,
            'field_mapping_json' => '{"paramColumn":"ville"}',
        ], $ctx);
        $this->assertArrayHasKey('error', $bad);
    }

    public function test_add_block_into_an_if_container(): void
    {
        $ctx = $this->context('statsdata');
        app(AddPageTool::class)->execute(['ref' => 'p1', 'title' => 'X'], $ctx);
        (new AddSectionTool)->execute(['ref' => 's1', 'page_ref' => 'p1', 'layout' => '1-col'], $ctx);
        $tool = app(AddBlockTool::class);

        $if = $tool->execute([
            'ref' => 'if1', 'section_ref' => 's1', 'col' => 0, 'type' => 'if',
            'config_json' => '{"ifParam":"carburant","ifOperator":"=","ifValue":"gazole"}',
        ], $ctx);
        $this->assertTrue($if['ok']);

        $child = $tool->execute(['ref' => 'p1b', 'loop_ref' => 'if1', 'type' => 'paragraph'], $ctx);
        $this->assertTrue($child['ok']);
        $this->assertSame('if1', collect($ctx->patchOps())->last()['loopRef']);

        // param interdit dans un conteneur.
        $bad = $tool->execute(['ref' => 'pmx', 'loop_ref' => 'if1', 'type' => 'param', 'dataset_id' => 1], $ctx);
        $this->assertArrayHasKey('error', $bad);
    }

    public function test_add_section_needs_a_known_page(): void
    {
        $ctx = $this->context();
        $tool = new AddSectionTool;

        $this->assertArrayHasKey('error', $tool->execute(['ref' => 's1', 'page_ref' => 'ghost', 'layout' => '1-col'], $ctx));

        (app(AddPageTool::class))->execute(['ref' => 'p1', 'title' => 'X'], $ctx);
        $this->assertTrue($tool->execute(['ref' => 's1', 'page_ref' => 'p1', 'layout' => '2-cols'], $ctx)['ok']);
    }

    public function test_add_block_rejects_disallowed_type_for_content_type(): void
    {
        $ctx = $this->context('statsdata');
        (app(AddPageTool::class))->execute(['ref' => 'p1', 'title' => 'X'], $ctx);
        (new AddSectionTool)->execute(['ref' => 's1', 'page_ref' => 'p1', 'layout' => '1-col'], $ctx);

        $tool = app(AddBlockTool::class);
        $res = $tool->execute(['ref' => 'b1', 'section_ref' => 's1', 'col' => 0, 'type' => 'rating'], $ctx);

        $this->assertArrayHasKey('error', $res);
    }

    public function test_add_block_into_a_loop_via_loop_ref(): void
    {
        $dataset = $this->dataset();
        $ctx = $this->context('statsdata');
        (app(AddPageTool::class))->execute(['ref' => 'p1', 'title' => 'X'], $ctx);
        (new AddSectionTool)->execute(['ref' => 's1', 'page_ref' => 'p1', 'layout' => '1-col'], $ctx);
        $tool = app(AddBlockTool::class);

        $loop = $tool->execute([
            'ref' => 'lp', 'section_ref' => 's1', 'col' => 0, 'type' => 'loop',
            'dataset_id' => $dataset->id,
            'field_mapping_json' => '{"loopColumn":"region","loopVar":"item"}',
        ], $ctx);
        $this->assertTrue($loop['ok']);

        // Un bloc de données dans la boucle : loop_ref au lieu de section_ref/col.
        $kpi = $tool->execute([
            'ref' => 'k1', 'loop_ref' => 'lp', 'type' => 'kpi',
            'dataset_id' => $dataset->id,
            'field_mapping_json' => '{"valueColumn":"population","aggregate":"avg"}',
            'filters_json' => '[{"column":"region","operator":"=","value":"{{item}}"}]',
        ], $ctx);
        $this->assertTrue($kpi['ok']);

        $op = collect($ctx->patchOps())->last();
        $this->assertSame('addBlock', $op['op']);
        $this->assertSame('lp', $op['loopRef']);
        $this->assertArrayNotHasKey('sectionRef', $op);

        // Une boucle imbriquée est autorisée (script dans script).
        $nested = $tool->execute([
            'ref' => 'lp2', 'loop_ref' => 'lp', 'type' => 'loop', 'dataset_id' => $dataset->id,
            'field_mapping_json' => '{"loopColumn":"region","loopVar":"sub"}',
        ], $ctx);
        $this->assertTrue($nested['ok']);

        // Un bloc de formulaire est refusé dans une boucle.
        $form = $tool->execute(['ref' => 'f1', 'loop_ref' => 'lp', 'type' => 'rating'], $ctx);
        $this->assertArrayHasKey('error', $form);

        // loop_ref qui ne pointe pas sur une boucle → refusé.
        $bad = $tool->execute(['ref' => 'x1', 'loop_ref' => 'k1', 'type' => 'paragraph'], $ctx);
        $this->assertArrayHasKey('error', $bad);
    }

    public function test_add_block_validates_columns_against_the_dataset(): void
    {
        $dataset = $this->dataset();
        $ctx = $this->context('statsdata');
        (app(AddPageTool::class))->execute(['ref' => 'p1', 'title' => 'X'], $ctx);
        (new AddSectionTool)->execute(['ref' => 's1', 'page_ref' => 'p1', 'layout' => '1-col'], $ctx);
        $tool = app(AddBlockTool::class);

        $bad = $tool->execute([
            'ref' => 'b1', 'section_ref' => 's1', 'col' => 0, 'type' => 'bar',
            'dataset_id' => $dataset->id,
            'field_mapping_json' => '{"xAxis":"departement","yAxes":["population"]}',
        ], $ctx);
        $this->assertArrayHasKey('error', $bad);
        $this->assertStringContainsString('departement', $bad['error']);

        $ok = $tool->execute([
            'ref' => 'b1', 'section_ref' => 's1', 'col' => 0, 'type' => 'bar',
            'dataset_id' => $dataset->id,
            'field_mapping_json' => '{"xAxis":"region","yAxes":["population"],"aggregate":"sum"}',
        ], $ctx);
        $this->assertTrue($ok['ok']);

        $op = collect($ctx->patchOps())->firstWhere('op', 'addBlock');
        $this->assertSame('bar', $op['type']);
        $this->assertSame($dataset->id, $op['datasetId']);
        $this->assertSame(['xAxis' => 'region', 'yAxes' => ['population'], 'aggregate' => 'sum'], $op['fieldMapping']);
    }

    public function test_add_block_accepts_calc_column_refs_in_the_mapping(): void
    {
        $dataset = $this->dataset();
        $ctx = $this->context('statsdata');
        (app(AddPageTool::class))->execute(['ref' => 'p1', 'title' => 'X'], $ctx);
        (new AddSectionTool)->execute(['ref' => 's1', 'page_ref' => 'p1', 'layout' => '1-col'], $ctx);

        $fm = '{"xAxis":"region","yAxes":["calc:t"],"aggregate":"avg",'
            .'"calcColumns":[{"id":"t","label":"Total","operands":[{"column":"population"},{"op":"*","value":2}]}]}';

        $ok = (app(AddBlockTool::class))->execute([
            'ref' => 'b1', 'section_ref' => 's1', 'col' => 0, 'type' => 'bar',
            'dataset_id' => $dataset->id,
            'field_mapping_json' => $fm,
        ], $ctx);

        $this->assertTrue($ok['ok'], json_encode($ok));
        $op = collect($ctx->patchOps())->firstWhere('op', 'addBlock');
        $this->assertSame(['calc:t'], $op['fieldMapping']['yAxes']);
    }

    public function test_add_block_requires_dataset_for_data_blocks(): void
    {
        $ctx = $this->context('statsdata');
        (app(AddPageTool::class))->execute(['ref' => 'p1', 'title' => 'X'], $ctx);
        (new AddSectionTool)->execute(['ref' => 's1', 'page_ref' => 'p1', 'layout' => '1-col'], $ctx);

        $res = app(AddBlockTool::class)->execute(['ref' => 'b1', 'section_ref' => 's1', 'col' => 0, 'type' => 'kpi'], $ctx);
        $this->assertArrayHasKey('error', $res);
    }

    public function test_locked_search_block_is_configurable_but_not_removable(): void
    {
        $dataset = $this->dataset();
        $content = StudioContentFactory::new()->create([
            'user_id' => $this->user->id, 'type' => 'statsdata',
            'pages' => [['id' => 'tpl', 'title' => 'Par région', 'isTemplate' => true, 'paramName' => 'region']],
            'sections' => [['id' => 'sec1', 'layout' => '1-col', 'pageId' => 'tpl']],
            'blocks' => [['id' => 'blk1', 'type' => 'search', 'zoneId' => 'sec1-0', 'locked' => true, 'config' => [], 'fieldMapping' => ['targetPageId' => 'tpl']]],
        ]);
        $ctx = new StudioAgentContext($this->user, $content->fresh());

        // update_block : autorisé sur le bloc search verrouillé.
        $upd = app(UpdateBlockTool::class)->execute([
            'block_ref' => 'blk1',
            'field_mapping_json' => '{"searchSources":[{"datasetId":"'.$dataset->id.'","columns":["region"]}],"resultTitleColumn":"region"}',
        ], $ctx);
        $this->assertTrue($upd['ok']);
        $op = collect($ctx->patchOps())->firstWhere('op', 'updateBlock');
        $this->assertSame('region', $op['fieldMapping']['searchSources'][0]['columns'][0]);

        // remove_block : toujours refusé.
        $this->assertArrayHasKey('error', (new RemoveBlockTool)->execute(['block_ref' => 'blk1'], $ctx));
    }

    public function test_update_block_rejects_unknown_search_column(): void
    {
        $dataset = $this->dataset();
        $content = StudioContentFactory::new()->create([
            'user_id' => $this->user->id, 'type' => 'statsdata',
            'pages' => [['id' => 'tpl', 'title' => 'X', 'isTemplate' => true, 'paramName' => 'region']],
            'sections' => [['id' => 'sec1', 'layout' => '1-col', 'pageId' => 'tpl']],
            'blocks' => [['id' => 'blk1', 'type' => 'search', 'zoneId' => 'sec1-0', 'locked' => true, 'config' => []]],
        ]);
        $ctx = new StudioAgentContext($this->user, $content->fresh());

        $res = app(UpdateBlockTool::class)->execute([
            'block_ref' => 'blk1',
            'field_mapping_json' => '{"searchSources":[{"datasetId":"'.$dataset->id.'","columns":["ville"]}]}',
        ], $ctx);
        $this->assertArrayHasKey('error', $res);
    }

    public function test_add_block_col_must_fit_the_layout(): void
    {
        $ctx = $this->context('statsdata');
        (app(AddPageTool::class))->execute(['ref' => 'p1', 'title' => 'X'], $ctx);
        (new AddSectionTool)->execute(['ref' => 's1', 'page_ref' => 'p1', 'layout' => '1-col'], $ctx);

        $res = app(AddBlockTool::class)->execute(['ref' => 'b1', 'section_ref' => 's1', 'col' => 2, 'type' => 'paragraph'], $ctx);
        $this->assertArrayHasKey('error', $res);
    }
}
