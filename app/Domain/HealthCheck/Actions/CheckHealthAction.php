<?php
namespace App\Domain\HealthCheck\Actions;

use App\Domain\HealthCheck\DTOs\HealthStatusDTO;
use App\Domain\HealthCheck\Enums\HealthStatus;
use Illuminate\Support\Facades\DB;

class CheckHealthAction
{
    public function execute(): array
    {
        $checks = [
            'api' => true,
            'database' => $this->checkDatabase(),
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
}
?>