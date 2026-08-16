<?php

namespace Tests\Feature\Support;

use App\Mail\Support\ContactConfirmationMailable;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
