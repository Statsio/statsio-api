<?php

namespace App\Domain\DataIngestion\Enums;

enum DataSourceStatusEnum: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case READY = 'ready';
    case FAILED = 'failed';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::READY, self::FAILED => true,
            default => false,
        };
    }
}
