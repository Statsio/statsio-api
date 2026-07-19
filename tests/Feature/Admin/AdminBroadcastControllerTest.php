<?php

namespace Tests\Feature\Admin;

use App\Models\Tv\TvAudience;
use App\Models\User\User;
use Database\Factories\TvBroadcastFactory;
use Database\Factories\TvChannelFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBroadcastControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    private function asAdmin()
    {
        return $this->withToken($this->admin->createToken('test')->plainTextToken);
    }

    public function test_index_filters_by_channel_and_date_range(): void
    {
        $channel = TvChannelFactory::new()->create();
        TvBroadcastFactory::new()->create([
            'tv_channel_id' => $channel->slug,
            'start_at' => '2026-01-10 10:00:00',
            'end_at' => '2026-01-10 11:00:00',
        ]);
        TvBroadcastFactory::new()->create([
            'start_at' => '2020-01-01 10:00:00',
            'end_at' => '2020-01-01 11:00:00',
        ]);

        $response = $this->asAdmin()->getJson(
            "/api/admin/tv/broadcasts?channel={$channel->slug}&date_from=2026-01-01&date_to=2026-01-31"
        );

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_index_filters_by_search_on_program_title(): void
    {
        $matching = \Database\Factories\TvProgramFactory::new()->create(['title' => 'Zorglub Show']);
        TvBroadcastFactory::new()->create(['program_id' => $matching->id]);
        TvBroadcastFactory::new()->create();

        $response = $this->asAdmin()->getJson('/api/admin/tv/broadcasts?search=ZORGLUB');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_show_includes_program_and_audience(): void
    {
        $broadcast = TvBroadcastFactory::new()->create();
        TvAudience::create(['broadcast_id' => $broadcast->id, 'viewers' => 42]);

        $this->asAdmin()->getJson("/api/admin/tv/broadcasts/{$broadcast->id}")
            ->assertStatus(200)
            ->assertJsonPath('audience.viewers', 42)
            ->assertJsonPath('program.id', $broadcast->program_id);
    }

    public function test_update_changes_season_episode_and_broadcast_type(): void
    {
        $broadcast = TvBroadcastFactory::new()->create();

        $this->asAdmin()->patchJson("/api/admin/tv/broadcasts/{$broadcast->id}", [
            'season' => 2,
            'episode' => 5,
            'broadcast_type' => 'rediffusion',
        ])->assertStatus(200)
            ->assertJsonPath('season', 2)
            ->assertJsonPath('episode', 5)
            ->assertJsonPath('broadcast_type', 'rediffusion');
    }

    public function test_update_audience_creates_when_missing_and_updates_when_present(): void
    {
        $broadcast = TvBroadcastFactory::new()->create();

        $this->asAdmin()->patchJson("/api/admin/tv/broadcasts/{$broadcast->id}/audience", [
            'pda' => 12.5,
            'rank' => 3,
        ])->assertStatus(200)->assertJsonPath('audience.pda', 12.5);

        $this->assertDatabaseCount('tv_audiences', 1);

        // pda=20.0 est un nombre entier une fois arrondi : json_encode() l'émet sans décimale
        // ("20"), donc json_decode() le redonne en int, pas en float — assertJsonPath compare
        // en strict, d'où l'attendu 20 (int) plutôt que 20.0.
        $this->asAdmin()->patchJson("/api/admin/tv/broadcasts/{$broadcast->id}/audience", [
            'pda' => 20.0,
        ])->assertStatus(200)->assertJsonPath('audience.pda', 20);

        $this->assertDatabaseCount('tv_audiences', 1);
        $this->assertSame(3, TvAudience::find($broadcast->id)->rank);
    }

    public function test_destroy_removes_broadcast_audience_and_user_views(): void
    {
        $broadcast = TvBroadcastFactory::new()->create();
        TvAudience::create(['broadcast_id' => $broadcast->id, 'viewers' => 1]);

        $this->asAdmin()->deleteJson("/api/admin/tv/broadcasts/{$broadcast->id}")->assertStatus(204);

        $this->assertDatabaseMissing('tv_broadcasts', ['id' => $broadcast->id]);
        $this->assertDatabaseMissing('tv_audiences', ['broadcast_id' => $broadcast->id]);
    }
}
