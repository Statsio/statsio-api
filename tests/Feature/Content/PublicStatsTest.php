<?php

namespace Tests\Feature\Content;

use App\Models\Channel\Channel;
use App\Models\DataIngestion\Dataset;
use App\Models\DataIngestion\DataSource;
use App\Models\User\User;
use Database\Factories\StudioContentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PublicStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_it_returns_published_content_counts_by_type(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        StudioContentFactory::new()->published()->create(['user_id' => $alice->id, 'type' => 'statsdata']);
        StudioContentFactory::new()->published()->create(['user_id' => $alice->id, 'type' => 'statsdata']);
        StudioContentFactory::new()->published()->create(['user_id' => $bob->id, 'type' => 'article']);
        StudioContentFactory::new()->published()->create(['user_id' => $bob->id, 'type' => 'survey']);
        // Brouillon : ne doit pas être compté.
        StudioContentFactory::new()->create(['user_id' => $bob->id, 'type' => 'statsdata']);

        $this->getJson('/api/public-stats')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.statsdata', 2)
            ->assertJsonPath('data.articles', 1)
            ->assertJsonPath('data.surveys', 1)
            ->assertJsonPath('data.published_total', 4)
            ->assertJsonPath('data.contributors', 2);
    }

    public function test_it_counts_ready_datasets_and_active_channels(): void
    {
        $user = User::factory()->create();

        $source = DataSource::create([
            'user_id' => $user->id,
            'name' => 'Ressource',
            'type' => 'json',
            'source_kind' => 'api',
            'materialization' => 'snapshot',
            'api_config' => ['url' => 'https://example.com/data/'],
            'original_filename' => 'Ressource.json',
            'raw_storage_path' => null,
            'file_size_bytes' => 0,
            'status' => 'ready',
        ]);
        Dataset::create(['data_source_id' => $source->id, 'user_id' => $user->id, 'name' => 'D1', 'row_count' => 5, 'status' => 'ready']);
        Dataset::create(['data_source_id' => $source->id, 'user_id' => $user->id, 'name' => 'D2', 'row_count' => 5, 'status' => 'pending']);

        Channel::factory()->create(['status' => 'active']);
        Channel::factory()->create(['status' => 'banned']);

        $this->getJson('/api/public-stats')
            ->assertOk()
            ->assertJsonPath('data.datasets', 1)
            ->assertJsonPath('data.channels', 1);
    }

    public function test_it_is_publicly_accessible_and_shaped(): void
    {
        $this->getJson('/api/public-stats')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'statsdata',
                    'articles',
                    'surveys',
                    'published_total',
                    'datasets',
                    'channels',
                    'contributors',
                    'last_published_at',
                ],
            ]);
    }
}
