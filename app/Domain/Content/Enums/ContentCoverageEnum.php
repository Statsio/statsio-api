<?php

namespace App\Domain\Content\Enums;

/**
 * Échelle de couverture géographique d'un contenu : une portée unique, du
 * mondial au local. Remplace l'ancien couple `coverage_type` / `coverage_data`
 * (continents / pays / villes). Repris de la maquette « Accueil v2 ».
 */
enum ContentCoverageEnum: string
{
    case Mondiale = 'mondiale';
    case Continentale = 'continentale';
    case Nationale = 'nationale';
    case Regionale = 'regionale';
    case Locale = 'locale';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Mondiale => 'Mondiale',
            self::Continentale => 'Continentale',
            self::Nationale => 'Nationale',
            self::Regionale => 'Régionale',
            self::Locale => 'Locale',
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
