<?php

namespace App\Domain\Billing\Enums;

/** Miroir du champ `duration` d'un Coupon Stripe. */
enum PromotionDurationEnum: string
{
    case Once = 'once';
    case Repeating = 'repeating';
    case Forever = 'forever';

    public function label(): string
    {
        return match ($this) {
            self::Once => 'Une seule fois (1er paiement)',
            self::Repeating => 'Pendant N mois',
            self::Forever => 'Tant que l\'abonnement dure',
        };
    }

    /** @return array<string, string> value => label (pour les selects Filament). */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label()])
            ->all();
    }
}
