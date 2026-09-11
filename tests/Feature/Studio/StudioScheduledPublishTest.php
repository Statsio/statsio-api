<?php

namespace Tests\Feature\Studio;

use App\Models\Channel\Channel;
use App\Models\Channel\ChannelUser;
use App\Models\User\User;
use Database\Factories\StudioContentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StudioScheduledPublishTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    public function test_saving_a_future_date_and_publishing_schedules_without_going_live(): void
    {
        Carbon::setTestNow('2026-09-11 10:00:00');

        $content = StudioContentFactory::new()->create([
            'user_id' => $this->user->id,
            'title' => 'À venir',
        ]);

        $this->withToken($this->token)
            ->patchJson("/api/studio/content/{$content->slug}", [
                'scheduled_publish_at' => '2026-09-20',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');

        $this->withToken($this->token)
            ->postJson("/api/studio/content/{$content->slug}/publish", ['published_as' => 'user'])
            ->assertOk()
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.published_version', null);

        $this->getJson("/api/studio/content/public/{$content->slug}")->assertStatus(404);
        $this->assertDatabaseMissing('studio_content_versions', [
            'studio_content_id' => $content->id,
        ]);
    }

    public function test_publish_immediate_bypasses_a_future_schedule(): void
    {
        Carbon::setTestNow('2026-09-11 10:00:00');

        $content = StudioContentFactory::new()->create([
            'user_id' => $this->user->id,
            'scheduled_publish_at' => '2026-09-20 00:00:00',
        ]);

        $this->withToken($this->token)
            ->postJson("/api/studio/content/{$content->slug}/publish", [
                'published_as' => 'user',
                'immediate' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.published_version', 1)
            ->assertJsonPath('data.scheduled_publish_at', null);
    }

    public function test_command_publishes_due_scheduled_contents_and_keeps_channel_author(): void
    {
        Carbon::setTestNow('2026-09-11 10:00:00');

        $channel = Channel::factory()->withProfile()->create();
        ChannelUser::create(['channel_id' => $channel->id, 'user_id' => $this->user->id, 'role' => 'owner']);

        $content = StudioContentFactory::new()->create([
            'user_id' => $this->user->id,
            'title' => 'Programmé chaîne',
            'status' => 'scheduled',
            'published_as' => 'channel',
            'channel_id' => $channel->id,
            'scheduled_publish_at' => '2026-09-11 09:00:00',
        ]);

        // Pas encore échue.
        StudioContentFactory::new()->create([
            'user_id' => $this->user->id,
            'status' => 'scheduled',
            'published_as' => 'user',
            'scheduled_publish_at' => '2026-09-12 00:00:00',
        ]);

        $this->artisan('content:publish-scheduled')->assertSuccessful();

        $content->refresh();
        $this->assertSame('published', $content->status);
        $this->assertSame('channel', $content->published_as);
        $this->assertSame($channel->id, $content->channel_id);
        $this->assertNull($content->scheduled_publish_at);
        $this->assertSame(1, $content->published_version);

        $this->getJson("/api/studio/content/public/{$content->slug}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Programmé chaîne');
    }

    public function test_clearing_scheduled_date_reverts_to_draft(): void
    {
        $content = StudioContentFactory::new()->create([
            'user_id' => $this->user->id,
            'status' => 'scheduled',
            'published_as' => 'user',
            'scheduled_publish_at' => now()->addDays(3),
        ]);

        $this->withToken($this->token)
            ->patchJson("/api/studio/content/{$content->slug}", ['scheduled_publish_at' => null])
            ->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.scheduled_publish_at', null);
    }

    public function test_past_or_empty_schedule_publishes_immediately(): void
    {
        Carbon::setTestNow('2026-09-11 10:00:00');

        $content = StudioContentFactory::new()->create([
            'user_id' => $this->user->id,
            'scheduled_publish_at' => '2026-09-01 00:00:00',
        ]);

        $this->withToken($this->token)
            ->postJson("/api/studio/content/{$content->slug}/publish", ['published_as' => 'user'])
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.scheduled_publish_at', null);
    }
}
