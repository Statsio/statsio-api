<?php

namespace App\Http\Controllers\Api\StatsData;

use App\Domain\StatsData\Actions\RefreshNormalizedDatasetAction;
use App\Domain\StatsData\Actions\StatsDataSourceAction;
use App\Domain\StatsData\Services\StatsDataApiProbeService;
use App\Domain\StatsData\Services\StatsDataNormalizationMappingSuggestionService;
use App\Domain\StatsData\Services\StatsDataNormalizationService;
use App\Domain\StatsData\Services\StatsDataSourceParsedRootService;
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
        private StatsDataApiProbeService $apiProbe,
        private RefreshNormalizedDatasetAction $refreshNormalized,
        private StatsDataNormalizationMappingSuggestionService $mappingSuggestions,
        private StatsDataSourceParsedRootService $parsedRoot,
        private StatsDataNormalizationService $normalizer,
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

        $list->loadMissing('latestSnapshot');

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

        $source->loadMissing('latestSnapshot');

        return response()->json([
            'success' => true,
            'data' => $source->toApiArray(),
        ]);
    }

    /**
     * Suggestions de `normalizationMapping` + liste de champs détectés (pour UI visuelle).
     * Route : GET /api/statsdata/{documentId}/sources/{sourceId}/mapping-suggestions
     */
    public function mappingSuggestions(Request $request, string $documentId, string $sourceId): JsonResponse
    {
        $source = $this->sources->findOwnedSourceOrNull($request->user(), $documentId, $sourceId);

        if (! $source) {
            return response()->json([
                'success' => false,
                'message' => __('stats_data.source_not_found'),
            ], 404);
        }

        try {
            $result = $this->mappingSuggestions->suggest($source);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Impossible de suggérer un mapping.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Recherche externe "à la demande" (sans snapshot), basée sur un template URL configuré sur la source.
     * Route : POST /api/statsdata/{documentId}/sources/{sourceId}/search-external
     *
     * Corps : { q: string, limit?: int, offset?: int, columns?: [{ label, from }] }
     */
    public function searchExternal(Request $request, string $documentId, string $sourceId): JsonResponse
    {
        $source = $this->sources->findOwnedSourceOrNull($request->user(), $documentId, $sourceId);
        if (! $source) {
            return response()->json([
                'success' => false,
                'message' => __('stats_data.source_not_found'),
            ], 404);
        }

        $v = $request->validate([
            'q' => 'required|string|max:500',
            'f' => 'sometimes|nullable|string|max:255',
            'limit' => 'sometimes|integer|min:1|max:1000',
            'offset' => 'sometimes|integer|min:0|max:1000000',
            'columns' => 'sometimes|array',
            'columns.*.label' => 'required_with:columns|string|max:500',
            'columns.*.from' => 'required_with:columns|string|max:512',
        ]);

        $mapping = $source->normalization_mapping;
        if (! is_array($mapping) || $mapping === []) {
            return response()->json([
                'success' => false,
                'message' => __('stats_data.normalization_mapping_required'),
            ], 422);
        }

        $tpl = $source->api_search_template;
        if (! is_string($tpl) || trim($tpl) === '') {
            return response()->json([
                'success' => false,
                'message' => 'Aucun template de recherche configuré sur la source.',
            ], 422);
        }

        $q = trim((string) $v['q']);
        $field = isset($v['f']) && is_string($v['f']) ? trim($v['f']) : '';
        $url = str_replace('{q}', rawurlencode($q), $tpl);
        if ($field !== '') {
            $url = str_replace('{f}', rawurlencode($field), $url);
        } else {
            $url = str_replace('{f}', '', $url);
        }

        try {
            $root = $this->parsedRoot->buildFromApiUrl($source, $url);
            $limit = isset($v['limit']) ? (int) $v['limit'] : 50;
            $offset = isset($v['offset']) ? (int) $v['offset'] : 0;
            $take = $offset + $limit + 1;
            $rows = $this->normalizer->normalize($mapping, $root, $take);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Recherche externe impossible.',
            ], 422);
        }

        $flat = array_map(function (array $r): array {
            $keys = is_array($r['keys'] ?? null) ? $r['keys'] : [];
            $values = is_array($r['values'] ?? null) ? $r['values'] : [];
            return array_merge($keys, $values);
        }, $rows);

        $paged = array_slice($flat, $offset, $limit + 1);
        $hasMore = count($paged) > $limit;
        if ($hasMore) {
            $paged = array_slice($paged, 0, $limit);
        }

        $columns = $v['columns'] ?? null;
        if (is_array($columns) && $columns !== []) {
            $projected = [];
            foreach ($paged as $row) {
                $outRow = [];
                foreach ($columns as $c) {
                    if (! is_array($c)) continue;
                    $label = $c['label'] ?? null;
                    $from = $c['from'] ?? null;
                    if (! is_string($label) || $label === '' || ! is_string($from) || $from === '') continue;
                    $parts = explode('.', $from, 2);
                    $field = $parts[1] ?? '';
                    $outRow[$label] = $field !== '' ? ($row[$field] ?? null) : null;
                }
                $projected[] = $outRow;
            }
            $pagedRows = $projected;
        } else {
            $pagedRows = $paged;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'rows' => array_values($pagedRows),
                'hasMore' => $hasMore,
            ],
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

        $source->loadMissing('latestSnapshot');

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

        $source->loadMissing('latestSnapshot');

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

    public function refreshNormalized(Request $request, string $documentId, string $sourceId): JsonResponse
    {
        $snapshot = $this->refreshNormalized->refreshForUser($request->user(), $documentId, $sourceId);

        if ($snapshot === null) {
            return response()->json([
                'success' => false,
                'message' => __('stats_data.source_not_found'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => __('stats_data.snapshot_refreshed'),
            'data' => $snapshot->toSummaryApiArray(),
        ]);
    }
}
