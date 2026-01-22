<?php

namespace App\Domain\User\Enums;

enum UserStatusEnum: string
{
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case BANNED = 'banned';
    case ANONYMIZED = 'anonymized';
    case DELETED = 'deleted';

    public static function values(): array
    {
        return array_map(fn(self $c) => $c->value, self::cases());
    }
}
