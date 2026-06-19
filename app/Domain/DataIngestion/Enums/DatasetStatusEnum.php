<?php

namespace App\Domain\DataIngestion\Enums;

enum DatasetStatusEnum: string
{
    case PENDING = 'pending';
    case READY = 'ready';
    case FAILED = 'failed';
}
