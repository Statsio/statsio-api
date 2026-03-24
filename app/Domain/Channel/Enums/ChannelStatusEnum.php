<?php

namespace App\Domain\Channel\Enums;

enum ChannelStatusEnum: string
{
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case BANNED = 'banned';
    case ANONYMIZED = 'anonymized';

    public static function values(): array
    {
        return array_map(fn(self $c) => $c->value, self::cases());
    }
}
