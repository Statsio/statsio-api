<?php

namespace Tests\Feature\Support;

use App\Mail\Support\ContactConfirmationMailable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactMessageControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_contact_message(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/contact', [
            'reason' => 'general',
            'name' => 'Jeanne Dupont',
            'email' => 'jeanne@example.com',
            'message' => 'Une question sur Statsio.',
        ]);

        $response->assertStatus(201)->assertJsonPath('success', true);

        $this->assertDatabaseHas('contact_messages', [
            'name' => 'Jeanne Dupont',
            'email' => 'jeanne@example.com',
            'reason' => 'general',
            'status' => 'new',
        ]);

        Mail::assertSent(ContactConfirmationMailable::class, fn ($mail) => $mail->hasTo('jeanne@example.com'));
    }

    public function test_store_requires_valid_reason(): void
    {
        $response = $this->postJson('/api/contact', [
            'reason' => 'invalide',
            'name' => 'Jeanne Dupont',
            'email' => 'jeanne@example.com',
            'message' => 'Une question sur Statsio.',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('reason');
    }

    public function test_store_requires_all_mandatory_fields(): void
    {
        $response = $this->postJson('/api/contact', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason', 'name', 'email', 'message']);
    }

    public function test_store_requires_turnstile_token_when_configured(): void
    {
        config(['services.turnstile.secret' => 'test-secret']);

        $response = $this->postJson('/api/contact', [
            'reason' => 'general',
            'name' => 'Jeanne Dupont',
            'email' => 'jeanne@example.com',
            'message' => 'Une question sur Statsio.',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('turnstile_token');
    }

    public function test_store_rejects_failed_turnstile_verification(): void
    {
        config(['services.turnstile.secret' => 'test-secret']);
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => false]),
        ]);

        $response = $this->postJson('/api/contact', [
            'reason' => 'general',
            'name' => 'Jeanne Dupont',
            'email' => 'jeanne@example.com',
            'message' => 'Une question sur Statsio.',
            'turnstile_token' => 'invalid-token',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('turnstile_token');
    }

    public function test_store_accepts_successful_turnstile_verification(): void
    {
        Mail::fake();
        config(['services.turnstile.secret' => 'test-secret']);
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response([
                'success' => true,
                'action' => 'contact',
                'hostname' => parse_url((string) config('app.frontend_url'), PHP_URL_HOST),
            ]),
        ]);

        $response = $this->postJson('/api/contact', [
            'reason' => 'general',
            'name' => 'Jeanne Dupont',
            'email' => 'jeanne@example.com',
            'message' => 'Une question sur Statsio.',
            'turnstile_token' => 'valid-token',
        ]);

        $response->assertStatus(201)->assertJsonPath('success', true);
    }
}
