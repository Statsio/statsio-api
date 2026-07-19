<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Maladies\Icd11ApiClient;
use App\Services\Maladies\TrackedDiseases;
use App\Services\Maladies\UmlsApiClient;
use App\Services\Pays\CountryReference;
use App\Services\Who\WhoGhoApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaladiesController extends Controller
{
    public function search(Request $request, Icd11ApiClient $client): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $results = collect($client->search($data['q'], 'fr'))
            ->map(fn (array $entity) => ['id' => $entity['id'], 'name' => $entity['title']])
            ->values();

        return response()->json($results);
    }

    /** Grille "Maladies suivies" de la page liste — voir TrackedDiseases. */
    public function populaires(Icd11ApiClient $client, WhoGhoApiClient $who): JsonResponse
    {
        $results = collect(TrackedDiseases::ids())
            ->map(fn (string $id) => $this->summarize($id, $client, $who))
            ->filter()
            ->values();

        return response()->json($results);
    }

    public function show(string $id, Icd11ApiClient $client, WhoGhoApiClient $who, UmlsApiClient $umls): JsonResponse
    {
        $entity = $client->getById($id, 'fr');

        if ($entity === null) {
            return response()->json(['message' => 'Maladie introuvable.'], 404);
        }

        $block = collect($entity['parentIds'])->first();
        $blockTitle = $block ? $client->getTitle($block, 'fr') : null;
        $chapter = $block ? collect($client->getById($block, 'fr')['parentIds'] ?? [])->first() : null;
        $chapterTitle = $chapter ? $client->getTitle($chapter, 'fr') : null;

        $indicatorCode = TrackedDiseases::indicatorFor($id);
        $stats = null;
        $topCountries = [];

        if ($indicatorCode !== null) {
            $trend = $who->getGlobalTrend($indicatorCode);
            if ($trend !== null) {
                $stats = [
                    'value' => $trend['value'],
                    'year' => $trend['year'],
                    'trend' => $trend['trend'],
                    'source' => 'WHO GHO',
                    'indicatorCode' => $indicatorCode,
                ];
            }

            $topCountries = collect($who->getTopCountries($indicatorCode, 5))
                ->map(function (array $row) {
                    $country = CountryReference::find($row['iso3']);

                    return $country === null ? null : [
                        'iso3' => $row['iso3'],
                        'iso2' => $country['iso2'],
                        'name' => $country['name'],
                        'lat' => $country['lat'],
                        'lon' => $country['lon'],
                        'value' => $row['value'],
                        'year' => $row['year'],
                    ];
                })
                ->filter()
                ->values()
                ->all();
        }

        $enrichment = $entity['code'] !== null ? $umls->getSymptomsAndRiskFactors($entity['code']) : null;

        return response()->json([
            'id' => $entity['id'],
            'code' => $entity['code'],
            'name' => $entity['title'],
            'definition' => $entity['definition'],
            'synonyms' => $entity['synonyms'],
            'inclusions' => $entity['inclusions'],
            'classKind' => $entity['classKind'],
            'chapter' => $chapterTitle,
            'block' => $blockTitle,
            'childIds' => $entity['childIds'],
            'classificationSource' => ['source' => 'ICD-11', 'releaseId' => $client->currentReleaseId()],
            'stats' => $stats,
            'indicatorUnit' => TrackedDiseases::unitFor($id),
            'topCountries' => $topCountries,
            'symptoms' => $enrichment['symptoms'] ?? null,
            'riskFactors' => $enrichment['riskFactors'] ?? null,
        ]);
    }

    private function summarize(string $id, Icd11ApiClient $client, WhoGhoApiClient $who): ?array
    {
        $entity = $client->getById($id, 'fr');
        if ($entity === null) {
            return null;
        }

        $indicatorCode = TrackedDiseases::indicatorFor($id);
        $trend = $indicatorCode !== null ? $who->getGlobalTrend($indicatorCode) : null;

        $evolution = null;
        if ($trend !== null && count($trend['trend']) >= 2) {
            $first = $trend['trend'][0]['value'];
            $last = $trend['trend'][count($trend['trend']) - 1]['value'];
            $evolution = $first !== 0.0 ? round((($last - $first) / abs($first)) * 100, 1) : null;
        }

        $block = collect($entity['parentIds'])->first();

        return [
            'id' => $id,
            'code' => $entity['code'],
            'name' => $entity['title'],
            'category' => $block ? $client->getTitle($block, 'fr') : null,
            'value' => $trend['value'] ?? null,
            'year' => $trend['year'] ?? null,
            'evolutionPercent' => $evolution,
            'trend' => $trend['trend'] ?? [],
        ];
    }
}
