<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Pays\CountryReference;
use App\Services\Who\WhoGhoApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SoinsController extends Controller
{
    /** Indicateurs proposés sur la carte Soins et les tuiles de la grille de pays (GHO, vérifiés). */
    private const INDICATORS = [
        'physicians' => ['code' => 'HWF_0001', 'label' => 'Médecins /1000', 'unit' => '', 'scale' => 0.1],
        'bedsPer1000' => ['code' => 'WHS6_102', 'label' => 'Lits /1000', 'unit' => '', 'scale' => 0.1],
        'uhcIndex' => ['code' => 'UHC_INDEX_REPORTED', 'label' => 'Couverture UHC', 'unit' => '/100', 'scale' => 1],
        'healthExpGDP' => ['code' => 'GHED_CHEGDP_SHA2011', 'label' => 'Dépenses santé % PIB', 'unit' => '%', 'scale' => 1],
    ];

    public function index(Request $request, WhoGhoApiClient $who): JsonResponse
    {
        $data = $request->validate([
            'indicator' => ['nullable', 'string', 'in:'.implode(',', array_keys(self::INDICATORS))],
        ]);

        $key = $data['indicator'] ?? 'physicians';
        $config = self::INDICATORS[$key];

        // Un seul appel bulk (caché) par indicateur, quel que soit le nombre de pays : permet
        // d'afficher médecins/lits/etc. sur chaque carte, indépendamment de l'indicateur
        // sélectionné pour la couleur de la carte ($key/$config).
        $statsByIndicator = collect(self::INDICATORS)->map(fn (array $c) => [
            'config' => $c,
            'values' => $who->getCountriesForIndicator($c['code']),
        ]);

        $countries = collect(CountryReference::all())->map(function (array $country) use ($key, $statsByIndicator) {
            $stats = $statsByIndicator->map(function (array $entry) use ($country) {
                $p = $entry['values'][$country['iso3']] ?? null;

                return [
                    'label' => $entry['config']['label'],
                    'unit' => $entry['config']['unit'],
                    'value' => $p ? round($p['value'] * $entry['config']['scale'], 1) : null,
                    'year' => $p['year'] ?? null,
                ];
            });

            return [
                'iso3' => $country['iso3'],
                'iso2' => $country['iso2'],
                'name' => $country['name'],
                'region' => $country['region'],
                'lat' => $country['lat'],
                'lon' => $country['lon'],
                'population' => $country['population'],
                'value' => $stats[$key]['value'],
                'year' => $stats[$key]['year'],
                'stats' => $stats,
            ];
        })->sortByDesc('population')->values();

        return response()->json([
            'indicator' => [
                'key' => $key,
                'label' => $config['label'],
                'unit' => $config['unit'],
                'source' => 'WHO GHO',
                'indicatorCode' => $config['code'],
            ],
            'options' => collect(self::INDICATORS)->map(fn (array $c, string $k) => ['key' => $k, 'label' => $c['label']])->values(),
            'countries' => $countries,
        ]);
    }
}
