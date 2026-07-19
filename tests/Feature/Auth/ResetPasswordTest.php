<?php

namespace Tests\Feature\Auth;

use App\Models\Auth\PasswordResetToken;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ResetPasswordTest extends TestCase
{
    use RefreshDatabase;

    private function createResetTokenForUser(User $user, ?Carbon $expiresAt = null): string
    {
        $plainToken = Str::random(64);

        PasswordResetToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $plainToken),
            'expires_at' => $expiresAt ?? now()->addMinutes(60),
            'created_at' => now(),
        ]);

        return $plainToken;
    }

    public function test_valid_token_resets_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);
        $plainToken = $this->createResetTokenForUser($user);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => $plainToken,
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertTrue(Hash::check('new-password123', $user->fresh()->password));
    }

    public function test_reset_password_consumes_token(): void
    {
        $user = User::factory()->create();
        $plainToken = $this->createResetTokenForUser($user);

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => $plainToken,
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $this->assertDatabaseMissing('password_reset_tokens', ['user_id' => $user->id]);
    }

    public function test_reset_password_revokes_existing_sessions(): void
    {
        $user = User::factory()->create();
        $tokenResult = $user->createToken('test-token');
        $plainToken = $this->createResetTokenForUser($user);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => $plainToken,
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenResult->accessToken->id]);
    }

    public function test_expired_token_returns_422(): void
    {
        $user = User::factory()->create();
        $plainToken = $this->createResetTokenForUser($user, now()->subMinutes(5));

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => $plainToken,
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_wrong_token_returns_422(): void
    {
        $user = User::factory()->create();
        $this->createResetTokenForUser($user);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => 'garbage-token',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_unknown_email_returns_422(): void
    {
        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'nobody@example.com',
            'token' => 'whatever-token',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_password_confirmation_mismatch_returns_422(): void
    {
        $user = User::factory()->create();
        $plainToken = $this->createResetTokenForUser($user);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => $plainToken,
            'password' => 'new-password123',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertStatus(422);
    }

    public function test_reset_password_requires_all_fields(): void
    {
        $response = $this->postJson('/api/auth/reset-password', []);

        $response->assertStatus(422);
    }
}
