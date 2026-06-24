<?php

namespace Tests\Feature\Channel;

use App\Models\Channel\Channel;
use App\Models\Channel\ChannelCategory;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelControllerTest extends TestCase
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

    public function test_can_list_channels(): void
    {
        Channel::factory()->withProfile()->count(3)->create();

        $response = $this->getJson('/api/channels');

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonStructure(['data']);
    }

    public function test_can_list_channel_categories(): void
    {
        $response = $this->getJson('/api/channels/categories');

        $response->assertStatus(200)
                 ->assertJsonPath('success', true);
    }

    public function test_authenticated_user_can_create_channel(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/channels', [
            'name'        => 'Mon Canal Test',
            'handle'      => 'mon_canal_test',
            'description' => 'Description du canal',
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('success', true);

        $this->assertDatabaseCount('channels', 1);
    }

    public function test_unauthenticated_user_cannot_create_channel(): void
    {
        $response = $this->postJson('/api/channels', [
            'name'   => 'Canal',
            'handle' => 'canal',
        ]);

        $response->assertStatus(401);
    }

    public function test_can_show_existing_channel(): void
    {
        $channel = Channel::factory()->withProfile()->create();

        $response = $this->getJson("/api/channels/{$channel->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('success', true);
    }

    public function test_show_returns_404_for_unknown_channel(): void
    {
        $response = $this->getJson('/api/channels/99999');

        $response->assertStatus(404);
    }

    public function test_authenticated_user_can_list_their_channels(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/channels/my');

        $response->assertStatus(200)
                 ->assertJsonPath('success', true);
    }

    public function test_authenticated_user_can_delete_own_channel(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $channel->users()->attach($this->user->id, ['role' => 'owner', 'subscribed_at' => now()]);

        $response = $this->withToken($this->token)->deleteJson("/api/channels/{$channel->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('channels', ['id' => $channel->id]);
    }

    public function test_delete_returns_404_for_unknown_channel(): void
    {
        $response = $this->withToken($this->token)->deleteJson('/api/channels/99999');

        $response->assertStatus(404);
    }

    public function test_can_list_channel_members(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $channel->users()->attach($this->user->id, ['role' => 'owner', 'subscribed_at' => now()]);

        $response = $this->withToken($this->token)->getJson("/api/channels/{$channel->id}/members");

        $response->assertStatus(200)
                 ->assertJsonPath('success', true);
    }

    public function test_can_check_handle_availability(): void
    {
        $response = $this->getJson('/api/channels/check-handle/my-unique-handle');

        $response->assertStatus(200);
    }
}
