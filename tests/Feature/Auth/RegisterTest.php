<?php

namespace Tests\Feature\Auth;

use App\Mail\Auth\RegistrationConfirmedMailable;
use App\Mail\Auth\VerifyEmailMailable;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private array $validPayload = [
        'first_name' => 'Alice',
        'last_name'  => 'Dupont',
        'birthday'   => '1990-05-15',
        'email'      => 'alice@example.com',
        'password'   => 'password123',
    ];

    public function test_user_can_register_with_valid_data(): void
    {
        $response = $this->postJson('/api/auth/register', $this->validPayload);

        $response->assertStatus(201)
                 ->assertJsonPath('success', true)
                 ->assertJsonStructure(['data' => ['email']]);

        $this->assertDatabaseHas('users', ['email' => 'alice@example.com']);
    }

    public function test_registration_sends_verification_and_welcome_emails(): void
    {
        Mail::fake();

        $this->postJson('/api/auth/register', $this->validPayload);

        Mail::assertSent(VerifyEmailMailable::class, fn ($mail) => $mail->hasTo('alice@example.com'));
        Mail::assertSent(RegistrationConfirmedMailable::class, fn ($mail) => $mail->hasTo('alice@example.com'));
    }

    public function test_registration_creates_user_profile(): void
    {
        $this->postJson('/api/auth/register', $this->validPayload);

        $user = User::where('email', 'alice@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->profile);
        $this->assertSame('Alice', $user->profile->first_name);
    }

    public function test_registration_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'alice@example.com']);

        $response = $this->postJson('/api/auth/register', $this->validPayload);

        $response->assertStatus(422);
    }

    public function test_registration_requires_all_fields(): void
    {
        $response = $this->postJson('/api/auth/register', []);

        $response->assertStatus(422);
    }

    public function test_registration_requires_password_min_8_chars(): void
    {
        $response = $this->postJson('/api/auth/register', array_merge($this->validPayload, [
            'password' => 'short',
        ]));

        $response->assertStatus(422);
    }

    public function test_registration_requires_turnstile_token_when_configured(): void
    {
        config(['services.turnstile.secret' => 'test-secret']);

        $response = $this->postJson('/api/auth/register', $this->validPayload);

        $response->assertStatus(422)->assertJsonValidationErrors('turnstile_token');
    }

    public function test_registration_accepts_successful_turnstile_verification(): void
    {
        config(['services.turnstile.secret' => 'test-secret']);
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response([
                'success' => true,
                'action' => 'register',
                'hostname' => parse_url((string) config('app.frontend_url'), PHP_URL_HOST),
            ]),
        ]);

        $response = $this->postJson('/api/auth/register', array_merge($this->validPayload, [
            'turnstile_token' => 'valid-token',
        ]));

        $response->assertStatus(201)->assertJsonPath('success', true);
    }
}
