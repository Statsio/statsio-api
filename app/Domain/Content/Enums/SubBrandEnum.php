<?php

namespace App\Domain\Content\Enums;

/**
 * Sous-marque Statsio à laquelle un objet éditorial (dossier, catégorie de
 * contenu…) est rattaché : il n'est proposé que sur le site correspondant.
 * `All` = visible sur toutes les marques (Statsio, TVStats, Medistats).
 */
enum SubBrandEnum: string
{
    case All = 'all';
    case Statsio = 'statsio';
    case Tvstats = 'tvstats';
    case Medistats = 'medistats';

    public function label(): string
    {
        return match ($this) {
            self::All => 'Toutes les marques',
            self::Statsio => 'Statsio',
            self::Tvstats => 'TVStats',
            self::Medistats => 'Medistats',
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

    /**
     * Sous-marques concrètes qu'un contenu ou une chaîne peut choisir côté
     * utilisateur (`All` exclu : c'est une classification back-office).
     *
     * @return list<self>
     */
    public static function contentCases(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $c) => $c !== self::All));
    }

    /** @return list<string> */
    public static function contentValues(): array
    {
        return array_map(static fn (self $case) => $case->value, self::contentCases());
    }

    /** @return array<string, string> value => label (selects utilisateur / Filament StudioContent). */
    public static function contentOptions(): array
    {
        return collect(self::contentCases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label()])
            ->all();
    }

    /**
     * Normalise une entrée de requête publique vers une sous-marque concrète
     * (`statsio|tvstats|medistats`) pour cadrer un listing. `null` = pas de filtre.
     */
    public static function sanitize(mixed $raw): ?string
    {
        return is_string($raw) && in_array($raw, self::contentValues(), true) ? $raw : null;
    }
}
