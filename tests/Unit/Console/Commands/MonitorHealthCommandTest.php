<?php

namespace Tests\Unit\Console\Commands;

use App\Domain\HealthCheck\Actions\CheckHealthAction;
use App\Domain\HealthCheck\DTOs\HealthStatusDTO;
use App\Domain\HealthCheck\Enums\HealthStatus;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class MonitorHealthCommandTest extends TestCase
{
    public function test_logs_critical_and_fails_when_a_check_is_down(): void
    {
        $this->mock(CheckHealthAction::class)
            ->shouldReceive('execute')
            ->once()
            ->andReturn((new HealthStatusDTO(HealthStatus::FAIL, [
                'api' => true,
                'database' => true,
                'redis' => false,
            ]))->toArray());

        Log::shouldReceive('critical')
            ->once()
            ->withArgs(fn (string $message, array $context) => $message === 'Health check failed'
                && $context['checks']['redis'] === false);

        $this->artisan('health:monitor')->assertExitCode(1);
    }

    public function test_logs_info_and_succeeds_when_all_checks_pass(): void
    {
        $this->mock(CheckHealthAction::class)
            ->shouldReceive('execute')
            ->once()
            ->andReturn((new HealthStatusDTO(HealthStatus::OK, [
                'api' => true,
                'database' => true,
                'redis' => true,
            ]))->toArray());

        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn (string $message) => $message === 'Health check OK');

        $this->artisan('health:monitor')->assertExitCode(0);
    }
}
