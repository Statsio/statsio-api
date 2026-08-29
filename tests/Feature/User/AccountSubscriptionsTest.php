<?php

namespace Tests\Feature\User;

use App\Models\Channel\Channel;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountSubscriptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_subscribed_channels(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $channel = Channel::factory()->withProfile()->create();
        $channel->users()->attach($user->id, [
            'role' => 'subscriber',
            'subscribed_at' => now(),
        ]);

        $other = Channel::factory()->withProfile()->create();
        $other->users()->attach($user->id, ['role' => 'subscriber', 'subscribed_at' => null]);

        $response = $this->withToken($token)->getJson('/api/me/subscriptions')->assertOk();

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $channel->id);
    }

    public function test_me_exposes_subscriptions_count(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $channel = Channel::factory()->withProfile()->create();
        $channel->users()->attach($user->id, ['role' => 'subscriber', 'subscribed_at' => now()]);

        $this->withToken($token)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.user.counts.subscriptions', 1);
    }
}
