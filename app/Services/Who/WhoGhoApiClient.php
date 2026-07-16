<?php

namespace App\Services\Who;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Relais vers l'API publique WHO Global Health Observatory (GHO OData), sans clé. Partagé par
 * les deux fonctionnalités MédiStats qui ont besoin de statistiques chiffrées par pays/indicateur :
 * les maladies (prévalence/taux mondial + pays les plus touchés) et les pays (espérance de vie,
 * dépenses de santé, densité médicale, mortalité <5 ans, + maladies principales du pays).
 * Couverture partielle par construction — l'OMS ne suit que les indicateurs de santé publique
 * qu'elle publie, pas l'intégralité des codes ICD-11. Pas de données inventées pour le reste :
 * toute méthode ici dégrade vers null en cas d'échec réseau ou d'indicateur non couvert.
 */
class WhoGhoApiClient
{
    /**
     * Un seul appel HTTP par indicateur (mis en cache 1 jour), quel que soit le nombre de pays
     * ou de maladies qui en ont besoin ensuite : brique commune à getTopCountries(), à la carte
     * Pays (coloration par indicateur) et au classement "maladies principales" d'un pays. Ajouter
     * un pays ou une maladie suivie ne multiplie donc jamais les appels GHO, seulement les lectures
     * de cache.
     *
     * @return array<string, array{value: float, year: int}> ISO3 => dernière valeur connue
     */
    public function getCountriesForIndicator(string $indicatorCode): array
    {
        try {
            $rows = Cache::remember(
                "who-gho:bulk:{$indicatorCode}",
                now()->addDay(),
                fn () => $this->client()
                    ->get("/{$indicatorCode}", ['$filter' => "SpatialDimType eq 'COUNTRY'"])
                    ->throw()
                    ->json('value') ?? [],
            );
        } catch (ConnectionException|RequestException) {
            return [];
        }

        return $this->preferBothSexes(collect($rows)->filter(
            fn (array $row) => is_numeric($row['NumericValue'] ?? null) && ! empty($row['SpatialDim']),
        ))
            ->groupBy('SpatialDim')
            ->map(function ($countryRows) {
                $primaryDim2 = $countryRows->countBy(fn (array $row) => $row['Dim2'] ?? '')->sortDesc()->keys()->first();
                $latest = $countryRows
                    ->filter(fn (array $row) => ($row['Dim2'] ?? '') === $primaryDim2)
                    ->sortBy('TimeDim')
                    ->last();

                return [
                    'value' => round((float) $latest['NumericValue'], 1),
                    'year' => (int) $latest['TimeDim'],
                ];
            })
            ->all();
    }

