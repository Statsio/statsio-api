<?php

namespace App\Domain\HealthCheck\Actions;

use App\Domain\HealthCheck\DTOs\HealthStatusDTO;
use App\Domain\HealthCheck\Enums\HealthStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class CheckHealthAction
{
    public function execute(): array
    {
        $checks = [
            'api' => true,
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
        ];

        $status = in_array(false, $checks, true)
            ? HealthStatus::FAIL
            : HealthStatus::OK;

        return (new HealthStatusDTO($status, $checks))->toArray();
    }

    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkRedis(): bool
    {
        try {
            return Redis::connection()->ping() !== false;
        } catch (\Throwable) {
            return false;
        }
    }
}
