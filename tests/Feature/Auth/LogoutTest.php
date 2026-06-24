<?php

namespace Tests\Feature\Auth;

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();
        $tokenResult = $user->createToken('test-token');

        $response = $this->withToken($tokenResult->plainTextToken)
                         ->postJson('/api/auth/logout');

        $response->assertStatus(200);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(401);
    }

    public function test_token_is_revoked_after_logout(): void
    {
        $user = User::factory()->create();
        $tokenResult = $user->createToken('test-token');
        $tokenId = $tokenResult->accessToken->id;

        $this->withToken($tokenResult->plainTextToken)->postJson('/api/auth/logout');

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    }
}