    /** @return array{iso3: string, value: float, year: int}[] Triés desc, les `$limit` premiers. */
    public function getTopCountries(string $indicatorCode, int $limit = 5): array
    {
        return collect($this->getCountriesForIndicator($indicatorCode))
            ->map(fn (array $row, string $iso3) => ['iso3' => $iso3, ...$row])
            ->sortByDesc('value')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Position (1 = le plus élevé) et centile d'un pays donné parmi tous les pays reportant cet
     * indicateur — utilisé pour classer les maladies suivies d'un pays entre elles malgré des
     * unités différentes (prévalence %, taux/100k, cas bruts...), voir PaysController::show().
     *
     * @return array{rank: int, total: int, percentile: float}|null null si le pays n'est pas couvert
     */
    public function rankCountry(string $iso3, string $indicatorCode): ?array
    {
        return $this->rankAllCountries($indicatorCode)[$iso3] ?? null;
    }

    /**
     * Classement de tous les pays reportant un indicateur, calculé en une passe — utilisé pour
     * trouver la maladie la plus préoccupante de chaque pays sur la liste Pays sans faire un
     * rankCountry() (donc un tri complet) par pays.
     *
     * @return array<string, array{rank: int, total: int, percentile: float}> ISO3 => classement
     */
    public function rankAllCountries(string $indicatorCode): array
    {
        $sorted = collect($this->getCountriesForIndicator($indicatorCode))->sortByDesc('value')->keys()->values();
        $total = $sorted->count();

        if ($total === 0) {
            return [];
        }

        return $sorted->mapWithKeys(fn (string $iso3, int $position) => [
            $iso3 => [
                'rank' => $position + 1,
                'total' => $total,
                'percentile' => round((($position + 1) / $total) * 100, 1),
            ],
        ])->all();
    }

    /**
     * @return array{value: float, year: int, trend: array<array{year: int, value: float}>}|null
     */
    public function getGlobalTrend(string $indicatorCode): ?array
    {
        return $this->trendFor($indicatorCode, "SpatialDim eq 'GLOBAL'", "who-gho:global:{$indicatorCode}");
    }

    /** @return array{value: float, year: int}|null */
    public function getCountryIndicator(string $iso3, string $indicatorCode): ?array
    {
        $all = $this->getCountriesForIndicator($indicatorCode);

        return $all[$iso3] ?? null;
    }

    /**
     * @return array{value: float, year: int, trend: array<array{year: int, value: float}>}|null
     */
    public function getCountryTrend(string $iso3, string $indicatorCode): ?array
    {
        return $this->trendFor(
            $indicatorCode,
            "SpatialDim eq '{$iso3}'",
            "who-gho:trend:{$indicatorCode}:{$iso3}",
        );
    }

    /**
     * @return array{value: float, year: int, trend: array<array{year: int, value: float}>}|null
     */
    private function trendFor(string $indicatorCode, string $filter, string $cacheKey): ?array
    {
        try {
            $rows = Cache::remember(
                $cacheKey,
                now()->addDay(),
                fn () => $this->client()
                    ->get("/{$indicatorCode}", ['$filter' => $filter])
                    ->throw()
                    ->json('value') ?? [],
            );
        } catch (ConnectionException|RequestException) {
            return null;
        }

        $rows = $this->preferBothSexes(
            collect($rows)->filter(fn (array $row) => is_numeric($row['NumericValue'] ?? null)),
        );

        if ($rows->isEmpty()) {
            return null;
        }

        // Certains indicateurs publient plusieurs séries en parallèle (ex. tranches d'âge
        // différentes) : on ne garde que la série la plus fournie pour éviter des doublons/an.
        $primaryDim2 = $rows->countBy(fn (array $row) => $row['Dim2'] ?? '')->sortDesc()->keys()->first();
        $series = $rows->filter(fn (array $row) => ($row['Dim2'] ?? '') === $primaryDim2)->sortBy('TimeDim')->values();

        $latest = $series->last();
        $trend = $series->slice(-8)->map(fn (array $row) => [
            'year' => (int) $row['TimeDim'],
            'value' => round((float) $row['NumericValue'], 1),
        ])->values()->all();

        return [
            'value' => round((float) $latest['NumericValue'], 1),
            'year' => (int) $latest['TimeDim'],
            'trend' => $trend,
        ];
    }

    /**
     * Ne garde que les lignes "les deux sexes confondus" (Dim1 = SEX_BTSX ou, pour la plupart
     * des indicateurs non ventilés par sexe — TB, paludisme, VIH, rougeole — Dim1 = null), pour
     * éviter de mélanger des séries hommes/femmes distinctes dans le même classement. Si un
     * indicateur ne publie vraiment que du hommes/femmes (aucune des deux valeurs présente), on
     * garde tout plutôt que de tout jeter : mieux vaut une série mixte qu'aucune donnée.
     */
    private function preferBothSexes(Collection $rows): Collection
    {
        $bothSexes = $rows->filter(fn (array $row) => in_array($row['Dim1'] ?? null, ['SEX_BTSX', null], true));

        return $bothSexes->isNotEmpty() ? $bothSexes : $rows;
    }

    private function client()
    {
        return Http::baseUrl(config('services.who_gho_api.base_url'))->timeout(10);
    }
}
