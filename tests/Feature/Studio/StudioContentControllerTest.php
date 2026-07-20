<?php

namespace Tests\Feature\Studio;

use App\Models\Channel\Channel;
use App\Models\StudioContent;
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
            'title'       => 'Mon premier article',
            'description' => 'Une description',
            'status'      => 'draft',
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
