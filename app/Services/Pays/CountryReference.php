<?php

namespace App\Services\Pays;

/**
 * Référentiel géographique statique (ISO3, nom FR, région, centroïde lon/lat, population) des
 * ~194 États membres ONU/OMS — voir resources/data/who-countries.php. Donnée de référence
 * géographique, pas sanitaire : chargée une fois en mémoire (require), jamais appelée en direct.
 */
class CountryReference
{
    /** @var array<string, array{iso3: string, iso2: string, name: string, region: string, lat: float, lon: float, population: int}>|null */
    private static ?array $byIso3 = null;

    /** @return array{iso3: string, iso2: string, name: string, region: string, lat: float, lon: float, population: int}[] */
    public static function all(): array
    {
        return array_values(self::indexed());
    }

    public static function find(string $iso3): ?array
    {
        return self::indexed()[$iso3] ?? null;
    }

    /** @return array<string, array{iso3: string, iso2: string, name: string, region: string, lat: float, lon: float, population: int}> */
    private static function indexed(): array
    {
        if (self::$byIso3 === null) {
            $rows = require base_path('resources/data/who-countries.php');
            self::$byIso3 = collect($rows)->keyBy('iso3')->all();
        }

        return self::$byIso3;
    }
}
