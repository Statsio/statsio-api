<?php

namespace Tests\Feature\Channel;

use App\Models\Channel\Channel;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_owners_admins_and_moderators_relations(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $moderator = User::factory()->create();

        $channel->users()->attach($owner->id, ['role' => 'owner', 'subscribed_at' => now()]);
        $channel->users()->attach($admin->id, ['role' => 'admin', 'subscribed_at' => now()]);
        $channel->users()->attach($moderator->id, ['role' => 'moderator', 'subscribed_at' => now()]);

        $this->assertSame([$owner->id], $channel->owners()->pluck('users.id')->all());
        $this->assertSame([$admin->id], $channel->admins()->pluck('users.id')->all());
        $this->assertSame([$moderator->id], $channel->moderators()->pluck('users.id')->all());
        $this->assertCount(3, $channel->managementTeam()->get());
    }

    public function test_get_subscriber_count_and_is_user_subscribed(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $subscriber = User::factory()->create();
        $notSubscribed = User::factory()->create();

        $channel->users()->attach($subscriber->id, ['role' => 'subscriber', 'subscribed_at' => now()]);
        $channel->users()->attach($notSubscribed->id, ['role' => 'subscriber', 'subscribed_at' => null]);

        $this->assertSame(1, $channel->getSubscriberCount());
        $this->assertTrue($channel->isUserSubscribed($subscriber));
        $this->assertFalse($channel->isUserSubscribed($notSubscribed));
    }

    public function test_is_user_banned_and_banned_users_relation(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $banned = User::factory()->create();
        $notBanned = User::factory()->create();

        $channel->users()->attach($banned->id, [
            'role' => 'subscriber', 'subscribed_at' => now(),
            'is_banned' => true, 'banned_until' => null,
        ]);
        $channel->users()->attach($notBanned->id, [
            'role' => 'subscriber', 'subscribed_at' => now(),
            'is_banned' => false,
        ]);

        $this->assertTrue($channel->isUserBanned($banned));
        $this->assertFalse($channel->isUserBanned($notBanned));
        $this->assertSame([$banned->id], $channel->bannedUsers()->pluck('users.id')->all());
    }

    public function test_is_user_banned_respects_expired_ban(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $user = User::factory()->create();

        $channel->users()->attach($user->id, [
            'role' => 'subscriber', 'subscribed_at' => now(),
            'is_banned' => true, 'banned_until' => now()->subDay(),
        ]);

        $this->assertFalse($channel->isUserBanned($user));
    }

    public function test_get_user_role_returns_null_when_not_a_member(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $user = User::factory()->create();

        $this->assertNull($channel->getUserRole($user));

        $channel->users()->attach($user->id, ['role' => 'admin', 'subscribed_at' => now()]);

        $this->assertSame('admin', $channel->fresh()->getUserRole($user));
    }

    public function test_get_or_create_profile_creates_profile_when_missing(): void
    {
        $channel = Channel::factory()->create();

        $this->assertNull($channel->profile);

        $profile = $channel->getOrCreateProfile(['name' => 'Nom par défaut', 'handle' => 'nom-par-defaut']);

        $this->assertSame('Nom par défaut', $profile->name);
        $this->assertSame($channel->id, $profile->channel_id);
    }

    public function test_get_or_create_profile_returns_existing_profile(): void
    {
        $channel = Channel::factory()->withProfile()->create();

        $profile = $channel->getOrCreateProfile(['name' => 'Ignoré', 'handle' => 'ignore']);

        $this->assertSame($channel->profile->id, $profile->id);
        $this->assertNotSame('Ignoré', $profile->name);
    }
}
