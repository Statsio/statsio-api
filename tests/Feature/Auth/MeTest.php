<?php

namespace Tests\Feature\Auth;

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_get_their_info(): void
    {
        $user = User::factory()->create();
        $user->profile()->create(['first_name' => 'Bob', 'last_name' => 'Martin']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/auth/me');

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.user.email', $user->email);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401);
    }

    public function test_response_includes_profile(): void
    {
        $user = User::factory()->create();
        $user->profile()->create(['first_name' => 'Claire', 'last_name' => 'Moreau']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/auth/me');

        $response->assertStatus(200)
                 ->assertJsonPath('data.user.profile.first_name', 'Claire');
    }
}
