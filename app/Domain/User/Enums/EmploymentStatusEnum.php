<?php

namespace App\Domain\User\Enums;

enum EmploymentStatusEnum: string
{
    case EMPLOYED = 'employed';
    case SELF_EMPLOYED = 'self_employed';
    case UNEMPLOYED = 'unemployed';
    case STUDENT = 'student';
    case RETIRED = 'retired';
    case INACTIVE = 'inactive';
    case OTHER = 'other';

    public static function values(): array
    {
        return array_map(fn(self $c) => $c->value, self::cases());
    }
}
