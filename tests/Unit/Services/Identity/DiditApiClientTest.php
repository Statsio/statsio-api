<?php

namespace Tests\Unit\Services\Identity;

use App\Domain\Identity\Exceptions\DiditException;
use App\Services\Identity\DiditApiClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DiditApiClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.didit.base_url' => 'https://verification.didit.me',
            'services.didit.api_key' => 'test-key',
            'services.didit.workflow_id' => 'wf-123',
        ]);
    }

    public function test_is_configured_requires_key_and_workflow(): void
    {
        $this->assertTrue((new DiditApiClient)->isConfigured());

        config(['services.didit.api_key' => null]);
        $this->assertFalse((new DiditApiClient)->isConfigured());
    }

    public function test_create_session_sends_api_key_and_returns_url(): void
    {
        Http::fake([
            'verification.didit.me/v3/session/' => Http::response([
                'session_id' => 'sess-1',
                'session_number' => 42,
                'url' => 'https://verify.didit.me/en/session/abc',
                'status' => 'Not Started',
            ], 201),
        ]);

        $result = (new DiditApiClient)->createSession('user-7', 'https://front/identity/callback');

        $this->assertSame('sess-1', $result['session_id']);
        $this->assertSame(42, $result['session_number']);
        $this->assertSame('https://verify.didit.me/en/session/abc', $result['url']);

        Http::assertSent(function ($request) {
            return $request->hasHeader('x-api-key', 'test-key')
                && $request['workflow_id'] === 'wf-123'
                && $request['vendor_data'] === 'user-7'
                && $request['callback'] === 'https://front/identity/callback';
        });
    }

    public function test_create_session_throws_on_error_response(): void
    {
        Http::fake(['verification.didit.me/*' => Http::response(['detail' => 'no credits'], 400)]);

        $this->expectException(DiditException::class);

        (new DiditApiClient)->createSession('user-7', 'https://front/identity/callback');
    }

    public function test_get_session_decision_returns_null_on_failure(): void
    {
        Http::fake(['verification.didit.me/*' => Http::response([], 404)]);

        $this->assertNull((new DiditApiClient)->getSessionDecision('sess-x'));
    }
}
