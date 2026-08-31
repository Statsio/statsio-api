<?php

namespace Tests\Feature\Identity;

use App\Models\Identity\IdentityVerification;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiditWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec-test';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.didit.webhook_secret' => self::SECRET]);
    }

    private function postWebhook(array $payload, ?string $signature = null, ?string $timestamp = null)
    {
        $raw = json_encode($payload);
        $timestamp ??= (string) time();
        $signature ??= hash_hmac('sha256', $raw, self::SECRET);

        return $this->call('POST', '/api/identity/verification/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_SIGNATURE' => $signature,
            'HTTP_X_TIMESTAMP' => $timestamp,
        ], $raw);
    }

    public function test_valid_approved_webhook_marks_user_verified(): void
    {
        $user = User::factory()->create();
        $verification = IdentityVerification::factory()->for($user)->create(['didit_session_id' => 'sess-42']);

        $this->postWebhook(['session_id' => 'sess-42', 'status' => 'Approved'])
            ->assertOk()
            ->assertJson(['received' => true]);

        $verification->refresh();
        $this->assertSame('Approved', $verification->status->value);
        $this->assertNotNull($verification->verified_at);
        $this->assertNotNull($user->fresh()->identity_verified_at);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        IdentityVerification::factory()->create(['didit_session_id' => 'sess-42']);

        $this->postWebhook(['session_id' => 'sess-42', 'status' => 'Approved'], signature: 'deadbeef')
            ->assertStatus(401);

        $this->assertDatabaseMissing('identity_verifications', ['status' => 'Approved']);
    }

    public function test_stale_timestamp_is_rejected(): void
    {
        IdentityVerification::factory()->create(['didit_session_id' => 'sess-42']);

        $this->postWebhook(['session_id' => 'sess-42', 'status' => 'Approved'], timestamp: (string) (time() - 4000))
            ->assertStatus(401);
    }

    public function test_replaying_the_same_event_is_idempotent(): void
    {
        $user = User::factory()->create();
        IdentityVerification::factory()->for($user)->create(['didit_session_id' => 'sess-42']);

        $this->postWebhook(['session_id' => 'sess-42', 'status' => 'Approved'])->assertOk();
        $firstVerifiedAt = $user->fresh()->identity_verified_at;

        $this->travel(5)->minutes();
        $this->postWebhook(['session_id' => 'sess-42', 'status' => 'Approved'])->assertOk();

        $this->assertEquals($firstVerifiedAt, $user->fresh()->identity_verified_at);
    }

    public function test_unknown_session_is_ignored_gracefully(): void
    {
        $this->postWebhook(['session_id' => 'does-not-exist', 'status' => 'Approved'])->assertOk();
    }
}
