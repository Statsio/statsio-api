<?php

namespace App\Domain\DataIngestion\Enums;

use Carbon\CarbonImmutable;

enum DataSourceRefreshFrequencyEnum: string
{
    case NONE = 'none';
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
    case YEARLY = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::NONE => 'Manuelle',
            self::DAILY => 'Quotidienne',
            self::WEEKLY => 'Hebdomadaire',
            self::MONTHLY => 'Mensuelle',
            self::YEARLY => 'Annuelle',
        };
    }

    public function nextOccurrenceFrom(CarbonImmutable $from): ?CarbonImmutable
    {
        return match ($this) {
            self::NONE => null,
            self::DAILY => $from->addDay(),
            self::WEEKLY => $from->addWeek(),
            self::MONTHLY => $from->addMonth(),
            self::YEARLY => $from->addYear(),
        };
    }
}
