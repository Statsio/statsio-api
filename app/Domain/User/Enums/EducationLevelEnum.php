<?php

namespace App\Domain\User\Enums;

enum EducationLevelEnum: string
{
    case NO_SCHOOLING = 'no_schooling';
    case PRIMARY = 'primary';
    case SECONDARY = 'secondary';
    case BACHELOR = 'bachelor';
    case MASTER = 'master';
    case DOCTORATE = 'doctorate';
    case OTHER = 'other';

    public static function values(): array
    {
        return array_map(fn(self $c) => $c->value, self::cases());
    }
}
