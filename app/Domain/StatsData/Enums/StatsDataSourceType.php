<?php

namespace App\Domain\StatsData\Enums;

enum StatsDataSourceType: string
{
    case Manual = 'manual';
    case File = 'file';
    case Api = 'api';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
