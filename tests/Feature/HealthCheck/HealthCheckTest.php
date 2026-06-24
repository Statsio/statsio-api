<?php

namespace Tests\Feature\HealthCheck;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_check_returns_ok_status(): void
    {
        $response = $this->getJson('/api/healthcheck');

        $response->assertStatus(200)
                 ->assertJsonPath('status', 'ok')
                 ->assertJsonPath('checks.api', true)
                 ->assertJsonPath('checks.database', true);
    }
}
