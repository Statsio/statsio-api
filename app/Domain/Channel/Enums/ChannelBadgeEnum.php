<?php

namespace App\Domain\Channel\Enums;

enum ChannelBadgeEnum: string
{
    case VERIFIED = 'verified';

    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }
}
