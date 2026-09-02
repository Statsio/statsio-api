<?php

namespace Tests\Feature\Studio;

use App\Models\Channel\Channel;
use App\Models\User\User;
use Database\Factories\StudioContentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudioContentControllerTest extends TestCase
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

    public function test_can_list_public_content(): void
    {
        StudioContentFactory::new()->published()->create(['user_id' => $this->user->id]);
        StudioContentFactory::new()->create(['user_id' => $this->user->id]);

        $response = $this->getJson('/api/studio/content/public');

        $response->assertStatus(200);
    }

    public function test_can_get_public_content_by_slug(): void
    {
        $content = StudioContentFactory::new()->published()->create(['user_id' => $this->user->id]);

        $response = $this->getJson("/api/studio/content/public/{$content->slug}");

        $response->assertStatus(200);
    }

    public function test_public_content_reports_favorite_and_follow_state_for_anonymous_viewer(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $content = StudioContentFactory::new()->published()->create([
            'user_id' => $this->user->id,
            'channel_id' => $channel->id,
            'published_as' => 'channel',
        ]);

        $response = $this->getJson("/api/studio/content/public/{$content->slug}");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_favorited', false)
            ->assertJsonPath('data.channel.is_following', false);
    }

    public function test_public_content_reports_favorite_state_for_a_viewer_who_favorited_it(): void
    {
        $content = StudioContentFactory::new()->published()->create(['user_id' => $this->user->id]);
        $this->user->favorites()->create([
            'favoritable_type' => $content->getMorphClass(),
            'favoritable_id' => $content->getKey(),
        ]);

        $response = $this->getJson("/api/studio/content/public/{$content->slug}", [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200)->assertJsonPath('data.is_favorited', true);
    }

    public function test_public_slug_returns_404_for_unknown(): void
    {
        $response = $this->getJson('/api/studio/content/public/unknown-slug');

        $response->assertStatus(404);
    }

    public function test_can_list_public_content_filtered_by_channel_id(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $otherChannel = Channel::factory()->withProfile()->create();

        $ownContent = StudioContentFactory::new()->published()->create([
            'user_id' => $this->user->id,
            'channel_id' => $channel->id,
            'published_as' => 'channel',
        ]);
        StudioContentFactory::new()->published()->create([
            'user_id' => $this->user->id,
            'channel_id' => $otherChannel->id,
            'published_as' => 'channel',
        ]);

        $response = $this->getJson("/api/studio/content/public?channel_id={$channel->id}");

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([(string) $ownContent->id], $ids);
    }

    public function test_authenticated_user_can_list_their_content(): void
    {
        StudioContentFactory::new()->count(2)->create(['user_id' => $this->user->id]);

        $response = $this->withToken($this->token)->getJson('/api/studio/content');

        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_cannot_list_content(): void
    {
        $this->getJson('/api/studio/content')->assertStatus(401);
    }

    public function test_authenticated_user_can_create_content(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/studio/content', [
            'title' => 'Mon premier article',
            'description' => 'Une description',
            'status' => 'draft',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('studio_contents', ['user_id' => $this->user->id]);
    }

    public function test_authenticated_user_can_update_own_content(): void
    {
        $content = StudioContentFactory::new()->create(['user_id' => $this->user->id]);

        $response = $this->withToken($this->token)->patchJson("/api/studio/content/{$content->slug}", [
            'title' => 'Titre mis à jour',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('studio_contents', ['id' => $content->id, 'title' => 'Titre mis à jour']);
    }

    public function test_update_persists_card_block_id_and_returns_it(): void
    {
        $content = StudioContentFactory::new()->create(['user_id' => $this->user->id]);

        $this->withToken($this->token)->patchJson("/api/studio/content/{$content->slug}", [
            'card_block_id' => 'chart-42',
        ])->assertStatus(200)->assertJsonPath('data.card_block_id', 'chart-42');

        $this->assertDatabaseHas('studio_contents', ['id' => $content->id, 'card_block_id' => 'chart-42']);

        $this->withToken($this->token)->patchJson("/api/studio/content/{$content->slug}", [
            'card_block_id' => null,
        ])->assertStatus(200)->assertJsonPath('data.card_block_id', null);
    }

    public function test_authenticated_user_can_change_own_content_slug(): void
    {
        $content = StudioContentFactory::new()->create(['user_id' => $this->user->id]);

        $response = $this->withToken($this->token)->patchJson("/api/studio/content/{$content->slug}", [
            'slug' => 'nouvelle-adresse-2026',
        ]);

        $response->assertStatus(200)->assertJsonPath('data.slug', 'nouvelle-adresse-2026');
        $this->assertDatabaseHas('studio_contents', ['id' => $content->id, 'slug' => 'nouvelle-adresse-2026']);
    }

    public function test_slug_update_rejects_a_slug_already_taken(): void
    {
        $taken = StudioContentFactory::new()->create(['slug' => 'deja-pris']);
        $content = StudioContentFactory::new()->create(['user_id' => $this->user->id]);

        $this->withToken($this->token)->patchJson("/api/studio/content/{$content->slug}", [
            'slug' => 'deja-pris',
        ])->assertStatus(422);
    }

    public function test_slug_update_rejects_an_invalid_format(): void
    {
        $content = StudioContentFactory::new()->create(['user_id' => $this->user->id]);

        $this->withToken($this->token)->patchJson("/api/studio/content/{$content->slug}", [
            'slug' => 'Pas Valide !',
        ])->assertStatus(422);
    }

    public function test_user_cannot_update_other_users_content(): void
    {
        $otherUser = User::factory()->create();
        $content = StudioContentFactory::new()->create(['user_id' => $otherUser->id]);

        $response = $this->withToken($this->token)->patchJson("/api/studio/content/{$content->slug}", [
            'title' => 'Tentative',
        ]);

        $response->assertStatus(404);
    }

    public function test_authenticated_user_can_delete_own_content(): void
    {
        $content = StudioContentFactory::new()->create(['user_id' => $this->user->id]);

        $response = $this->withToken($this->token)->deleteJson("/api/studio/content/{$content->slug}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('studio_contents', ['id' => $content->id]);
    }
}
