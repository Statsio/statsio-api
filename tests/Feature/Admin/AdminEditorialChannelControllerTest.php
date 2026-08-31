<?php

namespace Tests\Feature\Admin;

use App\Models\Channel\Channel;
use App\Models\User\User;
use Database\Factories\ChannelProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminEditorialChannelControllerTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        $admin = User::factory()->admin()->create();

        return $admin->createToken('test')->plainTextToken;
    }

    public function test_non_admin_cannot_list_channels(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/admin/channels')->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_list_channels(): void
    {
        $this->getJson('/api/admin/channels')->assertStatus(401);
    }

    public function test_admin_can_list_all_channels_regardless_of_status(): void
    {
        Channel::factory()->withProfile()->create(['status' => 'active']);
        Channel::factory()->withProfile()->create(['status' => 'suspended']);
        Channel::factory()->withProfile()->create(['status' => 'banned']);

        $response = $this->withToken($this->adminToken())->getJson('/api/admin/channels');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_admin_can_filter_channels_by_status(): void
    {
        Channel::factory()->withProfile()->create(['status' => 'active']);
        Channel::factory()->withProfile()->create(['status' => 'banned']);

        $response = $this->withToken($this->adminToken())->getJson('/api/admin/channels?status=banned');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('banned', $response->json('data.0.status'));
    }

    public function test_admin_can_search_channels_by_name(): void
    {
        Channel::factory()->has(ChannelProfileFactory::new()->state(['name' => 'Zorglub Channel']), 'profile')->create();
        Channel::factory()->has(ChannelProfileFactory::new()->state(['name' => 'Autre Chaine']), 'profile')->create();

        $response = $this->withToken($this->adminToken())->getJson('/api/admin/channels?search=zorglub');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_admin_can_view_channel_detail(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $owner = User::factory()->create();
        $channel->users()->attach($owner->id, ['role' => 'owner', 'subscribed_at' => now()]);

        $response = $this->withToken($this->adminToken())->getJson("/api/admin/channels/{$channel->id}");

        $response->assertStatus(200)->assertJsonPath('data.id', $channel->id);
    }

    public function test_admin_can_update_channel_profile_without_being_a_member(): void
    {
        $channel = Channel::factory()->withProfile()->create();

        $response = $this->withToken($this->adminToken())->patchJson("/api/admin/channels/{$channel->id}", [
            'name' => 'Nom corrigé par un admin',
            'custom_color_primary' => '#112233',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.profile.name', 'Nom corrigé par un admin')
            ->assertJsonPath('data.profile.custom_color_primary', '#112233');
        $this->assertSame('Nom corrigé par un admin', $channel->profile->fresh()->name);
    }

    public function test_admin_update_rejects_duplicate_handle(): void
    {
        Channel::factory()->has(ChannelProfileFactory::new()->state(['handle' => 'deja_pris']), 'profile')->create();
        $channel = Channel::factory()->withProfile()->create();

        $this->withToken($this->adminToken())->patchJson("/api/admin/channels/{$channel->id}", [
            'handle' => 'deja_pris',
        ])->assertStatus(422);
    }

    public function test_non_admin_cannot_update_channel(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $owner = User::factory()->create();
        $channel->users()->attach($owner->id, ['role' => 'owner', 'subscribed_at' => now()]);
        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)->patchJson("/api/admin/channels/{$channel->id}", [
            'name' => 'Tentative non admin',
        ])->assertStatus(403);
    }

    public function test_admin_can_suspend_ban_activate_and_anonymize_a_channel(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $token = $this->withToken($this->adminToken());

        $token->postJson("/api/admin/channels/{$channel->id}/suspend")
            ->assertStatus(200)->assertJsonPath('data.status', 'suspended');
        $this->assertNotNull($channel->fresh()->suspended_until);

        $token->postJson("/api/admin/channels/{$channel->id}/ban")
            ->assertStatus(200)->assertJsonPath('data.status', 'banned');

        $token->postJson("/api/admin/channels/{$channel->id}/activate")
            ->assertStatus(200)->assertJsonPath('data.status', 'active');

        $token->postJson("/api/admin/channels/{$channel->id}/anonymize")
            ->assertStatus(200)->assertJsonPath('data.status', 'anonymized');
        $this->assertNotNull($channel->fresh()->anonymized_at);
    }

    public function test_non_admin_cannot_suspend_a_channel(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $owner = User::factory()->create();
        $channel->users()->attach($owner->id, ['role' => 'owner', 'subscribed_at' => now()]);
        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson("/api/admin/channels/{$channel->id}/suspend")
            ->assertStatus(403);
        $this->assertSame('active', $channel->fresh()->status);
    }

    public function test_admin_can_delete_a_channel_they_do_not_own(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $owner = User::factory()->create();
        $channel->users()->attach($owner->id, ['role' => 'owner', 'subscribed_at' => now()]);

        $this->withToken($this->adminToken())->deleteJson("/api/admin/channels/{$channel->id}")
            ->assertStatus(200);
        $this->assertDatabaseMissing('channels', ['id' => $channel->id]);
    }
}
