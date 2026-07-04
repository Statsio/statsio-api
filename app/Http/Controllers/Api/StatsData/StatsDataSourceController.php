<?php

namespace App\Http\Controllers\Api\StatsData;

use App\Http\Controllers\Controller;
use App\Services\DataIngestion\HttpProbeService;
use Illuminate\Http\Request;

class StatsDataSourceController extends Controller
{
    public function __construct(
        private readonly HttpProbeService $httpProbe,
    ) {}

    /**
     * Test de connexion pour une source de type API (URL + méthode + headers optionnels).
     */
    public function probeConnection(Request $request)
    {
        $validated = $request->validate([
            'url' => ['required', 'url'],
            'method' => ['sometimes', 'string', 'in:GET,POST'],
            'headers' => ['sometimes', 'array'],
        ]);

        try {
            $this->httpProbe->probe(
                url: $validated['url'],
                method: $validated['method'] ?? 'GET',
                headers: $validated['headers'] ?? [],
            );

            return response()->json([
                'success' => true,
                'message' => 'Connexion réussie',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Impossible de joindre cette URL',
            ], 422);
        }
    }
}
