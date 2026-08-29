<?php

namespace Tests\Feature\Studio;

use App\Models\DataIngestion\Dataset;
use App\Models\DataIngestion\DataSource;
use App\Models\User\User;
use Database\Factories\StudioContentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudioContentDataSourcesTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): array
    {
        $user = User::factory()->create();

        return [$user, $user->createToken('test')->plainTextToken];
    }

    private function makeDataset(User $user, string $name): Dataset
    {
        $source = DataSource::create([
            'user_id' => $user->id,
            'name' => $name,
            'type' => 'csv',
            'original_filename' => "{$name}.csv",
            'raw_storage_path' => "sources/{$name}.csv",
            'file_size_bytes' => 1024,
            'status' => 'ready',
        ]);

        return Dataset::create([
            'data_source_id' => $source->id,
            'user_id' => $user->id,
            'name' => $name,
            'row_count' => 4200,
            'status' => 'ready',
        ]);
    }

    public function test_returns_404_for_content_of_another_user(): void
    {
        [, $token] = $this->actingAsUser();

        $other = StudioContentFactory::new()->create();

        $this->withToken($token)->getJson("/api/studio/content/{$other->slug}/data-sources")
            ->assertStatus(404);
    }

    public function test_returns_empty_list_when_content_has_no_dataset(): void
    {
        [$user, $token] = $this->actingAsUser();

        $content = StudioContentFactory::new()->create([
            'user_id' => $user->id,
            'blocks' => [['type' => 'heading', 'text' => 'Hello']],
        ]);

        $this->withToken($token)->getJson("/api/studio/content/{$content->slug}/data-sources")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', []);
    }

    public function test_aggregates_and_deduplicates_datasets_across_blocks_pages_and_sections(): void
    {
        [$user, $token] = $this->actingAsUser();

        $a = $this->makeDataset($user, 'dataset-a');
        $b = $this->makeDataset($user, 'dataset-b');

        $content = StudioContentFactory::new()->create([
            'user_id' => $user->id,
            'blocks' => [
                ['type' => 'chart', 'datasetId' => (string) $a->id],
                ['type' => 'table', 'datasetId' => (string) $a->id],
            ],
            'pages' => [
                ['blocks' => [['type' => 'chart', 'datasetId' => (string) $b->id]]],
            ],
            'sections' => [
                ['blocks' => [['type' => 'kpi', 'datasetId' => (string) $a->id]]],
            ],
        ]);

        $response = $this->withToken($token)->getJson("/api/studio/content/{$content->slug}/data-sources")
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');

        $data = collect($response->json('data'))->keyBy('id');

        $this->assertSame('dataset-a', $data[(string) $a->id]['name']);
        $this->assertSame('csv', $data[(string) $a->id]['type']);
        $this->assertSame(4200, $data[(string) $a->id]['row_count']);
        $this->assertSame(1, $data[(string) $a->id]['used_by_count']);
        $this->assertSame($content->title, $data[(string) $a->id]['used_by'][0]['title']);
    }
}
