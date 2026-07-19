<?php

namespace Tests\Feature\Admin;

use App\Models\Tv\TvAudience;
use App\Models\Tv\TvCategory;
use App\Models\Tv\TvProgram;
use App\Models\User\User;
use Database\Factories\TvBroadcastFactory;
use Database\Factories\TvChannelFactory;
use Database\Factories\TvProgramFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProgramControllerTest extends TestCase
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

    public function test_index_filters_by_channel_and_pick(): void
    {
        $channel = TvChannelFactory::new()->create();
        TvProgramFactory::new()->create(['tv_channel_id' => $channel->slug, 'is_tvstats_pick' => true]);
        TvProgramFactory::new()->create(['is_tvstats_pick' => false]);

        $response = $this->asAdmin()->getJson("/api/admin/tv/programs?channel={$channel->slug}&pick=1");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_index_filters_by_search_and_type(): void
    {
        TvProgramFactory::new()->create(['title' => 'Zorglub Show', 'type' => 'series']);
        TvProgramFactory::new()->create(['title' => 'Autre Programme', 'type' => 'movie']);

        $response = $this->asAdmin()->getJson('/api/admin/tv/programs?search=ZORGLUB');
        $response->assertStatus(200);
        $this->assertSame(['Zorglub Show'], collect($response->json('data'))->pluck('title')->all());

        $response = $this->asAdmin()->getJson('/api/admin/tv/programs?type=SERIES');
        $response->assertStatus(200);
        $this->assertSame(['Zorglub Show'], collect($response->json('data'))->pluck('title')->all());
    }

    public function test_show_includes_recent_broadcasts_with_audience(): void
    {
        $program = TvProgramFactory::new()->create();
        $broadcast = TvBroadcastFactory::new()->create(['program_id' => $program->id]);
        TvAudience::create(['broadcast_id' => $broadcast->id, 'viewers' => 500]);

        $response = $this->asAdmin()->getJson("/api/admin/tv/programs/{$program->id}");

        $response->assertStatus(200)
            ->assertJsonPath('broadcasts_count', 1)
            ->assertJsonPath('broadcasts.0.audience.viewers', 500);
    }

    public function test_update_changes_fields_and_syncs_categories(): void
    {
        $program = TvProgramFactory::new()->create();
        $category = TvCategory::where('slug', 'sport')->firstOrFail();

        $response = $this->asAdmin()->patchJson("/api/admin/tv/programs/{$program->id}", [
            'title' => 'Nouveau titre',
            'category_ids' => [$category->id],
        ]);

        $response->assertStatus(200)->assertJsonPath('title', 'Nouveau titre');
        $this->assertSame([$category->id], $program->fresh()->categories->pluck('id')->all());
    }

    public function test_destroy_cascades_broadcasts_audience_and_user_views(): void
    {
        $program = TvProgramFactory::new()->create();
        $broadcast = TvBroadcastFactory::new()->create(['program_id' => $program->id]);
        TvAudience::create(['broadcast_id' => $broadcast->id, 'viewers' => 10]);

        $this->asAdmin()->deleteJson("/api/admin/tv/programs/{$program->id}")->assertStatus(204);

        $this->assertDatabaseMissing('tv_programs', ['id' => $program->id]);
        $this->assertDatabaseMissing('tv_broadcasts', ['id' => $broadcast->id]);
        $this->assertDatabaseMissing('tv_audiences', ['broadcast_id' => $broadcast->id]);
    }
}
