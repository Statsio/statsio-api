<?php

namespace App\Domain\Content\Enums;

enum StudioContentAccessLevelEnum: string
{
    case None = 'none';
    case Read = 'read';
    case Write = 'write';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Aucun',
            self::Read => 'Lecture',
            self::Write => 'Modification',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $l) => $l->value, self::cases());
    }

    public function allows(self $needed): bool
    {
        if ($needed === self::None) {
            return true;
        }

        return match ($this) {
            self::None => false,
            self::Read => $needed === self::Read,
            self::Write => true,
        };
    }
}
