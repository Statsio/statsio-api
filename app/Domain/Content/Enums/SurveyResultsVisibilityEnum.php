<?php

namespace App\Domain\Content\Enums;

/**
 * Moment où les résultats agrégés d'un sondage deviennent visibles du public.
 * Le propriétaire et les collaborateurs les voient toujours.
 */
enum SurveyResultsVisibilityEnum: string
{
    /** Dès que le répondant a envoyé sa réponse. */
    case AfterSubmit = 'after_submit';
    /** Seulement une fois la date de clôture (`response_deadline`) passée. */
    case AtClose = 'at_close';
    /** Jamais publiés. */
    case Never = 'never';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::AfterSubmit => 'Après l’envoi',
            self::AtClose => 'À la clôture',
            self::Never => 'Jamais',
        };
    }
}
