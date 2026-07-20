<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Medicaments\GiygasApiClient;
use App\Services\Medicaments\MedicamentSalesService;
use App\Services\Medicaments\WikipediaImageClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MedicamentsController extends Controller
{
    public function search(Request $request, GiygasApiClient $client): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:50'],
        ]);

        return response()->json($client->search($data['q']));
    }

    public function generiques(Request $request, GiygasApiClient $client): JsonResponse
    {
        $data = $request->validate([
            'libelle' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        return response()->json($client->generiques($data['libelle']));
    }

    public function show(int $cis, GiygasApiClient $client): JsonResponse
    {
        $medicament = $client->getByCis($cis);

        if ($medicament === null) {
            return response()->json(['message' => 'Médicament introuvable.'], 404);
        }

        return response()->json($medicament);
    }

    /** Tendance des ventes (Open Medic) d'un médicament, agrégée sur tous ses CIP13. */
    public function ventes(int $cis, GiygasApiClient $giygas, MedicamentSalesService $sales): JsonResponse
    {
        $medicament = $giygas->getByCis($cis);

        if ($medicament === null) {
            return response()->json(['message' => 'Médicament introuvable.'], 404);
        }

        $cip13Codes = collect($medicament['presentation'] ?? [])->pluck('cip13')->map(fn ($cip) => (string) $cip)->all();

        $trend = $sales->getTrendForCip13Codes($cip13Codes);

        if ($trend === null) {
            return response()->json(['message' => 'Aucune donnée de ventes disponible pour ce médicament.'], 404);
        }

        return response()->json($trend);
    }

    /** Classement des médicaments les plus vendus (Open Medic), résolus vers leur fiche (CIS) quand possible. */
    public function topVentes(Request $request, MedicamentSalesService $sales, GiygasApiClient $giygas): JsonResponse
    {
        $limit = (int) $request->query('limit', 10);
        $limit = max(1, min($limit, 50));

        $top = collect($sales->getTopSoldMedicaments($limit))->map(function (array $row) use ($giygas) {
            $presentation = $giygas->getByCip($row['cip13']);

            return [
                'cip13' => $row['cip13'],
                'label' => $row['label'],
                'boxes' => $row['boxes'],
                'cis' => $presentation['cis'] ?? null,
            ];
        })->all();

        return response()->json(['results' => $top]);
    }

    public function image(Request $request, WikipediaImageClient $client): JsonResponse
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'min:2', 'max:150'],
        ]);

        return response()->json(['url' => $client->getThumbnailUrl($data['nom'])]);
    }
}
