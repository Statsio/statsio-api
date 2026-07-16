<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Medicaments\GiygasApiClient;
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
}
