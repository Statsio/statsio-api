<?php

namespace App\Domain\Channel\Enums;

enum ChannelKindEnum: string
{
    case REDACTION = 'redaction';
    case INSTITUTION = 'institution';
    case INDEPENDANT = 'independant';

    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::REDACTION => 'Rédaction',
            self::INSTITUTION => 'Institution',
            self::INDEPENDANT => 'Indépendant',
        };
    }
}
