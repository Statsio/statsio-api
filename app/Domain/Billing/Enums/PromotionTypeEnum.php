<?php

namespace App\Domain\Billing\Enums;

enum PromotionTypeEnum: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'Pourcentage',
            self::Fixed => 'Montant fixe',
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
