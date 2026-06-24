<?php

namespace Tests\Feature\Auth;

use App\Domain\Auth\Actions\IssueAuthTokensAction;
use App\Models\Auth\RefreshToken;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RefreshTokenTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithRefreshToken(User $user): string
    {
        $plainToken = Str::random(80);

        $accessToken = $user->createToken('api-token', ['*'], now()->addMinutes(15));

        RefreshToken::create([
            'user_id' => $user->id,
            'personal_access_token_id' => $accessToken->accessToken->id,
            'token' => hash('sha256', $plainToken),
            'expires_at' => now()->addDays(30),
        ]);

        return $plainToken;
    }

    public function test_valid_refresh_token_issues_new_tokens(): void
    {
        $user = User::factory()->create();
        $plainToken = $this->createUserWithRefreshToken($user);

        $response = $this->postJson('/api/auth/refresh', [
            'refresh_token' => $plainToken,
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['data' => ['token', 'refresh_token']]);
    }

    public function test_invalid_refresh_token_returns_401(): void
    {
        $response = $this->postJson('/api/auth/refresh', [
            'refresh_token' => 'invalid-token',
        ]);

        $response->assertStatus(401);
    }

    public function test_expired_refresh_token_returns_401(): void
    {
        $user = User::factory()->create();
        $plainToken = Str::random(80);

        $accessToken = $user->createToken('api-token');

        RefreshToken::create([
            'user_id' => $user->id,
            'personal_access_token_id' => $accessToken->accessToken->id,
            'token' => hash('sha256', $plainToken),
            'expires_at' => now()->subDays(1),
        ]);

        $response = $this->postJson('/api/auth/refresh', [
            'refresh_token' => $plainToken,
        ]);

        $response->assertStatus(401);
    }

    public function test_revoked_refresh_token_returns_401(): void
    {
        $user = User::factory()->create();
        $plainToken = Str::random(80);

        $accessToken = $user->createToken('api-token');

        RefreshToken::create([
            'user_id' => $user->id,
            'personal_access_token_id' => $accessToken->accessToken->id,
            'token' => hash('sha256', $plainToken),
            'expires_at' => now()->addDays(30),
            'revoked_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/refresh', [
            'refresh_token' => $plainToken,
        ]);

        $response->assertStatus(401);
    }
}
