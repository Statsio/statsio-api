<?php

namespace Tests\Feature\Auth;

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonStructure(['data' => ['token', 'refresh_token', 'type', 'expires_in']]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct')]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong',
        ]);

        $response->assertStatus(401)
                 ->assertJsonPath('success', false);
    }

    public function test_login_fails_with_unknown_email(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertStatus(422)
                 ->assertJsonPath('success', false);
    }

    public function test_login_rejects_invalid_email_format(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'not-an-email',
            'password' => 'password',
        ]);

        $response->assertStatus(422);
    }
}
