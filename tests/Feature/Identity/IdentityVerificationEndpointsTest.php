<?php

namespace Tests\Feature\Identity;

use App\Models\Identity\IdentityVerification;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IdentityVerificationEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.didit.base_url' => 'https://verification.didit.me',
            'services.didit.api_key' => 'test-key',
            'services.didit.workflow_id' => 'wf-123',
            'services.didit.callback_base_url' => 'https://front.test',
        ]);
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_start_requires_authentication(): void
    {
        $this->postJson('/api/identity/verification/start')->assertStatus(401);
    }

    public function test_start_returns_503_when_didit_not_configured(): void
    {
        config(['services.didit.api_key' => null]);
        $user = User::factory()->create();

        $this->withToken($this->token($user))
            ->postJson('/api/identity/verification/start')
            ->assertStatus(503);
    }

    public function test_start_creates_session_and_returns_url(): void
    {
        Http::fake([
            'verification.didit.me/v3/session/' => Http::response([
                'session_id' => 'sess-1',
                'session_number' => 42,
                'url' => 'https://verify.didit.me/en/session/abc',
                'status' => 'Not Started',
            ], 201),
        ]);

        $user = User::factory()->create();

        $response = $this->withToken($this->token($user))
            ->postJson('/api/identity/verification/start', ['return_path' => '/sondages/mon-sondage']);

        $response->assertOk()
            ->assertJsonPath('data.url', 'https://verify.didit.me/en/session/abc')
            ->assertJsonPath('data.verified', false);

        $this->assertDatabaseHas('identity_verifications', [
            'user_id' => $user->id,
            'didit_session_id' => 'sess-1',
            'status' => 'Not Started',
        ]);

        Http::assertSent(fn ($r) => $r['callback'] === 'https://front.test/identity/callback?return=%2Fsondages%2Fmon-sondage');
    }

    public function test_start_is_idempotent_when_didit_returns_an_existing_session(): void
    {
        // Session déjà en base mais sans `session_url` : la reprise ne la voit pas et un
        // nouvel appel Didit renvoie le même `session_id` (dédup par `vendor_data`).
        $user = User::factory()->create();
        IdentityVerification::factory()->for($user)->create([
            'didit_session_id' => 'sess-1',
            'session_url' => null,
            'status' => 'In Progress',
        ]);

        Http::fake([
            'verification.didit.me/v3/session/' => Http::response([
                'session_id' => 'sess-1',
                'session_number' => 42,
                'url' => 'https://verify.didit.me/en/session/abc',
                'status' => 'In Progress',
            ], 200),
        ]);

        $this->withToken($this->token($user))
            ->postJson('/api/identity/verification/start')
            ->assertOk()
            ->assertJsonPath('data.url', 'https://verify.didit.me/en/session/abc');

        $this->assertSame(1, IdentityVerification::where('didit_session_id', 'sess-1')->count());
        $this->assertDatabaseHas('identity_verifications', [
            'didit_session_id' => 'sess-1',
            'session_url' => 'https://verify.didit.me/en/session/abc',
        ]);
    }

    public function test_start_short_circuits_when_already_verified(): void
    {
        Http::fake();
        $user = User::factory()->create(['identity_verified_at' => now()]);

        $this->withToken($this->token($user))
            ->postJson('/api/identity/verification/start')
            ->assertOk()
            ->assertJsonPath('data.verified', true)
            ->assertJsonPath('data.url', null);

        Http::assertNothingSent();
    }

    public function test_fake_mode_approves_immediately_without_network(): void
    {
        config(['services.didit.api_key' => null, 'services.didit.workflow_id' => null, 'services.didit.fake' => true]);
        Http::fake();
        $user = User::factory()->create();

        $this->withToken($this->token($user))
            ->postJson('/api/identity/verification/start')
            ->assertOk()
            ->assertJsonPath('data.verified', true)
            ->assertJsonPath('data.status', 'Approved');

        Http::assertNothingSent();
        $this->assertNotNull($user->fresh()->identity_verified_at);
        $this->assertDatabaseHas('identity_verifications', ['user_id' => $user->id, 'status' => 'Approved']);
    }

    public function test_status_reports_verified_state(): void
    {
        $user = User::factory()->create(['identity_verified_at' => now()]);
        IdentityVerification::factory()->approved()->for($user)->create();

        $this->withToken($this->token($user))
            ->getJson('/api/identity/verification/status')
            ->assertOk()
            ->assertJsonPath('data.verified', true)
            ->assertJsonPath('data.status', 'Approved');
    }
}
