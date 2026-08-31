<?php

namespace Tests\Feature\Channel;

use App\Mail\Channel\ChannelInvitationMailable;
use App\Models\Channel\Channel;
use App\Models\Channel\ChannelInvitation;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChannelInvitationTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(Channel $channel, string $role): string
    {
        $user = User::factory()->create();
        $channel->users()->attach($user->id, ['role' => $role, 'subscribed_at' => now()]);

        return $user->createToken('test')->plainTextToken;
    }

    public function test_owner_can_invite_new_members_by_email(): void
    {
        Mail::fake();
        $channel = Channel::factory()->withProfile()->create();
        $token = $this->actingAsRole($channel, 'owner');

        $response = $this->withToken($token)->postJson("/api/channels/{$channel->id}/invitations", [
            'emails' => ['redactor@example.com', 'guest@example.com'],
            'role' => 'redactor',
            'permissions' => ['contents.create', 'contents.edit'],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.created', ['redactor@example.com', 'guest@example.com'])
            ->assertJsonPath('data.skipped', []);

        $this->assertDatabaseCount('channel_invitations', 2);
        Mail::assertQueued(ChannelInvitationMailable::class, 2);
    }

    public function test_admin_and_owner_permissions_are_forced_server_side(): void
    {
        Mail::fake();
        $channel = Channel::factory()->withProfile()->create();
        $token = $this->actingAsRole($channel, 'owner');

        $this->withToken($token)->postJson("/api/channels/{$channel->id}/invitations", [
            'emails' => ['admin@example.com'],
            'role' => 'admin',
            // Le client tente de restreindre les permissions d'un admin — le serveur doit ignorer ça.
            'permissions' => ['contents.create'],
        ])->assertStatus(200);

        $invitation = ChannelInvitation::where('email', 'admin@example.com')->first();
        $this->assertContains('team.invite_members', $invitation->permissions);
        $this->assertContains('channel.edit_profile', $invitation->permissions);
    }

    public function test_invite_skips_emails_already_members(): void
    {
        Mail::fake();
        $channel = Channel::factory()->withProfile()->create();
        $token = $this->actingAsRole($channel, 'owner');
        $existingMember = User::factory()->create(['email' => 'already@example.com']);
        $channel->users()->attach($existingMember->id, ['role' => 'redactor', 'subscribed_at' => now()]);

        $response = $this->withToken($token)->postJson("/api/channels/{$channel->id}/invitations", [
            'emails' => ['already@example.com'],
            'role' => 'redactor',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.created', [])
            ->assertJsonPath('data.skipped.0.email', 'already@example.com');
        $this->assertDatabaseCount('channel_invitations', 0);
    }

    public function test_invite_resends_existing_pending_invitation_instead_of_duplicating(): void
    {
        Mail::fake();
        $channel = Channel::factory()->withProfile()->create();
        $token = $this->actingAsRole($channel, 'owner');

        $this->withToken($token)->postJson("/api/channels/{$channel->id}/invitations", [
            'emails' => ['pending@example.com'],
            'role' => 'guest',
        ])->assertStatus(200)->assertJsonPath('data.created', ['pending@example.com']);

        $response = $this->withToken($token)->postJson("/api/channels/{$channel->id}/invitations", [
            'emails' => ['pending@example.com'],
            'role' => 'redactor',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.created', [])
            ->assertJsonPath('data.resent', ['pending@example.com']);
        $this->assertDatabaseCount('channel_invitations', 1);
        $this->assertSame('redactor', ChannelInvitation::first()->role);
    }

    public function test_redactor_without_invite_permission_cannot_invite(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $user = User::factory()->create();
        $channel->users()->attach($user->id, ['role' => 'redactor', 'permissions' => json_encode(['contents.create']), 'subscribed_at' => now()]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson("/api/channels/{$channel->id}/invitations", [
            'emails' => ['someone@example.com'],
            'role' => 'guest',
        ])->assertStatus(403);
    }

    public function test_member_with_invite_permission_can_invite_even_without_admin_role(): void
    {
        Mail::fake();
        $channel = Channel::factory()->withProfile()->create();
        $user = User::factory()->create();
        $channel->users()->attach($user->id, ['role' => 'redactor', 'permissions' => json_encode(['team.invite_members']), 'subscribed_at' => now()]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson("/api/channels/{$channel->id}/invitations", [
            'emails' => ['someone@example.com'],
            'role' => 'guest',
        ])->assertStatus(200);
    }

    public function test_can_list_and_revoke_pending_invitations(): void
    {
        Mail::fake();
        $channel = Channel::factory()->withProfile()->create();
        $token = $this->actingAsRole($channel, 'owner');

        $this->withToken($token)->postJson("/api/channels/{$channel->id}/invitations", [
            'emails' => ['pending@example.com'],
            'role' => 'guest',
        ]);

        $list = $this->withToken($token)->getJson("/api/channels/{$channel->id}/invitations");
        $list->assertStatus(200)->assertJsonCount(1, 'data');
        $invitationId = $list->json('data.0.id');

        $this->withToken($token)->deleteJson("/api/channels/{$channel->id}/invitations/{$invitationId}")
            ->assertStatus(200);

        $this->withToken($token)->getJson("/api/channels/{$channel->id}/invitations")
            ->assertStatus(200)->assertJsonCount(0, 'data');
    }

    public function test_accept_attaches_user_with_invited_role_and_permissions(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        $plainToken = $this->createPendingInvitation($channel, 'invitee@example.com', 'redactor', ['contents.create', 'contents.publish']);
        $inviteeToken = $invitee->createToken('test')->plainTextToken;

        $response = $this->withToken($inviteeToken)->postJson("/api/channels/invitations/{$plainToken}/accept");

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseHas('channel_users', [
            'channel_id' => $channel->id,
            'user_id' => $invitee->id,
            'role' => 'redactor',
        ]);
    }

    public function test_accept_fails_when_logged_in_account_email_does_not_match(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $stranger = User::factory()->create(['email' => 'stranger@example.com']);
        $plainToken = $this->createPendingInvitation($channel, 'invitee@example.com', 'redactor');
        $strangerToken = $stranger->createToken('test')->plainTextToken;

        $this->withToken($strangerToken)->postJson("/api/channels/invitations/{$plainToken}/accept")
            ->assertStatus(422);
        $this->assertDatabaseMissing('channel_users', ['channel_id' => $channel->id, 'user_id' => $stranger->id]);
    }

    public function test_accept_fails_for_revoked_invitation(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        $plainToken = $this->createPendingInvitation($channel, 'invitee@example.com', 'guest', [], 'revoked');
        $inviteeToken = $invitee->createToken('test')->plainTextToken;

        $this->withToken($inviteeToken)->postJson("/api/channels/invitations/{$plainToken}/accept")
            ->assertStatus(422);
    }

    public function test_public_invitation_show_endpoint_does_not_require_auth(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $plainToken = $this->createPendingInvitation($channel, 'invitee@example.com', 'guest');

        $this->getJson("/api/channels/invitations/{$plainToken}")
            ->assertStatus(200)
            ->assertJsonPath('data.email', 'invitee@example.com')
            ->assertJsonPath('data.role', 'guest');
    }

    /**
     * Crée directement une invitation en base (sans passer par l'endpoint HTTP, qui
     * demanderait de s'authentifier comme owner — le guard Sanctum de test met en cache
     * le premier utilisateur résolu pour la durée du test, donc s'authentifier ensuite
     * comme un second utilisateur dans le même test ne changerait rien) et retourne le
     * token en clair correspondant, comme celui reçu par e-mail.
     */
    private function createPendingInvitation(Channel $channel, string $email, string $role, array $permissions = [], string $status = 'pending'): string
    {
        $plainToken = Str::random(64);

        ChannelInvitation::create([
            'channel_id' => $channel->id,
            'email' => $email,
            'role' => $role,
            'permissions' => $permissions,
            'token' => hash('sha256', $plainToken),
            'status' => $status,
            'expires_at' => now()->addDays(7),
        ]);

        return $plainToken;
    }
}
