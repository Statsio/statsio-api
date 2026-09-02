<?php

namespace App\Http\Controllers\Api\DataIngestion;

use App\Http\Controllers\Controller;
use App\Services\DataIngestion\DataGouv\DataGouvCatalogClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Exploration du catalogue data.gouv.fr depuis le wizard « Ajouter une source ».
 * Sert de proxy vers l'API publique data.gouv : évite les limites de débit côté
 * client, centralise le cache et la vérification de disponibilité tabular-api.
 */
class DataGouvController extends Controller
{
    public function __construct(private readonly DataGouvCatalogClient $catalog) {}

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:150'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        try {
            $result = $this->catalog->searchDatasets(
                $validated['q'],
                (int) ($validated['page'] ?? 1),
            );
        } catch (RequestException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Le catalogue data.gouv.fr est momentanément indisponible.',
            ], 502);
        }

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ref' => ['required', 'string', 'max:500'],
        ]);

        try {
            $dataset = $this->catalog->getDataset($validated['ref']);
        } catch (RequestException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Le catalogue data.gouv.fr est momentanément indisponible.',
            ], 502);
        }

        if ($dataset === null) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun jeu de données data.gouv.fr ne correspond à cette référence.',
            ], 404);
        }

        return response()->json(['success' => true, 'data' => $dataset]);
    }
}
