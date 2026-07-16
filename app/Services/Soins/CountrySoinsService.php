<?php

namespace App\Services\Soins;

use App\Services\Pays\CountryReference;
use App\Services\Who\WhoGhoApiClient;

/**
 * Construit la section "système de santé" d'une fiche pays à partir du registre
 * TrackedSoinsCategories — logique métier isolée du contrôleur (réutilisée par
 * PaysController::show() pour ne pas y dupliquer ce calcul).
 *
 * Coût des appels GHO : un seul appel bulk caché par code indicateur utilisé dans une catégorie
 * (WhoGhoApiClient::getCountriesForIndicator, `who-gho:bulk:{code}`, partagé entre tous les pays
 * et déjà 1 jour de cache) sert à la fois à lire la valeur du pays ET à calculer son classement —
 * pas d'appel getCountryIndicator()/rankAllCountries() séparé qui redemanderait implicitement le
 * même bulk. Seule la tendance (catégories hasTrend) déclenche un appel caché supplémentaire
 * (`who-gho:trend:*`), limité à la métrique primaire de la catégorie.
 */
class CountrySoinsService
{
    public function __construct(private WhoGhoApiClient $who) {}

    /** @return array{categories: array<int, array{id: string, label: string, color: string, tint: string}>, byCategory: array<string, array{metrics: array, ranking: array, hasTrend: bool, trendTitle: ?string, trend: ?array}>} */
    public function buildCountryData(string $iso3): array
    {
        $namesByIso3 = collect(CountryReference::all())->pluck('name', 'iso3');

        $categories = [];
        $byCategory = [];

        foreach (TrackedSoinsCategories::categories() as $catId => $cat) {
            // Un seul appel bulk (caché) par code indicateur de la catégorie, quel que soit le
            // nombre de pays qui en ont besoin — valeur du pays ET classement en sont dérivés.
            $bulkByMetric = collect($cat['metrics'])->map(
                fn (array $m) => $this->who->getCountriesForIndicator($m['indicatorCode']),
            );

            $metrics = collect($cat['metrics'])->map(function (array $m, string $key) use ($iso3, $bulkByMetric) {
                $point = $bulkByMetric[$key][$iso3] ?? null;

                return [
                    'key' => $key,
                    'label' => $m['label'],
                    'unit' => $m['unit'],
                    'value' => $point ? round($point['value'] * $m['scale'], $m['decimals']) : null,
                    'sub' => $point ? "GHO OData · {$point['year']}" : 'Non disponible',
                ];
            })->values();

            // Catégorie non couverte pour ce pays (aucune métrique disponible) : on l'omet
            // plutôt que d'afficher une section entièrement vide.
            if ($metrics->every(fn (array $m) => $m['value'] === null)) {
                continue;
            }

            $primaryMeta = $cat['metrics'][$cat['primary']];
            $ranking = collect($bulkByMetric[$cat['primary']])
                ->map(fn (array $p, string $rIso3) => ['iso3' => $rIso3, 'value' => $p['value']])
                ->sortByDesc('value')
                ->take(6)
                ->values();
            $maxVal = $ranking->max('value') ?: 1;

            $ranking = $ranking->map(fn (array $r, int $i) => [
                'rank' => $i + 1,
                'iso3' => $r['iso3'],
                'name' => $namesByIso3[$r['iso3']] ?? $r['iso3'],
                'barWidth' => max(6, ($r['value'] / $maxVal) * 100).'%',
                'valueLabel' => round($r['value'] * $primaryMeta['scale'], $primaryMeta['decimals']).($cat['rankUnit'] ? ' '.$cat['rankUnit'] : ''),
            ])->values();

            $trend = null;
            if ($cat['hasTrend']) {
                $t = $this->who->getCountryTrend($iso3, $primaryMeta['indicatorCode']);
                if ($t !== null) {
                    $trend = [
                        'value' => round($t['value'] * $primaryMeta['scale'], $primaryMeta['decimals']),
                        'year' => $t['year'],
                        'trend' => collect($t['trend'])->map(fn (array $p) => [
                            'year' => $p['year'],
                            'value' => round($p['value'] * $primaryMeta['scale'], $primaryMeta['decimals']),
                        ])->all(),
                    ];
                }
            }

            $categories[] = ['id' => $catId, 'label' => $cat['label'], 'color' => $cat['color'], 'tint' => $cat['tint']];
            $byCategory[$catId] = [
                'metrics' => $metrics,
                'ranking' => $ranking,
                'hasTrend' => $cat['hasTrend'],
                'trendTitle' => $cat['trendTitle'] ?? null,
                'trend' => $trend,
            ];
        }

        return ['categories' => $categories, 'byCategory' => $byCategory];
    }
}
