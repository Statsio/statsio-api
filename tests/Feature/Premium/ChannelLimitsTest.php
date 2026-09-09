<?php

namespace Tests\Feature\Premium;

use App\Models\Channel\Channel;
use App\Models\Channel\ChannelInvitation;
use App\Models\Offer;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Limites de chaînes/membres pilotées par l'offre (admin-configurable, plus aucune
 * valeur codée en dur) — voir App\Domain\Content\Support\PremiumLimits. Freemium :
 * 1 chaîne, 3 membres/chaîne ; Premium : illimité (valeurs seedées ci-dessous).
 */
class ChannelLimitsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Offer::create([
            'key' => 'freemium', 'name' => 'Freemium', 'price_cents' => 0, 'period' => 'mois',
            'cta_label' => 'Continuer', 'position' => 1,
            'max_channels' => 1, 'max_channel_members' => 3,
        ]);
        Offer::create([
            'key' => 'premium', 'name' => 'Premium', 'price_cents' => 200, 'period' => 'mois',
            'cta_label' => 'Passer à Premium', 'position' => 2,
            'max_channels' => null, 'max_channel_members' => null,
        ]);
    }

    // ─── Chaînes ────────────────────────────────────────────────────────────

    public function test_freemium_user_can_create_a_first_channel(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson('/api/channels', ['name' => 'Ma chaîne', 'handle' => 'ma_chaine'])
            ->assertStatus(201);
    }

    public function test_admin_configured_channel_limit_is_enforced_instead_of_a_hardcoded_value(): void
    {
        Offer::where('key', 'freemium')->update(['max_channels' => 2]);
        $user = User::factory()->create();
        Channel::factory()->withProfile()->create()->users()->attach($user->id, ['role' => 'owner', 'subscribed_at' => now()]);

        // 1 chaîne existante + limite admin à 2 → une 2e chaîne passe.
        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson('/api/channels', ['name' => 'Deuxième chaîne', 'handle' => 'deuxieme_chaine'])
            ->assertStatus(201);
    }

    public function test_a_null_channel_limit_means_unlimited(): void
    {
        Offer::where('key', 'freemium')->update(['max_channels' => null]);
        $user = User::factory()->create();
        Channel::factory()->withProfile()->create()->users()->attach($user->id, ['role' => 'owner', 'subscribed_at' => now()]);

        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson('/api/channels', ['name' => 'Deuxième chaîne', 'handle' => 'deuxieme_chaine'])
            ->assertStatus(201);
    }

    public function test_freemium_user_cannot_create_a_second_channel(): void
    {
        $user = User::factory()->create();
        Channel::factory()->withProfile()->create()->users()->attach($user->id, ['role' => 'owner', 'subscribed_at' => now()]);

        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson('/api/channels', ['name' => 'Deuxième chaîne', 'handle' => 'deuxieme_chaine'])
            ->assertStatus(403);
    }

    public function test_premium_user_can_create_a_second_channel(): void
    {
        $user = User::factory()->premium()->create();
        Channel::factory()->withProfile()->create()->users()->attach($user->id, ['role' => 'owner', 'subscribed_at' => now()]);

        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson('/api/channels', ['name' => 'Deuxième chaîne', 'handle' => 'deuxieme_chaine'])
            ->assertStatus(201);
    }

    // ─── Membres ────────────────────────────────────────────────────────────

    private function attachMembers(Channel $channel, int $count, string $role = 'redactor'): void
    {
        for ($i = 0; $i < $count; $i++) {
            $channel->users()->attach(
                User::factory()->create()->id,
                ['role' => $role, 'subscribed_at' => now()],
            );
        }
    }

    public function test_freemium_channel_can_invite_up_to_the_member_limit(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $channel = Channel::factory()->withProfile()->create();
        $channel->users()->attach($owner->id, ['role' => 'owner', 'subscribed_at' => now()]);
        // 1 owner + 2 invités = 3, la limite freemium.

        $this->withToken($owner->createToken('t')->plainTextToken)
            ->postJson("/api/channels/{$channel->id}/invitations", [
                'emails' => ['a@example.com', 'b@example.com'],
                'role' => 'redactor',
            ])
            ->assertStatus(200);
    }

    public function test_freemium_channel_cannot_invite_beyond_the_member_limit(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $channel = Channel::factory()->withProfile()->create();
        $channel->users()->attach($owner->id, ['role' => 'owner', 'subscribed_at' => now()]);
        $this->attachMembers($channel, 2); // owner + 2 = 3, déjà au maximum.

        $this->withToken($owner->createToken('t')->plainTextToken)
            ->postJson("/api/channels/{$channel->id}/invitations", [
                'emails' => ['nouveau@example.com'],
                'role' => 'redactor',
            ])
            ->assertStatus(403);

        $this->assertDatabaseCount('channel_invitations', 0);
    }

    public function test_premium_channel_has_no_member_limit(): void
    {
        Mail::fake();
        $owner = User::factory()->premium()->create();
        $channel = Channel::factory()->withProfile()->create();
        $channel->users()->attach($owner->id, ['role' => 'owner', 'subscribed_at' => now()]);
        $this->attachMembers($channel, 5);

        $this->withToken($owner->createToken('t')->plainTextToken)
            ->postJson("/api/channels/{$channel->id}/invitations", [
                'emails' => ['nouveau@example.com'],
                'role' => 'redactor',
            ])
            ->assertStatus(200);
    }

    public function test_accepting_an_invitation_is_blocked_once_the_freemium_channel_is_at_the_limit(): void
    {
        $owner = User::factory()->create();
        $channel = Channel::factory()->withProfile()->create();
        $channel->users()->attach($owner->id, ['role' => 'owner', 'subscribed_at' => now()]);
        $this->attachMembers($channel, 2); // owner + 2 = 3, déjà au maximum.

        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        $plainToken = Str::random(64);
        ChannelInvitation::create([
            'channel_id' => $channel->id,
            'email' => 'invitee@example.com',
            'role' => 'guest',
            'permissions' => [],
            'token' => hash('sha256', $plainToken),
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        $this->withToken($invitee->createToken('t')->plainTextToken)
            ->postJson("/api/channels/invitations/{$plainToken}/accept")
            ->assertStatus(403);

        $this->assertDatabaseMissing('channel_users', ['channel_id' => $channel->id, 'user_id' => $invitee->id]);
    }
}
