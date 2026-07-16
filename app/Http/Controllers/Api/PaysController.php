<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Maladies\Icd11ApiClient;
use App\Services\Maladies\TrackedDiseases;
use App\Services\Pays\CountryReference;
use App\Services\Who\WhoGhoApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PaysController extends Controller
{
    /** Indicateurs proposés sur la carte Pays et les tuiles de la fiche pays (GHO, vérifiés). */
    private const INDICATORS = [
        'lifeExp' => ['code' => 'WHOSIS_000001', 'label' => 'Espérance de vie', 'unit' => ' ans', 'scale' => 1],
        'healthExp' => ['code' => 'GHED_CHEGDP_SHA2011', 'label' => 'Dépenses de santé', 'unit' => '% PIB', 'scale' => 1],
        // GHO publie les médecins pour 10 000 habitants ; ×0.1 pour l'affichage "pour 1000".
        'physicians' => ['code' => 'HWF_0001', 'label' => 'Médecins /1000', 'unit' => '', 'scale' => 0.1],
        'u5mort' => ['code' => 'MDG_0000000007', 'label' => 'Mortalité <5 ans', 'unit' => '‰', 'scale' => 1],
    ];

    public function index(Request $request, WhoGhoApiClient $who, Icd11ApiClient $icd11): JsonResponse
    {
        $data = $request->validate([
            'indicator' => ['nullable', 'string', 'in:'.implode(',', array_keys(self::INDICATORS))],
        ]);

        $key = $data['indicator'] ?? 'lifeExp';
        $config = self::INDICATORS[$key];

        // Un seul appel bulk (mis en cache) par indicateur pays, quel que soit le nombre de pays :
        // permet d'afficher espérance de vie/médecins/etc. sur chaque carte, indépendamment de
        // l'indicateur sélectionné pour la couleur de la carte ($key/$config).
        $statsByIndicator = collect(self::INDICATORS)->map(fn (array $c) => [
            'config' => $c,
            'values' => $who->getCountriesForIndicator($c['code']),
        ]);

        // Idem pour les maladies suivies : un classement bulk par maladie (au lieu d'un
        // rankCountry() par pays × maladie), puis on prend la moins bonne place de chaque pays.
        $diseaseRankings = collect(TrackedDiseases::trackedIndicators())
            ->map(fn (string $indicatorCode) => $who->rankAllCountries($indicatorCode));
        $diseaseTitles = collect(TrackedDiseases::trackedIndicators())
            ->map(fn (string $indicatorCode, string $diseaseId) => $icd11->getById($diseaseId, 'fr'));

        $countries = collect(CountryReference::all())->map(function (array $country) use ($key, $statsByIndicator, $diseaseRankings, $diseaseTitles) {
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
                'topDisease' => $this->topDiseaseFor($country['iso3'], $diseaseRankings, $diseaseTitles),
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

    /**
     * Maladie suivie la plus préoccupante pour un pays : celle où son classement (percentile)
     * parmi les pays reportant l'indicateur est le plus mauvais. Même logique que le "top 5" de
     * show(), mais à partir des classements bulk déjà calculés pour tous les pays d'un coup.
     *
     * @param  Collection<string, array<string, array{rank: int, total: int, percentile: float}>>  $diseaseRankings
     * @param  Collection<string, array{code: ?string, title: ?string}|null>  $diseaseTitles
     * @return array{id: string, code: ?string, name: ?string, percentile: float}|null
     */
    private function topDiseaseFor(string $iso3, Collection $diseaseRankings, Collection $diseaseTitles): ?array
    {
        $best = $diseaseRankings
            ->map(fn (array $rankings, string $diseaseId) => isset($rankings[$iso3])
                ? ['id' => $diseaseId, 'percentile' => $rankings[$iso3]['percentile']]
                : null)
            ->filter()
            ->sortBy('percentile')
            ->first();

        if ($best === null) {
            return null;
        }

        $entity = $diseaseTitles[$best['id']] ?? null;
        if ($entity === null) {
            return null;
        }

        return [
            'id' => $best['id'],
            'code' => $entity['code'],
            'name' => $entity['title'],
            'percentile' => $best['percentile'],
        ];
    }

    public function show(string $iso3, WhoGhoApiClient $who, Icd11ApiClient $icd11): JsonResponse
    {
        $country = CountryReference::find($iso3);

        if ($country === null) {
            return response()->json(['message' => 'Pays introuvable.'], 404);
        }

        $tiles = collect(self::INDICATORS)->map(function (array $config, string $key) use ($iso3, $who) {
            $point = $who->getCountryIndicator($iso3, $config['code']);

            return [
                'key' => $key,
                'label' => $config['label'],
                'unit' => $config['unit'],
                'value' => $point ? round($point['value'] * $config['scale'], 1) : null,
                'year' => $point['year'] ?? null,
                'source' => 'WHO GHO',
                'indicatorCode' => $config['code'],
            ];
        })->values();

        $lifeExpTrend = $who->getCountryTrend($iso3, self::INDICATORS['lifeExp']['code']);

        $topDiseases = collect(TrackedDiseases::trackedIndicators())
            ->map(function (string $indicatorCode, string $diseaseId) use ($iso3, $who, $icd11) {
                $ranking = $who->rankCountry($iso3, $indicatorCode);
                if ($ranking === null) {
                    return null;
                }

                $entity = $icd11->getById($diseaseId, 'fr');
                if ($entity === null) {
                    return null;
                }

                return [
                    'id' => $diseaseId,
                    'code' => $entity['code'],
                    'name' => $entity['title'],
                    'rank' => $ranking['rank'],
                    'total' => $ranking['total'],
                    'percentile' => $ranking['percentile'],
                    'source' => 'WHO GHO',
                    'indicatorCode' => $indicatorCode,
                ];
            })
            ->filter()
            ->sortBy('percentile')
            ->take(5)
            ->values();

        return response()->json([
            'iso3' => $country['iso3'],
            'name' => $country['name'],
            'region' => $country['region'],
            'tiles' => $tiles,
            'lifeExpectancyTrend' => $lifeExpTrend,
            'topDiseases' => $topDiseases,
        ]);
    }
}
