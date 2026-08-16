<?php

namespace App\Domain\Support\Enums;

enum ContactMessageStatusEnum: string
{
    case NEW = 'new';
    case IN_PROGRESS = 'in_progress';
    case RESOLVED = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::NEW => 'Nouveau',
            self::IN_PROGRESS => 'En cours',
            self::RESOLVED => 'Résolu',
        };
    }
}
