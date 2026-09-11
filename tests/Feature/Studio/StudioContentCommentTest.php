<?php

namespace Tests\Feature\Studio;

use App\Models\Studio\StudioContentComment;
use App\Models\User\User;
use Database\Factories\StudioContentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudioContentCommentTest extends TestCase
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

    public function test_guest_can_list_comments_on_published_content(): void
    {
        $content = StudioContentFactory::new()->published()->create([
            'user_id' => $this->user->id,
            'comments_enabled' => true,
        ]);
        StudioContentComment::create([
            'studio_content_id' => $content->id,
            'user_id' => $this->user->id,
            'body' => 'Très utile, merci.',
        ]);

        $this->getJson("/api/studio/content/public/{$content->slug}/comments")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.body', 'Très utile, merci.');
    }

    public function test_authenticated_user_can_post_a_comment(): void
    {
        $content = StudioContentFactory::new()->published()->create([
            'user_id' => $this->user->id,
            'comments_enabled' => true,
        ]);

        $this->withToken($this->token)
            ->postJson("/api/studio/content/public/{$content->slug}/comments", [
                'body' => 'Une question sur la méthodologie.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Une question sur la méthodologie.')
            ->assertJsonPath('data.can_delete', true);

        $this->assertDatabaseHas('studio_content_comments', [
            'studio_content_id' => $content->id,
            'user_id' => $this->user->id,
            'body' => 'Une question sur la méthodologie.',
        ]);
    }

    public function test_guest_cannot_post_a_comment(): void
    {
        $content = StudioContentFactory::new()->published()->create([
            'user_id' => $this->user->id,
        ]);

        $this->postJson("/api/studio/content/public/{$content->slug}/comments", [
            'body' => 'Hello',
        ])->assertUnauthorized();
    }

    public function test_comments_disabled_returns_404_on_list_and_403_on_post(): void
    {
        $content = StudioContentFactory::new()->published()->create([
            'user_id' => $this->user->id,
            'comments_enabled' => false,
        ]);

        $this->getJson("/api/studio/content/public/{$content->slug}/comments")->assertNotFound();

        $this->withToken($this->token)
            ->postJson("/api/studio/content/public/{$content->slug}/comments", ['body' => 'Nope'])
            ->assertForbidden();
    }

    public function test_author_can_delete_own_comment_and_owner_can_moderate(): void
    {
        $owner = $this->user;
        $author = User::factory()->create();
        $authorToken = $author->createToken('test')->plainTextToken;

        $content = StudioContentFactory::new()->published()->create([
            'user_id' => $owner->id,
            'comments_enabled' => true,
        ]);

        $own = StudioContentComment::create([
            'studio_content_id' => $content->id,
            'user_id' => $author->id,
            'body' => 'Mon commentaire',
        ]);
        $other = StudioContentComment::create([
            'studio_content_id' => $content->id,
            'user_id' => $author->id,
            'body' => 'À modérer',
        ]);

        $this->withToken($authorToken)
            ->deleteJson("/api/studio/content/public/{$content->slug}/comments/{$own->id}")
            ->assertOk();
        $this->assertDatabaseMissing('studio_content_comments', ['id' => $own->id]);

        $this->withToken($this->token)
            ->deleteJson("/api/studio/content/public/{$content->slug}/comments/{$other->id}")
            ->assertOk();
        $this->assertDatabaseMissing('studio_content_comments', ['id' => $other->id]);
    }

    public function test_owner_can_toggle_comments_enabled(): void
    {
        $content = StudioContentFactory::new()->create(['user_id' => $this->user->id]);

        $this->withToken($this->token)
            ->patchJson("/api/studio/content/{$content->slug}", ['comments_enabled' => false])
            ->assertOk()
            ->assertJsonPath('data.comments_enabled', false);
    }

    public function test_owner_can_toggle_download_and_embed_enabled(): void
    {
        $content = StudioContentFactory::new()->create(['user_id' => $this->user->id]);

        $this->withToken($this->token)
            ->patchJson("/api/studio/content/{$content->slug}", [
                'download_enabled' => false,
                'embed_enabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.download_enabled', false)
            ->assertJsonPath('data.embed_enabled', false);
    }
}
