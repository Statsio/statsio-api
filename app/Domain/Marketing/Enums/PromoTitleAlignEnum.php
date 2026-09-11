<?php

namespace App\Domain\Marketing\Enums;

enum PromoTitleAlignEnum: string
{
    /** Escalier : démarre au milieu de la ligne précédente. */
    case Stagger = 'stagger';
    case Left = 'left';
    case Center = 'center';

    public function label(): string
    {
        return match ($this) {
            self::Stagger => 'Quinconce (milieu de la ligne du dessus)',
            self::Left => 'Aligné à gauche',
            self::Center => 'Centré',
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
