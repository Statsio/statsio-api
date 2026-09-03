<?php

namespace Tests\Feature\User;

use App\Domain\Media\Actions\MediaAction;
use App\Models\Media;
use App\Models\User\User;
use Database\Factories\MediaFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AccountAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_and_remove_avatar(): void
    {
        // MediaAction est stubbé : le stockage disque réel n'est pas testable dans ce
        // sandbox (perms sur storage/framework/testing/disks). On vérifie le câblage
        // contrôleur → action → profil.
        $this->mock(MediaAction::class, function ($mock) {
            $media = new Media(['path' => 'avatars/fake.png', 'type' => 'image/png']);
            $media->id = 42;
            $mock->shouldReceive('upload')->once()->andReturn($media);
            $mock->shouldReceive('getUrl')->once()->andReturn('http://localhost/api/media/42/file');
        });

        $user = User::factory()->create();
        $user->profile()->create(['first_name' => 'Marie']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/me/avatar', [
            'file' => UploadedFile::fake()->create('avatar.png', 120, 'image/png'),
        ])->assertOk();

        $this->assertSame('http://localhost/api/media/42/file', $response->json('data.avatar'));
        $this->assertSame('http://localhost/api/media/42/file', $user->profile->fresh()->avatar);
        $this->assertSame('http://localhost/api/media/42/file', $response->json('data.user.profile.avatar'));

        $this->withToken($token)->deleteJson('/api/me/avatar')->assertOk();
        $this->assertNull($user->profile->fresh()->avatar);
    }

    public function test_user_can_set_avatar_from_media_library(): void
    {
        // Comme test_user_can_upload_and_remove_avatar : MediaAction est stubbé, le
        // stockage disque réel n'étant pas testable dans ce sandbox.
        $this->mock(MediaAction::class, function ($mock) {
            $copy = new Media(['path' => 'avatars/copy.png', 'type' => 'image/png']);
            $mock->shouldReceive('duplicate')->once()->andReturn($copy);
            $mock->shouldReceive('getUrl')->once()->andReturn('http://localhost/api/media/99/file');
        });

        $user = User::factory()->create();
        $user->profile()->create(['first_name' => 'Marie']);
        $token = $user->createToken('test')->plainTextToken;

        $source = MediaFactory::new()->create(['user_id' => $user->id]);

        $response = $this->withToken($token)->postJson('/api/me/avatar', [
            'media_id' => $source->id,
        ])->assertOk();

        $this->assertSame('http://localhost/api/media/99/file', $response->json('data.avatar'));
        $this->assertSame('http://localhost/api/media/99/file', $user->profile->fresh()->avatar);
        // Le média d'origine reste dans la bibliothèque.
        $this->assertDatabaseHas('media', ['id' => $source->id]);
    }

    public function test_avatar_from_media_library_rejects_media_of_another_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $foreign = MediaFactory::new()->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $this->withToken($token)->postJson('/api/me/avatar', [
            'media_id' => $foreign->id,
        ])->assertStatus(404);
    }

    public function test_avatar_rejects_non_image(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/me/avatar', [
            'file' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ])->assertStatus(422);
    }
}
