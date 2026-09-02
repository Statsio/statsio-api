<?php

namespace Tests\Feature\Studio;

use App\Models\Channel\Channel;
use App\Models\Channel\ChannelUser;
use App\Models\User\User;
use Database\Factories\StudioContentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudioPublishingTest extends TestCase
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

    public function test_content_is_created_as_a_draft_and_is_not_public(): void
    {
        $res = $this->withToken($this->token)->postJson('/api/studio/content', [
            'title' => 'Mon StatsData',
            'coverage' => 'nationale',
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.coverage', 'nationale')
            ->assertJsonPath('data.published_version', null);

        $slug = $res->json('data.slug');
        $this->getJson("/api/studio/content/public/{$slug}")->assertStatus(404);
    }

    public function test_first_publish_creates_v1_and_locks_the_author(): void
    {
        $content = StudioContentFactory::new()->create([
            'user_id' => $this->user->id,
            'title' => 'V1 title',
            'blocks' => [['id' => 'b1', 'type' => 'heading', 'config' => ['text' => 'Hello']]],
        ]);

        $this->withToken($this->token)
            ->postJson("/api/studio/content/{$content->slug}/publish", ['published_as' => 'user'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.published_version', 1)
            ->assertJsonPath('data.published_as', 'user');

        $this->assertDatabaseHas('studio_content_versions', [
            'studio_content_id' => $content->id,
            'version' => 1,
            'title' => 'V1 title',
        ]);

        $this->getJson("/api/studio/content/public/{$content->slug}")
            ->assertStatus(200)
            ->assertJsonPath('data.title', 'V1 title');
    }

    public function test_editing_a_published_draft_does_not_change_the_public_page_until_republish(): void
    {
        $content = StudioContentFactory::new()->create(['user_id' => $this->user->id, 'title' => 'V1']);
        $this->withToken($this->token)->postJson("/api/studio/content/{$content->slug}/publish", ['published_as' => 'user'])->assertOk();

        // Modifie le brouillon
        $this->withToken($this->token)->patchJson("/api/studio/content/{$content->slug}", ['title' => 'V2 brouillon'])->assertOk();

        // Le public voit toujours la v1
        $this->getJson("/api/studio/content/public/{$content->slug}")
            ->assertOk()
            ->assertJsonPath('data.title', 'V1')
            ->assertJsonPath('data.published_version', 1);

        // Re-publie → v2
        $this->withToken($this->token)->postJson("/api/studio/content/{$content->slug}/publish")
            ->assertOk()
            ->assertJsonPath('data.published_version', 2);

        $this->getJson("/api/studio/content/public/{$content->slug}")
            ->assertOk()
            ->assertJsonPath('data.title', 'V2 brouillon')
            ->assertJsonPath('data.published_version', 2);
    }

    public function test_republish_ignores_a_new_author_choice(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        ChannelUser::create(['channel_id' => $channel->id, 'user_id' => $this->user->id, 'role' => 'owner']);

        $content = StudioContentFactory::new()->create(['user_id' => $this->user->id]);
        $this->withToken($this->token)->postJson("/api/studio/content/{$content->slug}/publish", ['published_as' => 'user'])->assertOk();

        $this->withToken($this->token)
            ->postJson("/api/studio/content/{$content->slug}/publish", ['published_as' => 'channel', 'channel_id' => $channel->id])
            ->assertOk()
            ->assertJsonPath('data.published_as', 'user')
            ->assertJsonPath('data.published_version', 2);
    }

    public function test_publish_as_channel_requires_management_role(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $content = StudioContentFactory::new()->create(['user_id' => $this->user->id]);

        $this->withToken($this->token)
            ->postJson("/api/studio/content/{$content->slug}/publish", ['published_as' => 'channel', 'channel_id' => $channel->id])
            ->assertStatus(403);
    }

    public function test_versions_listing_and_restore_into_draft(): void
    {
        $content = StudioContentFactory::new()->create(['user_id' => $this->user->id, 'title' => 'V1']);
        $this->withToken($this->token)->postJson("/api/studio/content/{$content->slug}/publish", ['published_as' => 'user'])->assertOk();
        $this->withToken($this->token)->patchJson("/api/studio/content/{$content->slug}", ['title' => 'V2'])->assertOk();
        $this->withToken($this->token)->postJson("/api/studio/content/{$content->slug}/publish")->assertOk();

        $this->withToken($this->token)->getJson("/api/studio/content/{$content->slug}/versions")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.version', 2)
            ->assertJsonPath('data.0.is_current', true)
            ->assertJsonPath('data.1.version', 1);

        // Restaure la v1 dans le brouillon — le public reste sur la v2
        $this->withToken($this->token)->postJson("/api/studio/content/{$content->slug}/versions/1/restore")
            ->assertOk()
            ->assertJsonPath('data.title', 'V1')
            ->assertJsonPath('data.published_version', 2);

        $this->getJson("/api/studio/content/public/{$content->slug}")
            ->assertOk()
            ->assertJsonPath('data.title', 'V2');
    }

    public function test_unpublish_hides_the_public_page_but_keeps_versions(): void
    {
        $content = StudioContentFactory::new()->create(['user_id' => $this->user->id]);
        $this->withToken($this->token)->postJson("/api/studio/content/{$content->slug}/publish", ['published_as' => 'user'])->assertOk();

        $this->withToken($this->token)->postJson("/api/studio/content/{$content->slug}/unpublish")
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');

        $this->getJson("/api/studio/content/public/{$content->slug}")->assertStatus(404);
        $this->assertDatabaseHas('studio_content_versions', ['studio_content_id' => $content->id, 'version' => 1]);
    }

    public function test_update_no_longer_publishes_via_status(): void
    {
        $content = StudioContentFactory::new()->create(['user_id' => $this->user->id]);

        $this->withToken($this->token)->patchJson("/api/studio/content/{$content->slug}", ['status' => 'published'])->assertOk();

        $this->assertDatabaseHas('studio_contents', ['id' => $content->id, 'status' => 'draft']);
        $this->getJson("/api/studio/content/public/{$content->slug}")->assertStatus(404);
    }
}
