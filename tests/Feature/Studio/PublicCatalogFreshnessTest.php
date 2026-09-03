<?php

namespace Tests\Feature\Studio;

use App\Models\DataIngestion\Dataset;
use App\Models\DataIngestion\DataSource;
use App\Models\User\User;
use Database\Factories\StudioContentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCatalogFreshnessTest extends TestCase
{
    use RefreshDatabase;

    private function dataset(User $user, string $frequency, ?\DateTimeInterface $lastRefreshed): Dataset
    {
        $source = DataSource::create([
            'user_id' => $user->id,
            'name' => 'Ressource data.gouv.fr',
            'type' => 'json',
            'source_kind' => 'api',
            'materialization' => 'snapshot',
            'api_config' => ['url' => 'https://example.com/data/'],
            'original_filename' => 'Ressource data.gouv.fr.json',
            'raw_storage_path' => null,
            'file_size_bytes' => 0,
            'refresh_frequency' => $frequency,
            'last_refreshed_at' => $lastRefreshed,
            'status' => 'ready',
        ]);

        return Dataset::create([
            'data_source_id' => $source->id,
            'user_id' => $user->id,
            'name' => 'Dataset',
            'row_count' => 10,
            'status' => 'ready',
        ]);
    }

    public function test_card_exposes_freshness_for_a_scheduled_source(): void
    {
        $user = User::factory()->create();
        $dataset = $this->dataset($user, 'hourly', now()->subMinutes(20));

        StudioContentFactory::new()->published()->create([
            'user_id' => $user->id,
            'type' => 'statsdata',
            'title' => 'Prix des carburants',
            'blocks' => [['type' => 'bar', 'datasetId' => (string) $dataset->id, 'config' => []]],
        ]);

        $response = $this->getJson('/api/studio/content/public/catalog?type=statsdata');

        $response->assertOk()
            ->assertJsonPath('data.0.freshness.refresh_frequency', 'hourly')
            ->assertJsonPath('data.0.freshness.is_live', false);
        $this->assertNotNull($response->json('data.0.freshness.last_refreshed_at'));
    }

    public function test_card_freshness_is_null_when_source_never_syncs(): void
    {
        $user = User::factory()->create();
        $dataset = $this->dataset($user, 'none', now()->subDay());

        StudioContentFactory::new()->published()->create([
            'user_id' => $user->id,
            'type' => 'statsdata',
            'title' => 'Jeu figé',
            'blocks' => [['type' => 'bar', 'datasetId' => (string) $dataset->id, 'config' => []]],
        ]);

        $response = $this->getJson('/api/studio/content/public/catalog?type=statsdata');

        $response->assertOk()->assertJsonPath('data.0.freshness', null);
    }
}
