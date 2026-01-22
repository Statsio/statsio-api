<?php
namespace App\Domain\HealthCheck\DTOs;

use App\Domain\HealthCheck\Enums\HealthStatus;

class HealthStatusDTO
{
    public function __construct(
        public HealthStatus $status,
        public array $checks
    ) {}

    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'checks' => $this->checks,
        ];
    }
}
?>