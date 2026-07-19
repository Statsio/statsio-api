<?php

namespace Tests\Feature\Auth;

use App\Mail\Auth\ResetPasswordMailable;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_email_returns_success_and_sends_mail(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        Mail::assertSent(ResetPasswordMailable::class);

        $this->assertDatabaseHas('password_reset_tokens', [
            'user_id' => $user->id,
        ]);
    }

    public function test_unknown_email_returns_success_but_does_not_send_mail(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'nobody@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        Mail::assertNotSent(ResetPasswordMailable::class);

        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_forgot_password_requires_email(): void
    {
        $response = $this->postJson('/api/auth/forgot-password', []);

        $response->assertStatus(422);
    }

    public function test_forgot_password_rejects_invalid_email_format(): void
    {
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422);
    }

    public function test_existing_reset_token_is_replaced_on_new_request(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email]);
        $this->postJson('/api/auth/forgot-password', ['email' => $user->email]);

        $this->assertDatabaseCount('password_reset_tokens', 1);
    }

    public function test_forgot_password_respects_rate_limit(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/auth/forgot-password', ['email' => $user->email]);
        }

        $response = $this->postJson('/api/auth/forgot-password', ['email' => $user->email]);

        $response->assertStatus(429);
    }
}
