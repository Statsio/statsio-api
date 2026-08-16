<?php

namespace Tests\Feature\Admin;

use App\Models\Support\ContactMessage;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminContactMessageControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    private function asAdmin()
    {
        return $this->withToken($this->admin->createToken('test')->plainTextToken);
    }

    public function test_index_returns_paginated_messages(): void
    {
        ContactMessage::factory()->count(3)->create();

        $response = $this->asAdmin()->getJson('/api/admin/contact-messages');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_index_filters_by_status(): void
    {
        ContactMessage::factory()->create(['status' => 'new']);
        ContactMessage::factory()->create(['status' => 'resolved']);

        $response = $this->asAdmin()->getJson('/api/admin/contact-messages?status=resolved');

        $this->assertCount(1, $response->json('data'));
    }

    public function test_update_changes_status(): void
    {
        $message = ContactMessage::factory()->create(['status' => 'new']);

        $this->asAdmin()->patchJson("/api/admin/contact-messages/{$message->id}", [
            'status' => 'resolved',
        ])->assertStatus(200)->assertJsonPath('data.status', 'resolved');

        $this->assertDatabaseHas('contact_messages', ['id' => $message->id, 'status' => 'resolved']);
    }

    public function test_update_rejects_invalid_status(): void
    {
        $message = ContactMessage::factory()->create();

        $this->asAdmin()->patchJson("/api/admin/contact-messages/{$message->id}", [
            'status' => 'invalide',
        ])->assertStatus(422);
    }

    public function test_destroy_deletes_message(): void
    {
        $message = ContactMessage::factory()->create();

        $this->asAdmin()->deleteJson("/api/admin/contact-messages/{$message->id}")->assertStatus(204);
        $this->assertDatabaseMissing('contact_messages', ['id' => $message->id]);
    }

    public function test_index_requires_admin(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('test')->plainTextToken)
            ->getJson('/api/admin/contact-messages')
            ->assertStatus(403);
    }
}
