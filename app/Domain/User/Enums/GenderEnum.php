<?php

namespace App\Domain\User\Enums;

enum GenderEnum: string
{
    case MALE = 'male';
    case FEMALE = 'female';
    case NON_BINARY = 'non_binary';
    case OTHER = 'other';
    case PREFER_NOT_TO_SAY = 'prefer_not_to_say';

    public static function values(): array
    {
        return array_map(fn(self $c) => $c->value, self::cases());
    }
}
