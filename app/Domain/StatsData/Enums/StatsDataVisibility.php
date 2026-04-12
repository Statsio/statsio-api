<?php

namespace App\Domain\StatsData\Enums;

enum StatsDataVisibility: string
{
    case Private = 'private';
    case Team = 'team';
    case Public = 'public';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
