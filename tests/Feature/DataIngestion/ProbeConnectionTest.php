<?php

namespace Tests\Feature\DataIngestion;

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProbeConnectionTest extends TestCase
{
    use RefreshDatabase;

    private function authToken(): string
    {
        $user = User::factory()->create();

        return $user->createToken('test')->plainTextToken;
    }

    public function test_probe_connection_requires_authentication(): void
    {
        $this->postJson('/api/source-api/probe-connection', ['url' => 'https://example.com'])
            ->assertStatus(401);
    }

    public function test_probe_connection_requires_valid_url(): void
    {
        $this->withToken($this->authToken())
            ->postJson('/api/source-api/probe-connection', ['url' => 'not-a-url'])
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_probe_connection_returns_success_when_endpoint_reachable(): void
    {
        Http::fake(['example.com/*' => Http::response(['ok' => true])]);

        $this->withToken($this->authToken())
            ->postJson('/api/source-api/probe-connection', ['url' => 'https://example.com/data'])
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_probe_connection_returns_422_when_endpoint_fails(): void
    {
        Http::fake(['example.com/*' => Http::response([], 500)]);

        $this->withToken($this->authToken())
            ->postJson('/api/source-api/probe-connection', ['url' => 'https://example.com/data'])
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }
}
