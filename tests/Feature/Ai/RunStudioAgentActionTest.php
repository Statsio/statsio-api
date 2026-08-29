<?php

namespace Tests\Feature\Ai;

use App\Domain\Ai\Actions\RunStudioAgentAction;
use App\Models\Ai\AiConversation;
use App\Models\DataIngestion\Dataset;
use App\Models\DataIngestion\DatasetColumn;
use App\Models\DataIngestion\DataSource;
use App\Models\User\User;
use Database\Factories\StudioContentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RunStudioAgentActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.ai.gemini.api_key', 'test-key');
    }

    private function conversation(User $user): AiConversation
    {
        $content = StudioContentFactory::new()->create(['user_id' => $user->id, 'type' => 'statsdata']);

        return AiConversation::create(['user_id' => $user->id, 'studio_content_id' => $content->id]);
    }

    private function geminiText(string $text): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => $text]]]]]];
    }

    /** @param array<string,mixed> $args */
    private function geminiCall(string $name, array $args = []): array
    {
        return ['candidates' => [['content' => ['parts' => [['functionCall' => ['name' => $name, 'args' => $args]]]]]]];
    }

    public function test_agent_runs_a_read_tool_then_answers(): void
    {
        $user = User::factory()->create();

        $source = DataSource::create([
            'user_id' => $user->id, 'name' => 'Population régionale', 'type' => 'csv',
            'source_kind' => 'upload', 'materialization' => 'snapshot', 'status' => 'ready',
            'original_filename' => 'pop.csv', 'raw_storage_path' => 'data-sources/pop.csv', 'file_size_bytes' => 100,
        ]);
        $dataset = Dataset::create([
            'data_source_id' => $source->id, 'user_id' => $user->id, 'name' => 'Population régionale',
            'row_count' => 100, 'status' => 'ready',
        ]);
        DatasetColumn::create(['dataset_id' => $dataset->id, 'name' => 'region', 'type' => 'string', 'column_order' => 0]);

        Http::fakeSequence('generativelanguage.googleapis.com/*')
            ->push($this->geminiCall('list_sources'))
            ->push($this->geminiText('Tu as la source « Population régionale » avec la colonne region.'));

        $result = app(RunStudioAgentAction::class)->execute(
            $this->conversation($user),
            'Quelles sources ai-je ?',
        );

        $this->assertSame('Tu as la source « Population régionale » avec la colonne region.', $result->assistantMessage);
        $this->assertSame([], $result->patchOps);

        Http::assertSentCount(2);
        Http::assertSent(function (Request $request) {
            $body = $request->data();
            // 2e appel : contient le functionResponse de list_sources avec le dataset.
            if (! isset($body['contents'])) {
                return false;
            }
            foreach ($body['contents'] as $turn) {
                foreach ($turn['parts'] as $part) {
                    if (($part['functionResponse']['name'] ?? null) === 'list_sources'
                        && str_contains(
                            json_encode($part['functionResponse']['response'], JSON_UNESCAPED_UNICODE),
                            'Population régionale',
                        )) {
                        return true;
                    }
                }
            }

            return false;
        });
    }

    public function test_unknown_tool_call_is_reported_back_to_the_model(): void
    {
        $user = User::factory()->create();

        Http::fakeSequence('generativelanguage.googleapis.com/*')
            ->push($this->geminiCall('do_something_impossible'))
            ->push($this->geminiText('Désolé, je ne peux pas faire ça.'));

        $result = app(RunStudioAgentAction::class)->execute(
            $this->conversation($user),
            'fais un truc',
        );

        $this->assertSame('Désolé, je ne peux pas faire ça.', $result->assistantMessage);
        Http::assertSent(fn (Request $r) => str_contains(json_encode($r->data()), 'Outil inconnu'));
    }

    public function test_agent_builds_a_patch_from_write_tools(): void
    {
        $user = User::factory()->create();
        $source = DataSource::create([
            'user_id' => $user->id, 'name' => 'Pop', 'type' => 'csv', 'source_kind' => 'upload',
            'materialization' => 'snapshot', 'status' => 'ready', 'original_filename' => 'x.csv',
            'raw_storage_path' => 'data-sources/x.csv', 'file_size_bytes' => 1,
        ]);
        $dataset = Dataset::create([
            'data_source_id' => $source->id, 'user_id' => $user->id, 'name' => 'Pop',
            'row_count' => 10, 'status' => 'ready',
        ]);
        DatasetColumn::create(['dataset_id' => $dataset->id, 'name' => 'region', 'type' => 'string', 'column_order' => 0]);
        DatasetColumn::create(['dataset_id' => $dataset->id, 'name' => 'population', 'type' => 'integer', 'column_order' => 1]);

        Http::fakeSequence('generativelanguage.googleapis.com/*')
            ->push($this->geminiCall('add_section', ['ref' => 's1', 'page_ref' => 'default', 'layout' => '1-col']))
            ->push($this->geminiCall('add_block', [
                'ref' => 'b1', 'section_ref' => 's1', 'col' => 0, 'type' => 'bar',
                'dataset_id' => $dataset->id,
                'field_mapping_json' => '{"xAxis":"region","yAxes":["population"],"aggregate":"sum"}',
            ]))
            ->push($this->geminiText('J\'ai ajouté un graphique en barres de la population par région.'));

        $result = app(RunStudioAgentAction::class)->execute($this->conversation($user), 'graphique population par région');

        $this->assertCount(2, $result->patchOps);
        $this->assertSame('addSection', $result->patchOps[0]['op']);
        $this->assertSame('addBlock', $result->patchOps[1]['op']);
        $this->assertSame($dataset->id, $result->patchOps[1]['datasetId']);
    }

    public function test_loop_stops_after_max_iterations(): void
    {
        config()->set('services.ai.max_iterations', 2);
        $user = User::factory()->create();

        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($this->geminiCall('list_sources'))]);

        $result = app(RunStudioAgentAction::class)->execute($this->conversation($user), 'boucle');

        $this->assertStringContainsString('arrêté après 2 étapes', $result->assistantMessage);
        Http::assertSentCount(2);
    }
}
