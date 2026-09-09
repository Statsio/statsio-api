<?php

namespace App\Domain\Content\Enums;

/**
 * Offre à laquelle un utilisateur est rattaché. Bascule manuelle depuis le
 * back-office (pas d'intégration de paiement) — voir `User::isPremium()` pour
 * la prise en compte de `premium_until`.
 */
enum PremiumPlanEnum: string
{
    case Free = 'free';
    case Premium = 'premium';

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Freemium',
            self::Premium => 'Premium',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }

    /** @return array<string, string> value => label (pour les selects Filament). */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label()])
            ->all();
    }
}
