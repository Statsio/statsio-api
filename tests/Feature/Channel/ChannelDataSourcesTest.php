<?php

namespace Tests\Feature\Channel;

use App\Models\Channel\Channel;
use App\Models\DataIngestion\Dataset;
use App\Models\DataIngestion\DataSource;
use App\Models\User\User;
use Database\Factories\StudioContentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelDataSourcesTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsOwner(Channel $channel): array
    {
        $user = User::factory()->create();
        $channel->users()->attach($user->id, ['role' => 'owner', 'subscribed_at' => now()]);

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

    public function test_returns_404_for_unknown_channel(): void
    {
        [, $token] = $this->actingAsOwner(Channel::factory()->withProfile()->create());

        $this->withToken($token)->getJson('/api/channels/999999/data-sources')
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_returns_empty_list_when_channel_has_no_content(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        [, $token] = $this->actingAsOwner($channel);

        $this->withToken($token)->getJson("/api/channels/{$channel->id}/data-sources")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', []);
    }

    public function test_aggregates_and_deduplicates_datasets_across_channel_content(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        [$owner, $token] = $this->actingAsOwner($channel);

        $shared = $this->makeDataset($owner, 'shared-dataset');
        $solo = $this->makeDataset($owner, 'solo-dataset');

        StudioContentFactory::new()->published()->create([
            'user_id' => $owner->id,
            'channel_id' => $channel->id,
            'published_as' => 'channel',
            'type' => 'statsdata',
            'blocks' => [
                ['type' => 'chart', 'datasetId' => (string) $shared->id],
                ['type' => 'table', 'datasetId' => (string) $solo->id],
            ],
        ]);

        StudioContentFactory::new()->published()->create([
            'user_id' => $owner->id,
            'channel_id' => $channel->id,
            'published_as' => 'channel',
            'type' => 'statsdata',
            'blocks' => [
                ['type' => 'chart', 'datasetId' => (string) $shared->id],
            ],
        ]);

        $response = $this->withToken($token)->getJson("/api/channels/{$channel->id}/data-sources")
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');

        $data = collect($response->json('data'))->keyBy('id');

        $this->assertSame(2, $data[(string) $shared->id]['used_by_count']);
        $this->assertSame(1, $data[(string) $solo->id]['used_by_count']);
        $this->assertSame('csv', $data[(string) $shared->id]['type']);
        $this->assertSame(4200, $data[(string) $shared->id]['row_count']);
    }

    public function test_ignores_datasets_from_content_not_published_as_channel(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        [$owner, $token] = $this->actingAsOwner($channel);

        $dataset = $this->makeDataset($owner, 'private-dataset');

        StudioContentFactory::new()->published()->create([
            'user_id' => $owner->id,
            'channel_id' => $channel->id,
            'published_as' => 'user',
            'type' => 'statsdata',
            'blocks' => [
                ['type' => 'chart', 'datasetId' => (string) $dataset->id],
            ],
        ]);

        $this->withToken($token)->getJson("/api/channels/{$channel->id}/data-sources")
            ->assertStatus(200)
            ->assertJsonPath('data', []);
    }
}
