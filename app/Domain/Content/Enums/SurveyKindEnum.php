<?php

namespace App\Domain\Content\Enums;

/**
 * Format d'une consultation publiée comme contenu `type = survey`.
 * Repris de la maquette « Sondages Listing v2 ».
 */
enum SurveyKindEnum: string
{
    case SingleQuestion = 'single_question';
    case Long = 'long';
    case Petition = 'petition';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::SingleQuestion => 'Sondage rapide',
            self::Long => 'Questionnaire',
            self::Petition => 'Pétition',
        };
    }
}
