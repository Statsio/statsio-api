<?php

namespace Tests\Feature\Studio;

use App\Models\DataIngestion\DataSource;
use App\Models\DataIngestion\Dataset;
use App\Models\StudioContent;
use App\Models\User\User;
use Database\Factories\StudioContentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicDatasetQueryTest extends TestCase
{
    use RefreshDatabase;

    private function createDataset(User $user): Dataset
    {
        $dataSource = DataSource::create([
            'user_id' => $user->id,
            'name' => 'Source test',
            'type' => 'csv',
            'original_filename' => 'test.csv',
            'raw_storage_path' => 'data-sources/test.csv',
            'file_size_bytes' => 100,
        ]);

        return Dataset::create([
            'data_source_id' => $dataSource->id,
            'user_id' => $user->id,
            'name' => 'Dataset test',
        ]);
    }

    public function test_query_public_returns_404_for_unpublished_content(): void
    {
        $user = User::factory()->create();
        $dataset = $this->createDataset($user);
        $content = StudioContentFactory::new()->create([
            'user_id' => $user->id,
            'blocks' => [['datasetId' => (string) $dataset->id]],
        ]);

        $this->getJson("/api/studio/content/public/{$content->slug}/datasets/{$dataset->id}/query")
            ->assertStatus(404);
    }

    public function test_query_public_returns_403_when_dataset_not_referenced_by_content_blocks(): void
    {
        $user = User::factory()->create();
        $dataset = $this->createDataset($user);
        $content = StudioContentFactory::new()->published()->create([
            'user_id' => $user->id,
            'blocks' => [['datasetId' => '999999']],
        ]);

        $this->getJson("/api/studio/content/public/{$content->slug}/datasets/{$dataset->id}/query")
            ->assertStatus(403)
            ->assertJson(['success' => false]);
    }

    public function test_query_public_returns_rows_for_dataset_referenced_in_blocks(): void
    {
        $user = User::factory()->create();
        $dataset = $this->createDataset($user);
        $content = StudioContentFactory::new()->published()->create([
            'user_id' => $user->id,
            'blocks' => [['datasetId' => (string) $dataset->id]],
        ]);

        $response = $this->getJson("/api/studio/content/public/{$content->slug}/datasets/{$dataset->id}/query");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_rows', 0);
    }

    public function test_query_public_finds_dataset_referenced_via_join(): void
    {
        $user = User::factory()->create();
        $dataset = $this->createDataset($user);
        $content = StudioContentFactory::new()->published()->create([
            'user_id' => $user->id,
            'blocks' => [[
                'datasetId' => '999999',
                'joins' => [['datasetId' => (string) $dataset->id]],
            ]],
        ]);

        $this->getJson("/api/studio/content/public/{$content->slug}/datasets/{$dataset->id}/query")
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }
}
