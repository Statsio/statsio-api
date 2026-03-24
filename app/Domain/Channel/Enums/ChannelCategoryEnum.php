<?php

namespace App\Domain\Channel\Enums;

enum ChannelCategoryEnum: string
{
    case SPORT = 'sport';
    case ACTUALITE = 'actualite';
    case ACTUS_MEDIAS = 'actus_medias';
    case ACTUS_PEOPLE = 'actus_people';
    case EDITOS = 'editos';
    case SCIENCE = 'science';
    case TECHNOLOGIE = 'technologie';
    case CULTURE = 'culture';
    case ECONOMIE = 'economie';
    case POLITIQUE = 'politique';

    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }
}
