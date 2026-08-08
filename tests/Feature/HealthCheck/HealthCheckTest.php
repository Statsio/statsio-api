<?php

namespace Tests\Feature\HealthCheck;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_check_returns_ok_status(): void
    {
        Redis::shouldReceive('connection')->once()->andReturnSelf();
        Redis::shouldReceive('ping')->once()->andReturn(true);

        $response = $this->getJson('/api/healthcheck');

        $response->assertStatus(200)
                 ->assertJsonPath('status', 'ok')
                 ->assertJsonPath('checks.api', true)
                 ->assertJsonPath('checks.database', true)
                 ->assertJsonPath('checks.redis', true);
    }

    public function test_health_check_returns_fail_status_when_redis_is_unreachable(): void
    {
        Redis::shouldReceive('connection')->once()->andThrow(new \RuntimeException('Connection refused'));

        $response = $this->getJson('/api/healthcheck');

        $response->assertStatus(200)
                 ->assertJsonPath('status', 'fail')
                 ->assertJsonPath('checks.database', true)
                 ->assertJsonPath('checks.redis', false);
    }
}
