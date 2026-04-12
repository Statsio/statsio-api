<?php

namespace App\Http\Controllers\Api\StatsData;

use App\Domain\StatsData\Actions\StatsDataSourceAction;
use App\Domain\StatsData\Services\StatsDataApiProbeService;
use App\Http\Controllers\Controller;
use App\Http\Requests\StatsData\ProbeStatsDataApiRequest;
use App\Http\Requests\StatsData\StoreStatsDataSourceRequest;
use App\Http\Requests\StatsData\UpdateStatsDataSourceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StatsDataSourceController extends Controller
{
    public function __construct(
        private StatsDataSourceAction $sources,
        private StatsDataApiProbeService $apiProbe
    ) {}

    /**
     * Test de connexion pour une source de données de type API (URL + clé Bearer optionnelle).
     * Route : POST /api/source-api/probe-connection (hors ressource statsdata).
     */
    public function probeConnection(ProbeStatsDataApiRequest $request): JsonResponse
    {
        $v = $request->validated();
        $result = $this->apiProbe->probe($v['url'], $v['apiKey'] ?? null);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    public function index(Request $request, string $documentId): JsonResponse
    {
        $list = $this->sources->listForUser($request->user(), $documentId);
        if ($list === null) {
            return response()->json([
                'success' => false,
                'message' => __('stats_data.not_found'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $list->map(fn ($s) => $s->toApiArray())->values(),
        ]);
    }

    public function show(Request $request, string $documentId, string $sourceId): JsonResponse
    {
        $source = $this->sources->findOwnedSourceOrNull($request->user(), $documentId, $sourceId);

        if (! $source) {
            return response()->json([
                'success' => false,
                'message' => __('stats_data.source_not_found'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $source->toApiArray(),
        ]);
    }

    public function store(StoreStatsDataSourceRequest $request, string $documentId): JsonResponse
    {
        $source = $this->sources->createForUser(
            $request->user(),
            $documentId,
            $request->normalizedPayload(),
            $request->file('file')
        );

        if ($source === null) {
            return response()->json([
                'success' => false,
                'message' => __('stats_data.not_found'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => __('stats_data.source_created'),
            'data' => $source->toApiArray(),
        ], 201);
    }

    public function update(UpdateStatsDataSourceRequest $request, string $documentId, string $sourceId): JsonResponse
    {
        $source = $this->sources->updateForUser(
            $request->user(),
            $documentId,
            $sourceId,
            $request->normalizedPayload(),
            $request->file('file')
        );

        if ($source === null) {
            return response()->json([
                'success' => false,
                'message' => __('stats_data.source_not_found'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => __('stats_data.source_updated'),
            'data' => $source->toApiArray(),
        ]);
    }

    public function destroy(Request $request, string $documentId, string $sourceId): Response|JsonResponse
    {
        $ok = $this->sources->deleteForUser($request->user(), $documentId, $sourceId);

        if (! $ok) {
            return response()->json([
                'success' => false,
                'message' => __('stats_data.source_not_found'),
            ], 404);
        }

        return response()->noContent();
    }

    public function probe(ProbeStatsDataApiRequest $request, string $documentId): JsonResponse
    {
        $v = $request->validated();
        $result = $this->sources->probeForUser(
            $request->user(),
            $documentId,
            $v['url'],
            $v['apiKey'] ?? null
        );

        if ($result === null) {
            return response()->json([
                'success' => false,
                'message' => __('stats_data.not_found'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
}
