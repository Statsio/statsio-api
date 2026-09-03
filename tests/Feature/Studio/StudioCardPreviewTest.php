<?php

namespace Tests\Feature\Studio;

use App\Models\DataIngestion\Dataset;
use App\Models\DataIngestion\DatasetColumn;
use App\Models\DataIngestion\DatasetVersion;
use App\Models\DataIngestion\DataSource;
use App\Models\User\User;
use Database\Factories\StudioContentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudioCardPreviewTest extends TestCase
{
    use RefreshDatabase;

    private string $diskRoot;

    protected function setUp(): void
    {
        parent::setUp();

        // Disque de datasets isolé sur un dossier temporaire (évite Storage::fake('local')
        // qui bute sur les perms du dossier partagé en environnement conteneurisé).
        $this->diskRoot = sys_get_temp_dir().'/statsio-card-preview-'.uniqid();
        File::ensureDirectoryExists($this->diskRoot);
        config([
            'filesystems.disks.card_preview_test' => ['driver' => 'local', 'root' => $this->diskRoot],
            'statsio.data_ingestion.datasets_disk' => 'card_preview_test',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->diskRoot);
        parent::tearDown();
    }

    /**
     * @param  list<string>  $schema
     * @param  list<array<int, mixed>>  $rows
     */
    private function mockDataset(User $user, array $schema, array $rows): Dataset
    {
        $dataSource = DataSource::create([
            'user_id' => $user->id,
            'name' => 'Source test',
            'type' => 'csv',
            'source_kind' => 'upload',
            'materialization' => 'snapshot',
            'original_filename' => 'test.csv',
            'raw_storage_path' => 'data-sources/'.uniqid().'.csv',
            'file_size_bytes' => 100,
            'status' => 'ready',
        ]);

        $dataset = Dataset::create([
            'data_source_id' => $dataSource->id,
            'user_id' => $user->id,
            'name' => 'Dataset test',
            'row_count' => count($rows),
            'status' => 'ready',
        ]);

        foreach ($schema as $i => $col) {
            DatasetColumn::create([
                'dataset_id' => $dataset->id,
                'name' => $col,
                'type' => 'string',
                'column_order' => $i,
            ]);
        }

        $path = "datasets/{$dataset->id}/v1.parquet";
        Storage::disk('card_preview_test')->put($path, json_encode(['__mock__' => true, 'schema' => $schema, 'data' => $rows]));

        DatasetVersion::create([
            'dataset_id' => $dataset->id,
            'version_number' => 1,
            'parquet_storage_path' => $path,
            'file_size_bytes' => 100,
            'row_count' => count($rows),
        ]);

        return $dataset->fresh(['columns', 'versions', 'latestVersion', 'dataSource']);
    }

    public function test_returns_empty_when_no_chart_block(): void
    {
        $user = User::factory()->create();
        $content = StudioContentFactory::new()->published()->create([
            'user_id' => $user->id,
            'blocks' => [['id' => 'b1', 'type' => 'paragraph', 'config' => ['content' => 'hello']]],
        ]);

        $this->getJson("/api/studio/content/public/{$content->slug}/card-preview")
            ->assertOk()
            ->assertJsonPath('data.empty', true)
            ->assertJsonPath('data.series', []);
    }

    public function test_shapes_first_line_chart_from_real_rows(): void
    {
        $user = User::factory()->create();
        $dataset = $this->mockDataset($user, ['annee', 'prix'], [
            ['2019', '1.5'],
            ['2020', '1.6'],
            ['2021', '1.7'],
        ]);
        $content = StudioContentFactory::new()->published()->create([
            'user_id' => $user->id,
            'blocks' => [[
                'id' => 'chart-1',
                'type' => 'line',
                'datasetId' => (string) $dataset->id,
                'fieldMapping' => ['xAxis' => 'annee', 'yAxis' => 'prix'],
                'config' => ['title' => 'Prix', 'suffix' => ' €'],
            ]],
        ]);

        $res = $this->getJson("/api/studio/content/public/{$content->slug}/card-preview")->assertOk();

        $res->assertJsonPath('data.kind', 'line')
            ->assertJsonPath('data.empty', false)
            ->assertJsonPath('data.block_id', 'chart-1')
            ->assertJsonPath('data.title', 'Prix')
            ->assertJsonPath('data.unit', ' €')
            ->assertJsonPath('data.labels', ['2019', '2020', '2021']);
        $this->assertEqualsWithDelta([1.5, 1.6, 1.7], $res->json('data.series.0.values'), 0.001);
    }

    public function test_aggregates_bar_chart_with_group_by(): void
    {
        $user = User::factory()->create();
        $dataset = $this->mockDataset($user, ['region', 'ventes'], [
            ['Nord', '10'],
            ['Nord', '5'],
            ['Sud', '7'],
        ]);
        $content = StudioContentFactory::new()->published()->create([
            'user_id' => $user->id,
            'blocks' => [[
                'id' => 'bar-1',
                'type' => 'bar',
                'datasetId' => (string) $dataset->id,
                'fieldMapping' => [
                    'xAxis' => 'region',
                    'yAxis' => 'ventes',
                    'aggregates' => [['column' => 'ventes', 'fn' => 'sum']],
                ],
                'config' => [],
            ]],
        ]);

        $res = $this->getJson("/api/studio/content/public/{$content->slug}/card-preview")
            ->assertOk()
            ->assertJsonPath('data.kind', 'bar')
            ->assertJsonPath('data.orientation', 'vertical')
            ->assertJsonPath('data.labels', ['Nord', 'Sud']);
        $this->assertEqualsWithDelta([15.0, 7.0], $res->json('data.series.0.values'), 0.001);
    }

    public function test_pie_keeps_top_five_plus_autres(): void
    {
        $user = User::factory()->create();
        $rows = [];
        foreach (['a' => 60, 'b' => 30, 'c' => 20, 'd' => 10, 'e' => 5, 'f' => 3, 'g' => 2] as $cat => $n) {
            $rows[] = [$cat, (string) $n];
        }
        $dataset = $this->mockDataset($user, ['cat', 'n'], $rows);
        $content = StudioContentFactory::new()->published()->create([
            'user_id' => $user->id,
            'blocks' => [[
                'id' => 'pie-1',
                'type' => 'pie',
                'datasetId' => (string) $dataset->id,
                'fieldMapping' => ['label' => 'cat', 'value' => 'n'],
                'config' => [],
            ]],
        ]);

        $res = $this->getJson("/api/studio/content/public/{$content->slug}/card-preview")->assertOk();

        $labels = $res->json('data.labels');
        $this->assertCount(6, $labels);
        $this->assertSame('Autres', $labels[5]);
        // 'Autres' = f + g = 5
        $this->assertEqualsWithDelta(5.0, $res->json('data.series.0.values.5'), 0.001);
    }

    public function test_card_block_id_override_selects_a_specific_block(): void
    {
        $user = User::factory()->create();
        $dataset = $this->mockDataset($user, ['annee', 'prix'], [['2019', '1'], ['2020', '2']]);
        $content = StudioContentFactory::new()->published()->create([
            'user_id' => $user->id,
            'card_block_id' => 'chart-2',
            'blocks' => [
                [
                    'id' => 'chart-1',
                    'type' => 'line',
                    'datasetId' => (string) $dataset->id,
                    'fieldMapping' => ['xAxis' => 'annee', 'yAxis' => 'prix'],
                    'config' => [],
                ],
                [
                    'id' => 'chart-2',
                    'type' => 'bar',
                    'datasetId' => (string) $dataset->id,
                    'fieldMapping' => ['xAxis' => 'annee', 'yAxis' => 'prix'],
                    'config' => [],
                ],
            ],
        ]);

        $this->getJson("/api/studio/content/public/{$content->slug}/card-preview")
            ->assertOk()
            ->assertJsonPath('data.block_id', 'chart-2')
            ->assertJsonPath('data.kind', 'bar');
    }

    public function test_invalid_card_block_id_falls_back_to_first_chart(): void
    {
        $user = User::factory()->create();
        $dataset = $this->mockDataset($user, ['annee', 'prix'], [['2019', '1'], ['2020', '2']]);
        $content = StudioContentFactory::new()->published()->create([
            'user_id' => $user->id,
            'card_block_id' => 'does-not-exist',
            'blocks' => [[
                'id' => 'chart-1',
                'type' => 'line',
                'datasetId' => (string) $dataset->id,
                'fieldMapping' => ['xAxis' => 'annee', 'yAxis' => 'prix'],
                'config' => [],
            ]],
        ]);

        $this->getJson("/api/studio/content/public/{$content->slug}/card-preview")
            ->assertOk()
            ->assertJsonPath('data.block_id', 'chart-1');
    }

    public function test_pie_segments_block_is_skipped(): void
    {
        $user = User::factory()->create();
        $dataset = $this->mockDataset($user, ['annee', 'prix'], [['2019', '1'], ['2020', '2']]);
        $content = StudioContentFactory::new()->published()->create([
            'user_id' => $user->id,
            'blocks' => [
                [
                    'id' => 'pie-seg',
                    'type' => 'pie',
                    'datasetId' => (string) $dataset->id,
                    'fieldMapping' => ['label' => 'annee', 'value' => 'prix'],
                    'config' => ['pieMode' => 'segments'],
                ],
                [
                    'id' => 'line-ok',
                    'type' => 'line',
                    'datasetId' => (string) $dataset->id,
                    'fieldMapping' => ['xAxis' => 'annee', 'yAxis' => 'prix'],
                    'config' => [],
                ],
            ],
        ]);

        $this->getJson("/api/studio/content/public/{$content->slug}/card-preview")
            ->assertOk()
            ->assertJsonPath('data.block_id', 'line-ok');
    }

    public function test_unresolved_token_filter_is_dropped_not_zero_rows(): void
    {
        $user = User::factory()->create();
        $dataset = $this->mockDataset($user, ['annee', 'prix'], [['2019', '1'], ['2020', '2']]);
        $content = StudioContentFactory::new()->published()->create([
            'user_id' => $user->id,
            'blocks' => [[
                'id' => 'chart-1',
                'type' => 'line',
                'datasetId' => (string) $dataset->id,
                'fieldMapping' => ['xAxis' => 'annee', 'yAxis' => 'prix'],
                'filters' => [['column' => 'annee', 'operator' => '=', 'value' => '{{annee}}']],
                'config' => [],
            ]],
        ]);

        $this->getJson("/api/studio/content/public/{$content->slug}/card-preview")
            ->assertOk()
            ->assertJsonPath('data.empty', false)
            ->assertJsonPath('data.labels', ['2019', '2020']);
    }

    public function test_draft_is_404_for_anonymous_and_200_for_editor(): void
    {
        $user = User::factory()->create();
        $dataset = $this->mockDataset($user, ['annee', 'prix'], [['2019', '1'], ['2020', '2']]);
        $content = StudioContentFactory::new()->create([
            'user_id' => $user->id,
            'blocks' => [[
                'id' => 'chart-1',
                'type' => 'line',
                'datasetId' => (string) $dataset->id,
                'fieldMapping' => ['xAxis' => 'annee', 'yAxis' => 'prix'],
                'config' => [],
            ]],
        ]);

        $this->getJson("/api/studio/content/public/{$content->slug}/card-preview")->assertStatus(404);

        $this->withToken($user->createToken('t')->plainTextToken)
            ->getJson("/api/studio/content/public/{$content->slug}/card-preview")
            ->assertOk()
            ->assertJsonPath('data.empty', false);
    }

    public function test_payload_stays_small(): void
    {
        $user = User::factory()->create();
        $rows = [];
        for ($y = 1980; $y < 2060; $y++) {
            $rows[] = [(string) $y, (string) ($y * 1.37)];
        }
        $dataset = $this->mockDataset($user, ['annee', 'prix'], $rows);
        $content = StudioContentFactory::new()->published()->create([
            'user_id' => $user->id,
            'blocks' => [[
                'id' => 'chart-1',
                'type' => 'line',
                'datasetId' => (string) $dataset->id,
                'fieldMapping' => ['xAxis' => 'annee', 'yAxis' => 'prix'],
                'config' => [],
            ]],
        ]);

        $res = $this->getJson("/api/studio/content/public/{$content->slug}/card-preview")->assertOk();

        $this->assertLessThanOrEqual(24, count($res->json('data.labels')));
        $this->assertLessThan(2048, strlen((string) json_encode($res->json('data'))));
    }
}
