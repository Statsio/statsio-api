<?php

namespace Tests\Feature\Tv;

use App\Models\Tv\TvChannel;
use App\Models\User\User;
use Database\Factories\TvBroadcastFactory;
use Database\Factories\TvChannelFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TvControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_active_tv_channels(): void
    {
        TvChannel::query()->delete();
        TvChannelFactory::new()->count(3)->create(['is_active' => true]);
        TvChannelFactory::new()->count(2)->create(['is_active' => false]);

        $response = $this->getJson('/api/tv/channels');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json());
    }

    public function test_channels_are_ordered_by_number(): void
    {
        TvChannel::query()->delete();
        TvChannelFactory::new()->create(['number' => 3]);
        TvChannelFactory::new()->create(['number' => 1]);
        TvChannelFactory::new()->create(['number' => 2]);

        $response = $this->getJson('/api/tv/channels');

        $numbers = array_column($response->json(), 'number');
        $this->assertSame([1, 2, 3], $numbers);
    }

    public function test_epg_rejects_invalid_date_format(): void
    {
        $response = $this->getJson('/api/tv/epg?date=not-a-date');

        $response->assertStatus(422);
    }

    public function test_can_get_audiences(): void
    {
        $response = $this->getJson('/api/tv/audiences');

        $response->assertStatus(200);
    }

    public function test_can_get_broadcast_details(): void
    {
        $broadcast = TvBroadcastFactory::new()->create();

        $response = $this->getJson("/api/tv/broadcasts/{$broadcast->id}");

        $response->assertStatus(200)
                 ->assertJsonStructure(['id', 'startAt', 'endAt', 'program']);
    }

    public function test_broadcast_returns_404_for_unknown_id(): void
    {
        $response = $this->getJson('/api/tv/broadcasts/99999');

        $response->assertStatus(404);
    }

    public function test_authenticated_user_can_toggle_broadcast_view(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $broadcast = TvBroadcastFactory::new()->create();

        $response = $this->withToken($token)->postJson("/api/tv/broadcasts/{$broadcast->id}/view", [
            'type' => 'will_watch',
        ]);

        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_cannot_toggle_view(): void
    {
        $broadcast = TvBroadcastFactory::new()->create();

        $response = $this->postJson("/api/tv/broadcasts/{$broadcast->id}/view", [
            'status' => 'will_watch',
        ]);

        $response->assertStatus(401);
    }

    public function test_can_get_broadcast_reviews(): void
    {
        $broadcast = TvBroadcastFactory::new()->create();

        $response = $this->getJson("/api/tv/broadcasts/{$broadcast->id}/reviews");

        $response->assertStatus(200);
    }
}
