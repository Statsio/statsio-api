<?php

namespace Tests\Feature\User;

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_get_profile(): void
    {
        $user = User::factory()->create();
        $user->profile()->create(['first_name' => 'Luc', 'last_name' => 'Bernard']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/me');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', $user->email);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/me')->assertStatus(401);
    }

    public function test_user_can_update_profile(): void
    {
        $user = User::factory()->create();
        $user->profile()->create(['first_name' => 'Old', 'last_name' => 'Name']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->putJson('/api/me', [
            'first_name' => 'Nouveau',
            'last_name' => 'Prénom',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->id,
            'first_name' => 'Nouveau',
        ]);
    }

    public function test_user_can_update_profile_without_existing_profile(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->putJson('/api/me', [
            'first_name' => 'Sophie',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->id,
            'first_name' => 'Sophie',
        ]);
    }

    public function test_user_can_update_notification_preferences(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->patchJson('/api/me', [
            'notification_preferences' => [
                'articles' => false,
                'weekly' => true,
                'replies' => true,
                'offers' => false,
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.user.profile.notification_preferences.articles', false)
            ->assertJsonPath('data.user.profile.notification_preferences.replies', true);
    }

    public function test_me_returns_default_notification_preferences(): void
    {
        $user = User::factory()->create();
        $user->profile()->create(['first_name' => 'Marie']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/me')
            ->assertStatus(200)
            ->assertJsonPath('data.user.profile.notification_preferences.articles', true)
            ->assertJsonPath('data.user.profile.notification_preferences.offers', false);
    }

    public function test_user_can_anonymize_account(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/account/anonymize');

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => 'anonymized',
        ]);
    }
}
