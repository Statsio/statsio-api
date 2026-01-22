<?php

namespace App\Domain\User\Enums;

enum SocioProfessionalCategoryEnum: string
{
    case MANAGER = 'manager';
    case PROFESSIONAL = 'professional';
    case TECHNICIAN = 'technician';
    case CLERICAL = 'clerical';
    case SERVICE = 'service';
    case SKILLED_WORKER = 'skilled_worker';
    case UNSKILLED_WORKER = 'unskilled_worker';
    case SELF_EMPLOYED = 'self_employed';
    case STUDENT = 'student';
    case RETIRED = 'retired';
    case UNEMPLOYED = 'unemployed';
    case OTHER = 'other';

    public static function values(): array
    {
        return array_map(fn(self $c) => $c->value, self::cases());
    }
}
