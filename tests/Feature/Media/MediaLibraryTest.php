<?php

namespace Tests\Feature\Media;

use App\Models\Media;
use App\Models\User\User;
use Database\Factories\MediaFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('statsio.media.disk', 'local'));
        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    public function test_library_listing_requires_authentication(): void
    {
        $this->getJson('/api/media')->assertStatus(401);
    }

    public function test_library_returns_only_the_current_user_images(): void
    {
        MediaFactory::new()->create(['user_id' => $this->user->id]);
        MediaFactory::new()->create(['user_id' => $this->user->id, 'type' => 'video/mp4']);
        MediaFactory::new()->create(['user_id' => User::factory()->create()->id]);

        $this->withToken($this->token)->getJson('/api/media')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure(['data' => [['id', 'type', 'url']]]);
    }

    public function test_upload_attaches_the_media_to_the_authenticated_user(): void
    {
        $res = $this->withToken($this->token)->postJson('/api/media/upload', [
            'file' => UploadedFile::fake()->create('photo.png', 40, 'image/png'),
            'directory' => 'studio/images',
        ])->assertOk();

        $id = $res->json('data.id');
        $this->assertSame($this->user->id, Media::find($id)->user_id);
    }

    public function test_a_user_cannot_delete_another_users_media(): void
    {
        $other = MediaFactory::new()->create(['user_id' => User::factory()->create()->id]);

        $this->withToken($this->token)->deleteJson("/api/media/{$other->id}")->assertStatus(403);
        $this->assertDatabaseHas('media', ['id' => $other->id]);
    }

    public function test_a_user_can_delete_their_own_media(): void
    {
        $mine = MediaFactory::new()->create(['user_id' => $this->user->id]);

        $this->withToken($this->token)->deleteJson("/api/media/{$mine->id}")->assertOk();
        $this->assertDatabaseMissing('media', ['id' => $mine->id]);
    }
}
