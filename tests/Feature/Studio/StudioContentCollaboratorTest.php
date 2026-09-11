<?php

namespace Tests\Feature\Studio;

use App\Mail\Studio\StudioContentInvitationMailable;
use App\Models\Media;
use App\Models\Studio\StudioContentCollaborator;
use App\Models\Studio\StudioContentInvitation;
use App\Models\User\User;
use Database\Factories\StudioContentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudioContentCollaboratorTest extends TestCase
{
    use RefreshDatabase;

    private function fullWritePermissions(): array
    {
        return [
            'contenu' => 'write',
            'publication' => 'write',
            'sources' => 'write',
            'historique' => 'write',
            'studio' => 'write',
        ];
    }

    private function readOnlyStudio(): array
    {
        return [
            'contenu' => 'read',
            'publication' => 'none',
            'sources' => 'none',
            'historique' => 'none',
            'studio' => 'read',
        ];
    }

    public function test_owner_can_invite_collaborators(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $token = $owner->createToken('test')->plainTextToken;
        $content = StudioContentFactory::new()->create(['user_id' => $owner->id]);

        $response = $this->withToken($token)->postJson("/api/studio/content/{$content->slug}/invitations", [
            'emails' => ['collab@example.com'],
            'permissions' => $this->fullWritePermissions(),
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.created', ['collab@example.com']);
        $this->assertDatabaseCount('studio_content_invitations', 1);
        Mail::assertQueued(StudioContentInvitationMailable::class, 1);
    }

    public function test_non_owner_cannot_invite(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $content = StudioContentFactory::new()->create(['user_id' => $owner->id]);
        $token = $other->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson("/api/studio/content/{$content->slug}/invitations", [
            'emails' => ['x@example.com'],
            'permissions' => $this->fullWritePermissions(),
        ])->assertStatus(403);
    }

    public function test_accept_invitation_grants_access(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'collab@example.com']);
        $content = StudioContentFactory::new()->create(['user_id' => $owner->id]);

        $plain = Str::random(64);
        StudioContentInvitation::create([
            'studio_content_id' => $content->id,
            'email' => 'collab@example.com',
            'permissions' => $this->fullWritePermissions(),
            'token' => hash('sha256', $plain),
            'invited_by' => $owner->id,
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        $inviteeToken = $invitee->createToken('test')->plainTextToken;
        $this->withToken($inviteeToken)
            ->postJson("/api/studio/content/invitations/{$plain}/accept")
            ->assertStatus(200)
            ->assertJsonPath('data.slug', $content->slug);

        $this->assertDatabaseHas('studio_content_collaborators', [
            'studio_content_id' => $content->id,
            'user_id' => $invitee->id,
        ]);

        $this->withToken($inviteeToken)
            ->getJson("/api/studio/content/{$content->slug}")
            ->assertStatus(200)
            ->assertJsonPath('data.access.is_owner', false)
            ->assertJsonPath('data.access.permissions.studio', 'write');
    }

    public function test_email_mismatch_rejects_accept(): void
    {
        $owner = User::factory()->create();
        $content = StudioContentFactory::new()->create(['user_id' => $owner->id]);
        $wrongUser = User::factory()->create(['email' => 'other@example.com']);

        $plain = Str::random(64);
        StudioContentInvitation::create([
            'studio_content_id' => $content->id,
            'email' => 'collab@example.com',
            'permissions' => $this->fullWritePermissions(),
            'token' => hash('sha256', $plain),
            'invited_by' => $owner->id,
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        $this->withToken($wrongUser->createToken('test')->plainTextToken)
            ->postJson("/api/studio/content/invitations/{$plain}/accept")
            ->assertStatus(422);
    }

    public function test_read_only_collaborator_cannot_update_studio_blocks(): void
    {
        $owner = User::factory()->create();
        $collab = User::factory()->create();
        $content = StudioContentFactory::new()->create(['user_id' => $owner->id]);

        StudioContentCollaborator::create([
            'studio_content_id' => $content->id,
            'user_id' => $collab->id,
            'permissions' => $this->readOnlyStudio(),
            'invited_by' => $owner->id,
        ]);

        $this->withToken($collab->createToken('test')->plainTextToken)
            ->patchJson("/api/studio/content/{$content->slug}", [
                'blocks' => [['id' => 'b1', 'type' => 'text']],
            ])
            ->assertStatus(403);

        $this->withToken($collab->createToken('test')->plainTextToken)
            ->getJson("/api/studio/content/{$content->slug}")
            ->assertStatus(200);
    }

    public function test_collaborator_cannot_delete_content(): void
    {
        $owner = User::factory()->create();
        $collab = User::factory()->create();
        $content = StudioContentFactory::new()->create(['user_id' => $owner->id]);

        StudioContentCollaborator::create([
            'studio_content_id' => $content->id,
            'user_id' => $collab->id,
            'permissions' => $this->fullWritePermissions(),
            'invited_by' => $owner->id,
        ]);

        $this->withToken($collab->createToken('test')->plainTextToken)
            ->deleteJson("/api/studio/content/{$content->slug}")
            ->assertStatus(403);
    }

    public function test_shared_contents_appear_in_index(): void
    {
        $owner = User::factory()->create();
        $collab = User::factory()->create();
        $content = StudioContentFactory::new()->create(['user_id' => $owner->id, 'title' => 'Partagé']);

        StudioContentCollaborator::create([
            'studio_content_id' => $content->id,
            'user_id' => $collab->id,
            'permissions' => $this->fullWritePermissions(),
            'invited_by' => $owner->id,
        ]);

        $this->withToken($collab->createToken('test')->plainTextToken)
            ->getJson('/api/studio/content')
            ->assertStatus(200)
            ->assertJsonFragment(['title' => 'Partagé', 'is_shared' => true]);
    }

    public function test_media_upload_with_content_slug_belongs_to_owner(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $collab = User::factory()->create();
        $content = StudioContentFactory::new()->create(['user_id' => $owner->id]);

        StudioContentCollaborator::create([
            'studio_content_id' => $content->id,
            'user_id' => $collab->id,
            'permissions' => $this->fullWritePermissions(),
            'invited_by' => $owner->id,
        ]);

        $file = UploadedFile::fake()->image('photo.jpg');

        $response = $this->withToken($collab->createToken('test')->plainTextToken)
            ->post('/api/media/upload', [
                'file' => $file,
                'directory' => 'studio/images',
                'studio_content_slug' => $content->slug,
            ]);

        $response->assertStatus(200);
        $mediaId = $response->json('data.id');
        $this->assertDatabaseHas('media', [
            'id' => $mediaId,
            'user_id' => $owner->id,
        ]);
        $this->assertNotEquals($collab->id, Media::find($mediaId)?->user_id);
    }

    public function test_can_revoke_and_update_collaborator_permissions(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $token = $owner->createToken('test')->plainTextToken;
        $content = StudioContentFactory::new()->create(['user_id' => $owner->id]);
        $collab = User::factory()->create();

        StudioContentCollaborator::create([
            'studio_content_id' => $content->id,
            'user_id' => $collab->id,
            'permissions' => $this->fullWritePermissions(),
            'invited_by' => $owner->id,
        ]);

        $this->withToken($token)->patchJson(
            "/api/studio/content/{$content->slug}/collaborators/{$collab->id}",
            ['permissions' => $this->readOnlyStudio()],
        )->assertStatus(200)
            ->assertJsonPath('data.permissions.studio', 'read');

        $this->withToken($token)
            ->deleteJson("/api/studio/content/{$content->slug}/collaborators/{$collab->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('studio_content_collaborators', [
            'studio_content_id' => $content->id,
            'user_id' => $collab->id,
        ]);
    }
}
