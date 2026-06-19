<?php

namespace App\Domain\DataIngestion\Enums;

enum ColumnTypeEnum: string
{
    case STRING = 'string';
    case INTEGER = 'integer';
    case FLOAT = 'float';
    case BOOLEAN = 'boolean';
    case DATE = 'date';
    case DATETIME = 'datetime';

    public function isNumeric(): bool
    {
        return match ($this) {
            self::INTEGER, self::FLOAT => true,
            default => false,
        };
    }

    public function isTemporal(): bool
    {
        return match ($this) {
            self::DATE, self::DATETIME => true,
            default => false,
        };
    }
}
