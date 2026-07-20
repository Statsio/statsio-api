<?php

namespace App\Domain\User\Enums;

enum MaritalStatusEnum: string
{
    case SINGLE = 'single';
    case IN_RELATIONSHIP = 'in_relationship';
    case MARRIED = 'married';
    case CIVIL_UNION = 'civil_union';
    case DIVORCED = 'divorced';
    case WIDOWED = 'widowed';
    case OTHER = 'other';

    public static function values(): array
    {
        return array_map(fn(self $c) => $c->value, self::cases());
    }
}
