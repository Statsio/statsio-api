<?php

namespace App\Http\Controllers\Api\DataIngestion;

use App\Domain\DataIngestion\Exceptions\InvalidQueryGraphException;
use App\Domain\DataIngestion\Exceptions\LiveApiQueryException;
use App\Domain\DataIngestion\Exceptions\UnsupportedLiveQueryOperationException;
use App\Domain\DataIngestion\Query\QueryGraph;
use App\Domain\DataIngestion\Query\QueryResult;
use App\Http\Controllers\Controller;
use App\Models\DataIngestion\Dataset;
use App\Models\DataIngestion\DatasetVersion;
use App\Models\StudioContent;
use App\Services\DataIngestion\LiveQuery\LiveDatasetQueryService;
use App\Services\DataIngestion\NumericValueParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class DatasetController extends Controller
{
    /**
     * TTL for cached parquet query/preview/distinct-values responses.
     * Cache keys embed the dataset version checksum, so a re-ingested dataset
     * busts its cache automatically — the TTL only bounds worst-case staleness.
     */
    private const CACHE_TTL = 900; // 15 minutes

    /**
     * Colonnes calculées de la requête courante (combinaisons arithmétiques injectées
     * dans la projection avant agrégation). Portée requête — le contrôleur est résolu
     * à chaque requête. Réf `calc:<id>`.
     *
     * @var array<int, array{id: string, operands: array<int, array{op?: string, column?: string, value?: float}>}>
     */
    private array $calcSpecs = [];

    public function __construct(
        private readonly LiveDatasetQueryService $liveQueryService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $datasets = Dataset::with('dataSource')
            ->where('user_id', $userId)
            ->orWhereHas('dataSource.users', fn ($q) => $q->where('user_id', $userId))
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $datasets->map(fn ($dataset) => $this->formatDataset($dataset, $userId)),
            'meta' => [
                'total' => $datasets->total(),
                'per_page' => $datasets->perPage(),
                'current_page' => $datasets->currentPage(),
                'last_page' => $datasets->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, Dataset $dataset): JsonResponse
    {
        if (! $dataset->isAccessibleBy($request->user()->id)) {
            return response()->json(['success' => false, 'message' => 'Non autorisé.'], 403);
        }

        $dataset->load(['columns', 'versions', 'dataSource']);

        return response()->json([
            'success' => true,
            'data' => $this->formatDatasetFull($dataset, $request->user()->id),
        ]);
    }

    public function preview(Request $request, Dataset $dataset): JsonResponse
    {
        if (! $dataset->isAccessibleBy($request->user()->id)) {
            return response()->json(['success' => false, 'message' => 'Non autorisé.'], 403);
        }

        $limit = min((int) $request->query('limit', 5), 100);

        try {
            $result = $this->resolveRows($dataset, null, [], $limit);
        } catch (UnsupportedLiveQueryOperationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'code' => 'unsupported_live_operation'], 422);
        } catch (LiveApiQueryException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->httpStatus());
        }

        return response()->json([
            'success' => true,
            'data' => ['columns' => $result->columns, 'rows' => $result->rows, 'total' => $result->total],
        ]);
    }

    public function query(Request $request, Dataset $dataset): JsonResponse
    {
        $userId = $request->user()->id;

        if (! $dataset->isAccessibleBy($userId)) {
            return response()->json(['success' => false, 'message' => 'Non autorisé.'], 403);
        }

        try {
            $p = $this->parseQueryParams($request, $dataset->id);

            if ($p['distinct'] && count($p['columns']) === 1) {
                return $this->distinctResponse($dataset, $p['columns'][0], $p['limit'], (string) $request->query('search', ''), $p['filters'], $p['graph'], $userId);
            }

            if ($p['facet'] && count($p['columns']) === 1) {
                return $this->facetResponse($dataset, $p['columns'][0], $p['facetLimit'], $p['facetOffset'], (string) $request->query('search', ''), $p['filters'], $p['graph'], $userId);
            }

            $result = $this->resolveRows(
                $dataset,
                count($p['columns']) ? $p['columns'] : null,
                $p['filters'],
                $p['limit'],
                $p['joins'],
                $userId,
                $p['searchQ'],
                $p['searchCols'],
                $p['distinctColumn'],
                $p['sortColumn'],
                $p['sortDirection'],
                $p['aggregate'],
                $p['aggregateColumns'],
                $p['groupBy'],
                $p['offset'],
                $p['aggregateSpecs'],
                $p['graph'],
            );
        } catch (InvalidQueryGraphException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'code' => 'invalid_query_graph'], 422);
        } catch (UnsupportedLiveQueryOperationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'code' => 'unsupported_live_operation'], 422);
        } catch (LiveApiQueryException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->httpStatus());
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatQueryResult($result, $p['columns']),
        ]);
    }

    public function queryPublic(Request $request, string $slug, Dataset $dataset): JsonResponse
    {
        $content = StudioContent::where(function ($q) use ($slug) {
            $q->where('slug', $slug);
            if (is_numeric($slug)) {
                $q->orWhere('id', (int) $slug);
            }
        })->firstOrFail();

        // Publié = lisible par tous ; brouillon = seulement pour son éditeur
        // (aperçu d'un bloc `sd-embed` pointant son propre Statsdata dans le Studio).
        if ($content->status !== 'published') {
            $viewer = $request->user('sanctum');
            abort_unless($viewer !== null && $viewer->can('update', $content), 404);
        }

        $docDatasetIds = collect($this->collectContentBlocks($content))
            ->flatMap(function ($block) {
                $ids = [$block['datasetId'] ?? null];
                foreach ($block['sources'] ?? [] as $source) {
                    $ids[] = $source['datasetId'] ?? null;
                }
                foreach ($block['joins'] ?? [] as $join) {
                    $ids[] = $join['datasetId'] ?? null;
                }
                foreach ($block['fieldMapping']['searchSources'] ?? [] as $source) {
                    $ids[] = $source['datasetId'] ?? null;
                }
                foreach ($block['fieldMapping']['searchJoins'] ?? [] as $j) {
                    $ids[] = $j['datasetId'] ?? null;
                }

                return $ids;
            })
            ->filter()
            ->unique()
            ->values()
            ->map(fn ($id) => (string) $id)
            ->toArray();

        if (! in_array((string) $dataset->id, $docDatasetIds, true)) {
            return response()->json(['success' => false, 'message' => 'Dataset non autorisé.'], 403);
        }

        try {
            $p = $this->parseQueryParams($request, $dataset->id);

            if ($p['distinct'] && count($p['columns']) === 1) {
                return $this->distinctResponse($dataset, $p['columns'][0], $p['limit'], (string) $request->query('search', ''), $p['filters'], $p['graph'], $content->user_id);
            }

            if ($p['facet'] && count($p['columns']) === 1) {
                return $this->facetResponse($dataset, $p['columns'][0], $p['facetLimit'], $p['facetOffset'], (string) $request->query('search', ''), $p['filters'], $p['graph'], $content->user_id);
            }

            $result = $this->resolveRows(
                $dataset,
                count($p['columns']) ? $p['columns'] : null,
                $p['filters'],
                $p['limit'],
                $p['joins'],
                $content->user_id,
                $p['searchQ'],
                $p['searchCols'],
                $p['distinctColumn'],
                $p['sortColumn'],
                $p['sortDirection'],
                $p['aggregate'],
                $p['aggregateColumns'],
                $p['groupBy'],
                $p['offset'],
                $p['aggregateSpecs'],
                $p['graph'],
            );
        } catch (InvalidQueryGraphException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'code' => 'invalid_query_graph'], 422);
        } catch (UnsupportedLiveQueryOperationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'code' => 'unsupported_live_operation'], 422);
        } catch (LiveApiQueryException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->httpStatus());
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatQueryResult($result, $p['columns']),
        ]);
    }

    /**
     * Blocs d'un contenu — racine + pages + sections — pour l'autorisation des
     * datasets référencés (superset : n'élargit qu'aux datasets réellement cités).
     *
     * @return array<int, array<string, mixed>>
     */
    private function collectContentBlocks(StudioContent $content): array
    {
        $groups = [
            $content->blocks ?? [],
            ...array_map(fn ($page) => $page['blocks'] ?? [], $content->pages ?? []),
            ...array_map(fn ($section) => $section['blocks'] ?? [], $content->sections ?? []),
        ];

        $blocks = [];
        foreach ($groups as $group) {
            if (is_array($group)) {
                foreach ($group as $block) {
                    if (is_array($block)) {
                        $blocks[] = $block;
                    }
                }
            }
        }

        return $blocks;
    }

    /**
     * Met en forme la charge `data` d'une réponse query()/queryPublic().
     *
     * @param  array<int, string>  $requestedColumns
     * @return array<string, mixed>
     */
    private function formatQueryResult(QueryResult $result, array $requestedColumns): array
    {
        $finalColumns = count($requestedColumns)
            ? array_values(array_intersect($result->columns, $this->resolvedKeysFor($requestedColumns, $result->columnMap)))
            : $result->columns;

        $data = [
            'columns' => $finalColumns,
            'rows' => $result->rows,
            'total_rows' => $result->total,
        ];

        if ($result->columnMap !== []) {
            $data['column_map'] = $result->columnMap;
        }

        return $data;
    }

    /**
     * @param  array<int, string>  $refs
     * @param  array<string, string>  $columnMap
     * @return array<int, string>
     */
    private function resolvedKeysFor(array $refs, array $columnMap): array
    {
        return array_values(array_unique(array_map(fn ($ref) => $columnMap[$ref] ?? $ref, $refs)));
    }

    private const AGG_FUNCTIONS = ['sum', 'avg', 'count', 'min', 'max'];

    /**
     * Parses and validates the query params shared by query() and queryPublic().
     *
     * @return array{limit: int, offset: int, columns: array<string>, filters: array, joins: array, searchQ: string, searchCols: array<string>, distinct: bool, facet: bool, facetLimit: int, facetOffset: int, distinctColumn: ?string, sortColumn: ?string, sortDirection: string, aggregate: ?string, aggregateColumns: array<string>, groupBy: array<string>, aggregateSpecs: array<int, array{column: string, fn: string}>, graph: QueryGraph}
     */
    private function parseQueryParams(Request $request, int $primaryDatasetId): array
    {
        $limit = min((int) $request->query('limit', 500), 5000);
        $offset = max((int) $request->query('offset', 0), 0);
        $columns = $request->query('columns', []);
        $filters = $request->query('filters', []);
        $joins = $request->query('joins', []);
        $sources = $request->query('sources', []);
        $searchQ = (string) $request->query('search_q', '');
        $searchCols = $request->query('search_columns', []);
        $distinct = filter_var($request->query('distinct', false), FILTER_VALIDATE_BOOLEAN);
        $facet = filter_var($request->query('facet', false), FILTER_VALIDATE_BOOLEAN);
        $facetLimit = min(max((int) $request->query('facet_limit', 50), 1), 200);
        $facetOffset = max((int) $request->query('facet_offset', 0), 0);
        $distinctColumn = (string) $request->query('distinct_column', '');
        $sortColumn = (string) $request->query('sort_column', '');
        $sortDirection = in_array($request->query('sort_direction'), ['asc', 'desc']) ? $request->query('sort_direction') : 'asc';
        $aggregate = strtolower((string) $request->query('aggregate', ''));
        $aggregateColumns = $request->query('aggregate_columns', []);
        $groupBy = $request->query('group_by', []);
        $aggregates = $request->query('aggregates', []);

        foreach (['columns', 'filters', 'joins', 'sources', 'searchCols', 'aggregateColumns', 'groupBy', 'aggregates'] as $var) {
            if (! is_array($$var)) {
                $$var = [];
            }
        }

        // ─── Agrégats : format « une fonction par colonne » (aggregates[]) prioritaire
        //     sur le format legacy uniforme (aggregate + aggregate_columns[]). ────────
        $aggregateSpecs = [];
        foreach ($aggregates as $spec) {
            if (! is_array($spec)) {
                continue;
            }
            $col = (string) ($spec['column'] ?? '');
            $fn = strtolower((string) ($spec['fn'] ?? ''));
            if ($col !== '' && in_array($fn, self::AGG_FUNCTIONS, true)) {
                $aggregateSpecs[] = ['column' => $col, 'fn' => $fn];
            }
        }

        if ($aggregateSpecs === []) {
            if (! in_array($aggregate, self::AGG_FUNCTIONS, true) || empty($aggregateColumns)) {
                $aggregate = null;
                $aggregateColumns = [];
                $groupBy = [];
            } else {
                foreach ($aggregateColumns as $col) {
                    $aggregateSpecs[] = ['column' => (string) $col, 'fn' => $aggregate];
                }
            }
        } else {
            // Chemin live scalaire (LiveDatasetQueryService) : garde une vue « uniforme »
            // quand toutes les fonctions sont identiques et qu'il n'y a qu'une colonne.
            $fns = array_unique(array_column($aggregateSpecs, 'fn'));
            $aggregate = count($fns) === 1 ? $fns[array_key_first($fns)] : $aggregateSpecs[0]['fn'];
            $aggregateColumns = array_values(array_unique(array_column($aggregateSpecs, 'column')));
        }

        $graph = QueryGraph::fromRequest($sources, $joins, $primaryDatasetId);

        $this->calcSpecs = $this->parseCalcSpecs($request->query('calc', []));

        return [
            'limit' => $limit,
            'offset' => $offset,
            'columns' => array_values($columns),
            'filters' => array_values($filters),
            'joins' => array_values($joins),
            'searchQ' => $searchQ,
            'searchCols' => array_values($searchCols),
            'distinct' => $distinct,
            'facet' => $facet,
            'facetLimit' => $facetLimit,
            'facetOffset' => $facetOffset,
            'distinctColumn' => $distinctColumn ?: null,
            'sortColumn' => $sortColumn ?: null,
            'sortDirection' => $sortDirection,
            'aggregate' => $aggregate,
            'aggregateColumns' => array_values($aggregateColumns),
            'groupBy' => array_values($groupBy),
            'aggregateSpecs' => $aggregateSpecs,
            'graph' => $graph,
        ];
    }

    /**
     * @param  array<int, array{column: string, operator: string, value: string}>  $filters
     */
    private function distinctResponse(Dataset $dataset, string $col, int $limit, string $search, array $filters = [], ?QueryGraph $graph = null, int $userId = 0): JsonResponse
    {
        if ($graph && $graph->isMultiSource() && ! $dataset->isLive()) {
            // Colonne pilote (loop/param) issue d'une source jointe : on passe par le
            // moteur multi-sources (jointures appliquées avant le DISTINCT).
            $result = $this->resolveRows(
                $dataset, [$col], $filters, $limit, [], $userId, $search, $search !== '' ? [$col] : [],
                $col, $col, 'asc', null, [], [], 0, [], $graph,
            );
            $key = $result->columnMap[$col] ?? $col;
            $rows = array_values(array_filter(array_unique(array_map(
                fn ($r) => (string) ($r[$key] ?? ''),
                $result->rows,
            )), fn ($v) => $v !== ''));
            sort($rows);
            $rows = array_slice($rows, 0, $limit);

            return response()->json([
                'success' => true,
                'data' => [
                    'columns' => [$col],
                    'rows' => array_map(fn ($v) => [$col => $v], $rows),
                    'total_rows' => count($rows),
                ],
            ]);
        }

        // Une ref qualifiée (`col@<sourceId>`) sur la source primaire se ramène au nom nu.
        $resolvedCol = $graph ? $graph->resolveRef($col)['name'] : $col;

        $rows = $this->resolveDistinctValues($dataset, $resolvedCol, $limit, $search, $filters);

        $response = [
            'success' => true,
            'data' => [
                'columns' => [$col],
                'rows' => array_map(fn ($v) => [$col => $v], $rows),
                'total_rows' => count($rows),
            ],
        ];

        if ($dataset->isLive()) {
            // Une source live ne scanne jamais l'ensemble des valeurs — la liste vient
            // de l'échantillon capturé à la création, donc nécessairement partielle.
            $response['meta'] = ['partial' => true];
        }

        return response()->json($response);
    }

    /**
     * Panneau à facettes : valeurs distinctes d'une colonne + nombre d'occurrences,
     * triées par décompte décroissant, avec recherche et pagination. Alimente le
     * panneau de filtres du Studio.
     *
     * @param  array<int, array{column: string, operator: string, value: string}>  $filters
     */
    private function facetResponse(Dataset $dataset, string $col, int $limit, int $offset, string $search, array $filters = [], ?QueryGraph $graph = null, int $userId = 0): JsonResponse
    {
        // ─── Source live : pas de GROUP BY possible en amont → valeurs indicatives
        //     issues de l'échantillon capturé, sans décompte. ──────────────────
        if ($dataset->isLive()) {
            $resolvedCol = $graph ? $graph->resolveRef($col)['name'] : $col;
            $values = $this->liveQueryService->resolveDistinctValues($dataset, $resolvedCol, $limit, $search);

            return response()->json([
                'success' => true,
                'data' => [
                    'column' => $col,
                    'values' => array_map(fn ($v) => ['value' => (string) $v, 'count' => null], array_values($values)),
                    'total' => count($values),
                    'offset' => 0,
                    'limit' => $limit,
                ],
                'meta' => ['has_counts' => false, 'partial' => true],
            ]);
        }

        // ─── Multi-sources : jointures appliquées avant le décompte (scan plafonné). ──
        if ($graph && $graph->isMultiSource()) {
            $cap = (int) config('statsio.data_ingestion.facet_scan_cap', 50_000);
            $result = $this->resolveRows(
                $dataset, [$col], $filters, $cap, [], $userId, $search, $search !== '' ? [$col] : [],
                null, null, 'asc', null, [], [], 0, [], $graph,
            );
            $key = $result->columnMap[$col] ?? $col;
            [$values, $total] = $this->tallyFacet($result->rows, $key, $limit, $offset);

            return response()->json([
                'success' => true,
                'data' => [
                    'column' => $col,
                    'values' => $values,
                    'total' => $total,
                    'offset' => $offset,
                    'limit' => $limit,
                ],
                'meta' => ['has_counts' => true, 'partial' => count($result->rows) >= $cap],
            ]);
        }

        // ─── Mono-source matérialisée. ──────────────────────────────────────────
        $resolvedCol = $graph ? $graph->resolveRef($col)['name'] : $col;
        $data = $this->resolveColumnFacets($dataset, $resolvedCol, $limit, $offset, $search, $filters);

        return response()->json([
            'success' => true,
            'data' => [
                'column' => $col,
                'values' => $data['values'],
                'total' => $data['total'],
                'offset' => $offset,
                'limit' => $limit,
            ],
            'meta' => ['has_counts' => true, 'partial' => false],
        ]);
    }

    /**
     * Regroupe des lignes déjà résolues par valeur d'une colonne et compte les
     * occurrences. Tri : décompte décroissant puis valeur croissante.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{0: array<int, array{value: string, count: int}>, 1: int} [page, nb total de valeurs distinctes]
     */
    private function tallyFacet(array $rows, string $key, int $limit, int $offset): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $val = $row[$key] ?? null;
            if ($val === null || $val === '') {
                continue;
            }
            $str = (string) $val;
            $counts[$str] = ($counts[$str] ?? 0) + 1;
        }

        uksort($counts, function ($a, $b) use ($counts) {
            return $counts[$b] <=> $counts[$a] ?: strnatcasecmp($a, $b);
        });

        $total = count($counts);
        $page = array_slice($counts, $offset, $limit, true);
        $values = [];
        foreach ($page as $value => $count) {
            $values[] = ['value' => (string) $value, 'count' => $count];
        }

        return [$values, $total];
    }

    /**
     * @param  array<int, array{column: string, operator: string, value: string}>  $filters
     * @return array{values: array<int, array{value: string, count: int}>, total: int}
     */
    private function resolveColumnFacets(Dataset $dataset, string $column, int $limit, int $offset, string $search, array $filters = []): array
    {
        $version = $dataset->latestVersion;

        if (! $version?->parquet_storage_path) {
            return ['values' => [], 'total' => 0];
        }

        $cacheKey = $this->buildQueryCacheKey('facet', $dataset, $version, [], [
            'column' => $column,
            'limit' => $limit,
            'offset' => $offset,
            'search' => $search,
            'filters' => $filters,
        ]);

        return Cache::remember($cacheKey, self::CACHE_TTL, fn () => $this->fetchColumnFacets($dataset, $version, $column, $limit, $offset, $search, $filters));
    }

    /**
     * Requête DuckDB / mock-parquet réelle du décompte de facettes. Appelée uniquement sur cache miss.
     *
     * @param  array<int, array{column: string, operator: string, value: string}>  $filters
     * @return array{values: array<int, array{value: string, count: int}>, total: int}
     */
    private function fetchColumnFacets(Dataset $dataset, DatasetVersion $version, string $column, int $limit, int $offset, string $search, array $filters = []): array
    {
        $datasetsDisk = config('statsio.data_ingestion.datasets_disk', 'local');

        $raw = Storage::disk($datasetsDisk)->get($version->parquet_storage_path);
        if ($raw === null) {
            return ['values' => [], 'total' => 0];
        }

        $decoded = json_decode($raw, true);

        // ─── Mock parquet — scan PHP ────────────────────────────────────────────
        if (is_array($decoded) && isset($decoded['__mock__'])) {
            $allColumns = $decoded['schema'] ?? [];
            $needle = mb_strtolower($search);

            $counts = [];
            foreach ($decoded['data'] ?? [] as $row) {
                $assoc = array_is_list($row) ? array_combine($allColumns, $row) : $row;
                $val = $assoc[$column] ?? null;
                if ($val === null || $val === '') {
                    continue;
                }
                if (! $this->matchesFilters($assoc, $filters)) {
                    continue;
                }
                $str = (string) $val;
                if ($needle !== '' && mb_stripos($str, $needle) === false) {
                    continue;
                }
                $counts[$str] = ($counts[$str] ?? 0) + 1;
            }

            uksort($counts, fn ($a, $b) => ($counts[$b] <=> $counts[$a]) ?: strnatcasecmp($a, $b));

            $total = count($counts);
            $values = [];
            foreach (array_slice($counts, $offset, $limit, true) as $value => $count) {
                $values[] = ['value' => (string) $value, 'count' => $count];
            }

            return ['values' => $values, 'total' => $total];
        }

        // ─── Parquet réel via DuckDB ────────────────────────────────────────────
        $localParquet = tempnam(sys_get_temp_dir(), 'statsio_');
        file_put_contents($localParquet, $raw);
        $escapedPath = escapeshellarg($localParquet);
        $escapedCol = '"'.str_replace('"', '""', $column).'"';

        $whereSearch = $search !== ''
            ? " AND lower({$escapedCol}::VARCHAR) LIKE lower(".escapeshellarg('%'.$search.'%').')'
            : '';
        // buildDuckDbWhere() renvoie " WHERE a AND b" — raccordé en " AND ..." à la
        // clause WHERE déjà présente (IS NOT NULL).
        $filterClause = $this->buildDuckDbWhere($filters);
        if ($filterClause !== '') {
            $filterClause = ' AND '.substr($filterClause, strlen(' WHERE '));
        }

        $baseWhere = "WHERE {$escapedCol} IS NOT NULL{$filterClause}{$whereSearch}";
        $valuesSql = "SELECT {$escapedCol}::VARCHAR AS value, COUNT(*) AS count FROM read_parquet({$escapedPath}) {$baseWhere} GROUP BY 1 ORDER BY count DESC, value ASC LIMIT {$limit} OFFSET {$offset}";
        $totalSql = "SELECT COUNT(DISTINCT {$escapedCol}) AS total FROM read_parquet({$escapedPath}) {$baseWhere}";

        $values = [];
        $valuesOut = shell_exec('duckdb -json -c '.escapeshellarg($valuesSql).' 2>/dev/null');
        if ($valuesOut) {
            $jsonRows = json_decode($valuesOut, true);
            if (is_array($jsonRows)) {
                foreach ($jsonRows as $r) {
                    $value = (string) ($r['value'] ?? '');
                    if ($value === '') {
                        continue;
                    }
                    $values[] = ['value' => $value, 'count' => (int) ($r['count'] ?? 0)];
                }
            }
        }

        $total = count($values);
        $totalOut = shell_exec('duckdb -json -c '.escapeshellarg($totalSql).' 2>/dev/null');
        if ($totalOut) {
            $totalRows = json_decode($totalOut, true);
            if (is_array($totalRows) && isset($totalRows[0]['total'])) {
                $total = (int) $totalRows[0]['total'];
            }
        }

        return ['values' => $values, 'total' => $total];
    }

    /**
     * @param  array<int, array{column: string, operator: string, value: string}>  $filters
     * @param  array<int, array>  $joins  ancien format (dataset_id) — passé tel quel au service live
     * @param  array<int, array{column: string, fn: string}>  $aggregateSpecs
     */
    private function resolveRows(Dataset $dataset, ?array $selectColumns, array $filters, int $limit, array $joins = [], int $userId = 0, string $searchQ = '', array $searchCols = [], ?string $distinctColumn = null, ?string $sortColumn = null, string $sortDirection = 'asc', ?string $aggregate = null, array $aggregateColumns = [], array $groupBy = [], int $offset = 0, array $aggregateSpecs = [], ?QueryGraph $graph = null): QueryResult
    {
        $graph ??= QueryGraph::fromRequest([], $joins, $dataset->id);

        if ($dataset->isLive()) {
            if ($this->calcSpecs !== []) {
                throw new UnsupportedLiveQueryOperationException(
                    'Les colonnes calculées ne sont pas disponibles pour une source en direct.'
                );
            }
            // La pagination serveur (offset) ne s'applique qu'aux datasets matérialisés.
            [$cols, $rows, $total] = $this->liveQueryService->resolveRows(
                $dataset, $selectColumns, $filters, $limit, $joins, $userId, $searchQ, $searchCols,
                $distinctColumn, $sortColumn, $sortDirection, $aggregate, $aggregateColumns, $groupBy, $graph,
            );

            return new QueryResult($cols, $rows, $total);
        }

        $version = $dataset->latestVersion;

        if (! $version?->parquet_storage_path) {
            return new QueryResult($dataset->columns->pluck('name')->toArray(), [], $dataset->row_count ?? 0);
        }

        $cacheKey = $this->buildQueryCacheKey('rows', $dataset, $version, $graph, [
            'columns' => $selectColumns,
            'filters' => $filters,
            'limit' => $limit,
            'userId' => $userId,
            'searchQ' => $searchQ,
            'searchCols' => $searchCols,
            'distinctColumn' => $distinctColumn,
            'sortColumn' => $sortColumn,
            'sortDirection' => $sortDirection,
            'aggregateSpecs' => $aggregateSpecs,
            'groupBy' => $groupBy,
            'offset' => $offset,
            'calcSpecs' => $this->calcSpecs,
        ]);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use (
            $dataset, $version, $selectColumns, $filters, $limit, $userId, $searchQ, $searchCols,
            $distinctColumn, $sortColumn, $sortDirection, $aggregateSpecs, $groupBy, $offset, $graph
        ) {
            if ($graph->isMultiSource()) {
                return $this->fetchMultiSourceRows(
                    $dataset, $version, $graph, $selectColumns, $filters, $limit, $userId, $searchQ, $searchCols,
                    $distinctColumn, $sortColumn, $sortDirection, $aggregateSpecs, $groupBy, $offset,
                );
            }

            [$cols, $rows, $total] = $this->fetchParquetRows(
                $dataset, $version, $graph, $selectColumns, $filters, $limit, $userId, $searchQ, $searchCols,
                $distinctColumn, $sortColumn, $sortDirection, $aggregateSpecs, $groupBy, $offset,
            );

            return new QueryResult($cols, $rows, $total);
        });
    }

    /**
     * Requête mono-source (aucune jointure). Le graphe sert uniquement à ramener
     * une éventuelle ref qualifiée `col@<primaire>` à son nom nu.
     *
     * @param  array<int, array{column: string, fn: string}>  $aggregateSpecs
     * @return array{0: array<int, string>, 1: array<int, array<string, mixed>>, 2: int}
     */
    private function fetchParquetRows(Dataset $dataset, DatasetVersion $version, QueryGraph $graph, ?array $selectColumns, array $filters, int $limit, int $userId, string $searchQ, array $searchCols, ?string $distinctColumn, ?string $sortColumn, string $sortDirection, array $aggregateSpecs = [], array $groupBy = [], int $offset = 0): array
    {
        $bare = fn (?string $ref): ?string => $ref === null || $ref === '' ? $ref : $graph->resolveRef($ref)['name'];

        $selectColumns = $selectColumns !== null ? array_values(array_unique(array_map($bare, $selectColumns))) : null;
        $filters = array_map(fn ($f) => [...$f, 'column' => $bare($f['column'] ?? '')], $filters);
        $searchCols = array_map($bare, $searchCols);
        $distinctColumn = $bare($distinctColumn);
        $sortColumn = $bare($sortColumn);
        $groupBy = array_map($bare, $groupBy);
        $aggregateSpecs = array_map(fn ($s) => ['column' => $bare($s['column']), 'fn' => $s['fn']], $aggregateSpecs);

        $datasetsDisk = config('statsio.data_ingestion.datasets_disk', 'local');
        $raw = Storage::disk($datasetsDisk)->get($version->parquet_storage_path);
        if ($raw === null) {
            return [$dataset->columns->pluck('name')->toArray(), [], $dataset->row_count ?? 0];
        }

        $decoded = json_decode($raw, true);

        // ─── Mock parquet ───────────────────────────────────────────────────
        if (is_array($decoded) && isset($decoded['__mock__'])) {
            $allColumns = $decoded['schema'] ?? [];
            $rows = array_map(fn ($row) => array_combine($allColumns, $row), $decoded['data'] ?? []);
            $rows = $this->applyCalcColumns($rows, $bare);
            $rows = array_values(array_filter(
                $rows,
                fn ($assoc) => $this->matchesFilters($assoc, $filters) && $this->matchesSearchQ($assoc, $searchQ, $searchCols),
            ));

            $rows = $this->sortMockRows($rows, $sortColumn, $sortDirection);
            $rows = $this->distinctMockRows($rows, $distinctColumn);
            $totalAfterFilter = count($rows);

            if ($aggregateSpecs !== []) {
                $rows = $this->aggregateRows($rows, $aggregateSpecs, $groupBy);
                $rows = $this->orderAggregatedRows($rows, $groupBy, array_column($aggregateSpecs, 'column'), $sortColumn, $sortDirection);
                $allColumns = array_values(array_unique(array_merge($groupBy, array_column($aggregateSpecs, 'column'))));
                $totalAfterFilter = count($rows);
                $rows = array_slice($rows, 0, $limit);
            } else {
                $rows = array_slice($rows, $offset, $limit);
            }

            if ($selectColumns) {
                $rows = array_map(fn ($r) => array_intersect_key($r, array_flip($selectColumns)), $rows);
            }

            return [$allColumns, $rows, $totalAfterFilter];
        }

        // ─── Real Parquet via DuckDB CLI ────────────────────────────────────
        $localParquet = tempnam(sys_get_temp_dir(), 'statsio_');
        file_put_contents($localParquet, $raw);
        $escapedPath = escapeshellarg($localParquet);
        $q = fn (string $c) => '"'.str_replace('"', '""', $c).'"';

        // Tri numérique d'abord (les valeurs décorées « 90 % » comprises), puis
        // lexical en repli — les cellules non numériques passent en fin.
        $sortDir = strtoupper($sortDirection);
        $orderClause = $sortColumn
            ? ' ORDER BY '.$this->duckDbNumericExpr($q($sortColumn))." {$sortDir} NULLS LAST, ".$q($sortColumn)." {$sortDir}"
            : '';
        $where = $this->appendSearchClause($this->buildDuckDbWhere($filters), $searchQ, $searchCols);
        $colClause = $selectColumns ? implode(', ', array_map($q, $selectColumns)) : '*';

        // Colonnes calculées : injectées comme colonnes de la sous-requête → utilisables
        // ensuite comme n'importe quelle colonne (filtre, tri, group_by, agrégat).
        $source = "read_parquet({$escapedPath})";
        if ($this->calcSpecs !== []) {
            $calcCols = array_map(
                fn ($spec) => $this->calcColumnSql($spec['operands'], fn ($ref) => $q($bare($ref))).' AS '.$q('calc:'.$spec['id']),
                $this->calcSpecs,
            );
            $source = '(SELECT *, '.implode(', ', $calcCols)." FROM read_parquet({$escapedPath})) calc_src";
        }

        if ($distinctColumn) {
            $innerOrder = $sortColumn
                ? ' ORDER BY '.$q($distinctColumn).', '.$q($sortColumn).' '.strtoupper($sortDirection)
                : ' ORDER BY '.$q($distinctColumn);
            $inner = "SELECT DISTINCT ON ({$q($distinctColumn)}) * FROM {$source}{$where}{$innerOrder}";
            $sql = "SELECT {$colClause} FROM ({$inner}) sub{$orderClause}";
        } else {
            $sql = "SELECT {$colClause} FROM {$source}{$where}{$orderClause}";
        }

        if ($aggregateSpecs !== []) {
            $sql = $this->wrapAggregateSql($sql, $aggregateSpecs, $groupBy, $sortColumn, $sortDirection)." LIMIT {$limit}";
        } else {
            $sql .= " LIMIT {$limit}".($offset > 0 ? " OFFSET {$offset}" : '');
        }

        $jsonRows = $this->runDuckDb($sql);
        if ($jsonRows !== []) {
            return [array_keys($jsonRows[0]), $jsonRows, $dataset->row_count ?? count($jsonRows)];
        }

        return [$dataset->columns->pluck('name')->toArray(), [], $dataset->row_count ?? 0];
    }

    /**
     * Requête multi-sources : jointures entre N sources selon le graphe du bloc,
     * colonnes référencées `name` (primaire) ou `name@<sourceId>`, agrégats par
     * colonne. Renvoie aussi la table de correspondance ref → clé de ligne.
     *
     * @param  array<int, array{column: string, fn: string}>  $aggregateSpecs
     */
    private function fetchMultiSourceRows(Dataset $dataset, DatasetVersion $version, QueryGraph $graph, ?array $selectColumns, array $filters, int $limit, int $userId, string $searchQ, array $searchCols, ?string $distinctColumn, ?string $sortColumn, string $sortDirection, array $aggregateSpecs, array $groupBy, int $offset): QueryResult
    {
        $datasetsDisk = config('statsio.data_ingestion.datasets_disk', 'local');

        // 1. Charger chaque source (dataset + parquet décodé), en vérifiant l'accès.
        $loaded = [];   // sourceId => ['dataset'=>Dataset, 'schema'=>string[], 'decoded'=>array|null, 'raw'=>string]
        foreach ($graph->sources() as $src) {
            $ds = Dataset::where('id', $src['dataset_id'])
                ->where(fn ($q) => $q->where('user_id', $userId)
                    ->orWhereHas('dataSource.users', fn ($u) => $u->where('user_id', $userId)))
                ->first();
            if (! $ds) {
                throw new InvalidQueryGraphException("Source inaccessible : {$graph->labelFor($src['id'])}.");
            }
            $v = $ds->latestVersion;
            $raw = $v?->parquet_storage_path ? Storage::disk($datasetsDisk)->get($v->parquet_storage_path) : null;
            if ($raw === null) {
                throw new InvalidQueryGraphException("Source non prête : {$graph->labelFor($src['id'])}.");
            }
            $decoded = json_decode($raw, true);
            $isMock = is_array($decoded) && isset($decoded['__mock__']);
            $loaded[$src['id']] = [
                'schema' => $isMock ? ($decoded['schema'] ?? []) : $ds->columns->pluck('name')->toArray(),
                'decoded' => $isMock ? $decoded : null,
                'raw' => $raw,
            ];
        }

        // 2. Plan de colonnes : ref `name@<sourceId>` => clé de ligne (nue si pas de collision).
        [$plan, $columnMap] = $this->buildColumnPlan($graph, fn ($id) => $loaded[$id]['schema']);

        $refToKey = function (?string $ref) use ($graph, $plan): ?string {
            if ($ref === null || $ref === '') {
                return $ref;
            }
            $r = $graph->resolveRef($ref);

            return $plan[$r['source_id']][$r['name']] ?? $r['name'];
        };

        $allMock = ! in_array(null, array_column($loaded, 'decoded'), true);

        if (! $allMock) {
            return $this->fetchMultiSourceRowsReal(
                $dataset, $graph, $loaded, $plan, $columnMap, $selectColumns, $filters, $limit,
                $searchQ, $searchCols, $distinctColumn, $sortColumn, $sortDirection, $aggregateSpecs, $groupBy, $offset,
            );
        }

        // 3. Lignes de la primaire, puis jointures dans l'ordre topologique.
        $primaryId = $graph->primarySourceId();
        $rows = array_map(
            fn ($r) => array_combine($loaded[$primaryId]['schema'], $r),
            $loaded[$primaryId]['decoded']['data'] ?? [],
        );

        foreach ($graph->orderedJoins() as $edge) {
            $toId = $edge['to_source'];
            $toSchema = $loaded[$toId]['schema'];
            $joinRows = array_map(
                fn ($r) => array_combine($toSchema, $r),
                $loaded[$toId]['decoded']['data'] ?? [],
            );
            $fromKey = $plan[$this->sourceIdForAlias($graph, $edge['from_alias'])][$edge['from_column']]
                ?? $edge['from_column'];
            $toKeyMap = [];
            foreach ($toSchema as $col) {
                $toKeyMap[$col] = $plan[$toId][$col];
            }
            $rows = $this->hashJoinRows($rows, $fromKey, $joinRows, $edge['to_column'], $toKeyMap, $edge['type']);
        }

        // 3b. Colonnes calculées (avant filtres/tri/agrégat).
        $rows = $this->applyCalcColumns($rows, fn ($ref) => $refToKey($ref) ?? $ref);

        // 4. Filtres / recherche / tri / distinct — sur les clés de ligne résolues.
        $mFilters = array_map(fn ($f) => [...$f, 'column' => $refToKey($f['column'] ?? '')], $filters);
        $mSearchCols = array_map($refToKey, $searchCols);
        $rows = array_values(array_filter(
            $rows,
            fn ($r) => $this->matchesFilters($r, $mFilters) && $this->matchesSearchQ($r, $searchQ, $mSearchCols),
        ));
        $rows = $this->sortMockRows($rows, $refToKey($sortColumn), $sortDirection);
        $rows = $this->distinctMockRows($rows, $refToKey($distinctColumn));
        $total = count($rows);

        // 5. Agrégation par colonne OU pagination.
        if ($aggregateSpecs !== []) {
            $specs = array_map(fn ($s) => ['column' => $refToKey($s['column']), 'fn' => $s['fn']], $aggregateSpecs);
            $groupKeys = array_map($refToKey, $groupBy);
            $rows = $this->aggregateRows($rows, $specs, $groupKeys);
            $rows = $this->orderAggregatedRows($rows, $groupKeys, array_column($specs, 'column'), $sortColumn ? $refToKey($sortColumn) : null, $sortDirection);
            $total = count($rows);
            $rows = array_slice($rows, 0, $limit);
            $outColumns = array_values(array_unique(array_merge($groupKeys, array_column($specs, 'column'))));
        } else {
            $rows = array_slice($rows, $offset, $limit);
            $outColumns = $rows !== [] ? array_keys($rows[0]) : $this->defaultProjectionKeys($graph, $plan);
        }

        // 6. Projection finale vers les refs demandées.
        if ($selectColumns !== null && $selectColumns !== []) {
            $keys = array_values(array_unique(array_map($refToKey, $selectColumns)));
            $rows = array_map(fn ($r) => $this->pickKeys($r, $keys), $rows);
            $outColumns = $keys;
        } elseif ($aggregateSpecs === []) {
            $keys = $this->defaultProjectionKeys($graph, $plan);
            $rows = array_map(fn ($r) => $this->pickKeys($r, $keys), $rows);
            $outColumns = $keys;
        }

        return new QueryResult($outColumns, array_values($rows), $total, $columnMap);
    }

    /**
     * Chemin DuckDB réel du multi-sources. Écrit chaque parquet en fichier temp,
     * assemble le FROM + JOINs dans l'ordre topologique, projette avec alias sur
     * collision de nom.
     *
     * @param  array<string, array{schema: array<string>, decoded: ?array, raw: string}>  $loaded
     * @param  array<string, array<string, string>>  $plan
     * @param  array<string, string>  $columnMap
     * @param  array<int, array{column: string, fn: string}>  $aggregateSpecs
     */
    private function fetchMultiSourceRowsReal(Dataset $dataset, QueryGraph $graph, array $loaded, array $plan, array $columnMap, ?array $selectColumns, array $filters, int $limit, string $searchQ, array $searchCols, ?string $distinctColumn, ?string $sortColumn, string $sortDirection, array $aggregateSpecs, array $groupBy, int $offset): QueryResult
    {
        $q = fn (string $c) => '"'.str_replace('"', '""', $c).'"';
        $paths = [];
        foreach ($loaded as $id => $entry) {
            $tmp = tempnam(sys_get_temp_dir(), 'statsio_');
            file_put_contents($tmp, $entry['raw']);
            $paths[$id] = $tmp;
        }

        $primaryId = $graph->primarySourceId();
        $primaryAlias = $graph->aliasFor($primaryId);
        $from = 'read_parquet('.escapeshellarg($paths[$primaryId]).") {$primaryAlias}";
        foreach ($graph->orderedJoins() as $edge) {
            $type = strtoupper($edge['type']);
            $from .= " {$type} JOIN read_parquet(".escapeshellarg($paths[$edge['to_source']]).") {$edge['to_alias']}"
                ." ON {$edge['from_alias']}.{$q($edge['from_column'])} = {$edge['to_alias']}.{$q($edge['to_column'])}";
        }

        // Projection : chaque (source, colonne) => alias."name" [AS "name@sourceId"]
        $projParts = [];
        foreach ($graph->sources() as $src) {
            $alias = $graph->aliasFor($src['id']);
            foreach ($loaded[$src['id']]['schema'] as $col) {
                $key = $plan[$src['id']][$col];
                $projParts[] = $key === $col
                    ? "{$alias}.{$q($col)}"
                    : "{$alias}.{$q($col)} AS {$q($key)}";
            }
        }
        $projection = implode(', ', $projParts);

        $sqlRef = function (?string $ref) use ($graph, $q): ?string {
            if ($ref === null || $ref === '') {
                return null;
            }
            $r = $graph->resolveRef($ref);

            return "{$r['alias']}.{$q($r['name'])}";
        };

        // Colonnes calculées : on aplatit le JOIN dans `msbase` (projection + calc),
        // et tout ce qui suit (filtres, tri, distinct, agrégat) opère sur `msbase`.
        if ($this->calcSpecs !== []) {
            $keyOf = function (string $ref) use ($graph, $plan, $q): string {
                $r = $graph->resolveRef($ref);

                return $q($plan[$r['source_id']][$r['name']] ?? $r['name']);
            };
            $calcCols = array_map(
                fn ($spec) => $this->calcColumnSql($spec['operands'], $keyOf).' AS '.$q('calc:'.$spec['id']),
                $this->calcSpecs,
            );
            $from = '(SELECT '.$projection.', '.implode(', ', $calcCols)." FROM {$from}) msbase";
            $projection = 'msbase.*';
            $sqlRef = function (?string $ref) use ($graph, $plan, $q): ?string {
                if ($ref === null || $ref === '') {
                    return null;
                }
                $r = $graph->resolveRef($ref);

                return 'msbase.'.$q($plan[$r['source_id']][$r['name']] ?? $r['name']);
            };
        }

        $where = '';
        $clauses = [];
        foreach ($filters as $f) {
            $col = $sqlRef($f['column'] ?? '');
            if ($col === null) {
                continue;
            }
            $clauses[] = $this->duckDbFilterClause($col, $f['operator'] ?? '=', (string) ($f['value'] ?? ''));
        }
        if ($searchQ !== '' && $searchCols !== []) {
            $val = "'".str_replace("'", "''", $searchQ)."'";
            $or = array_filter(array_map(fn ($c) => ($x = $sqlRef($c)) ? "LOWER({$x}::VARCHAR) LIKE LOWER(CONCAT('%', {$val}, '%'))" : null, $searchCols));
            if ($or) {
                $clauses[] = '('.implode(' OR ', $or).')';
            }
        }
        if ($clauses) {
            $where = ' WHERE '.implode(' AND ', $clauses);
        }

        $sortSql = $sqlRef($sortColumn);
        $sortDir = strtoupper($sortDirection);
        $orderClause = $sortSql
            ? ' ORDER BY '.$this->duckDbNumericExpr($sortSql)." {$sortDir} NULLS LAST, {$sortSql} {$sortDir}"
            : '';
        $distinctSql = $sqlRef($distinctColumn);

        if ($distinctSql) {
            $innerOrder = $sortSql ? " ORDER BY {$distinctSql}, {$sortSql} ".strtoupper($sortDirection) : " ORDER BY {$distinctSql}";
            $inner = "SELECT DISTINCT ON ({$distinctSql}) {$projection} FROM {$from}{$where}{$innerOrder}";
            $sql = "SELECT * FROM ({$inner}) sub{$orderClause}";
        } else {
            $sql = "SELECT {$projection} FROM {$from}{$where}{$orderClause}";
        }

        $refToKey = function (string $ref) use ($graph, $plan): string {
            $r = $graph->resolveRef($ref);

            return $plan[$r['source_id']][$r['name']] ?? $r['name'];
        };

        if ($aggregateSpecs !== []) {
            $specs = array_map(fn ($s) => ['column' => $refToKey($s['column']), 'fn' => $s['fn']], $aggregateSpecs);
            $groupKeys = array_map($refToKey, $groupBy);
            $sortKey = ($sortColumn !== null && $sortColumn !== '') ? $refToKey($sortColumn) : null;
            $sql = $this->wrapAggregateSql($sql, $specs, $groupKeys, $sortKey, $sortDirection)." LIMIT {$limit}";
        } else {
            $sql .= " LIMIT {$limit}".($offset > 0 ? " OFFSET {$offset}" : '');
        }

        $jsonRows = $this->runDuckDb($sql);

        if ($jsonRows !== [] && $selectColumns) {
            $keys = array_values(array_unique(array_map($refToKey, $selectColumns)));
            $jsonRows = array_map(fn ($r) => $this->pickKeys($r, $keys), $jsonRows);
        }

        $cols = $jsonRows !== [] ? array_keys($jsonRows[0]) : $this->defaultProjectionKeys($graph, $plan);

        return new QueryResult($cols, $jsonRows, $dataset->row_count ?? count($jsonRows), $columnMap);
    }

    /**
     * @param  callable(string): array<string>  $schemaFor  sourceId => noms de colonnes
     * @return array{0: array<string, array<string, string>>, 1: array<string, string>}
     *                                                                                  [plan (sourceId => (colName => rowKey)), columnMap (ref => rowKey)]
     */
    private function buildColumnPlan(QueryGraph $graph, callable $schemaFor): array
    {
        $plan = [];
        $columnMap = [];
        $taken = [];

        foreach ($graph->sources() as $src) {
            $sid = $src['id'];
            $plan[$sid] = [];
            foreach ($schemaFor($sid) as $col) {
                if (! isset($taken[$col])) {
                    $taken[$col] = true;
                    $key = $col;
                } else {
                    $key = $col.'@'.$sid;
                }
                $plan[$sid][$col] = $key;
                $columnMap[$col.'@'.$sid] = $key;
                if ($sid === $graph->primarySourceId()) {
                    $columnMap[$col] = $key;
                }
            }
        }

        return [$plan, $columnMap];
    }

    private function sourceIdForAlias(QueryGraph $graph, string $alias): string
    {
        foreach ($graph->sources() as $src) {
            if ($graph->aliasFor($src['id']) === $alias) {
                return $src['id'];
            }
        }

        return $graph->primarySourceId();
    }

    /** @return array<int, string> */
    private function defaultProjectionKeys(QueryGraph $graph, array $plan): array
    {
        $primaryId = $graph->primarySourceId();
        $keys = array_values($plan[$primaryId] ?? []);
        foreach ($graph->legacyProjection() as $p) {
            $k = $plan[$p['source_id']][$p['column']] ?? null;
            if ($k !== null && ! in_array($k, $keys, true)) {
                $keys[] = $k;
            }
        }

        return $keys;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    private function pickKeys(array $row, array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = $row[$k] ?? null;
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, array<string, mixed>>  $joinRows
     * @param  array<string, string>  $toKeyMap  colonne native de la source jointe => clé de ligne cible
     * @return array<int, array<string, mixed>>
     */
    private function hashJoinRows(array $rows, string $fromKey, array $joinRows, string $toColumn, array $toKeyMap, string $type): array
    {
        $index = [];
        foreach ($joinRows as $jr) {
            $k = (string) ($jr[$toColumn] ?? '');
            if ($k !== '' && ! isset($index[$k])) {
                $index[$k] = $jr;
            }
        }

        $nullRow = array_fill_keys(array_values($toKeyMap), null);
        $isInner = $type === 'inner';
        $out = [];
        foreach ($rows as $row) {
            $match = $index[(string) ($row[$fromKey] ?? '')] ?? null;
            if ($match === null) {
                if (! $isInner) {
                    $out[] = array_merge($row, $nullRow);
                }

                continue;
            }
            $merged = $row;
            foreach ($toKeyMap as $native => $rowKey) {
                $merged[$rowKey] = $match[$native] ?? null;
            }
            $out[] = $merged;
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function sortMockRows(array $rows, ?string $sortColumn, string $sortDirection): array
    {
        if (! $sortColumn) {
            return $rows;
        }
        usort($rows, fn ($a, $b) => $this->compareCells($a[$sortColumn] ?? null, $b[$sortColumn] ?? null, $sortDirection === 'desc'));

        return $rows;
    }

    /**
     * Comparaison d'une paire de cellules : tri numérique quand les deux valeurs le
     * sont (« 90 % » comprises), valeurs non numériques reléguées en fin, repli lexical.
     */
    private function compareCells(mixed $av, mixed $bv, bool $desc): int
    {
        $an = NumericValueParser::parse($av);
        $bn = NumericValueParser::parse($bv);

        if ($an !== null && $bn !== null) {
            return $desc ? ($bn <=> $an) : ($an <=> $bn);
        }
        if ($an !== null) {
            return -1;
        }
        if ($bn !== null) {
            return 1;
        }
        $cmp = strcmp((string) $av, (string) $bv);

        return $desc ? -$cmp : $cmp;
    }

    /**
     * Ordonne des lignes déjà agrégées (une par combinaison de $groupBy). Le GROUP BY
     * détruit tout tri appliqué en amont : sans ça, l'axe X d'un graphique agrégé
     * ressort dans un ordre arbitraire. Tri sur $sortColumn si elle fait partie des
     * clés de groupe ou d'une colonne agrégée ; sinon repli sur la 1re clé de groupe
     * (l'axe X), croissant.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $groupBy  clés de groupe (déjà résolues en clés de ligne)
     * @param  array<int, string>  $aggColumns  colonnes agrégées (déjà résolues)
     * @return array<int, array<string, mixed>>
     */
    private function orderAggregatedRows(array $rows, array $groupBy, array $aggColumns, ?string $sortColumn, string $sortDirection): array
    {
        if ($rows === [] || $groupBy === []) {
            return $rows;
        }

        $desc = strtolower($sortDirection) === 'desc';
        $target = ($sortColumn !== null && $sortColumn !== '' && (in_array($sortColumn, $groupBy, true) || in_array($sortColumn, $aggColumns, true)))
            ? $sortColumn
            : $groupBy[0];
        if ($target === $groupBy[0] && $target !== $sortColumn) {
            $desc = false; // repli « axe X » : toujours croissant
        }
        $secondary = array_values(array_filter($groupBy, fn ($k) => $k !== $target));

        usort($rows, function ($a, $b) use ($target, $desc, $secondary) {
            $c = $this->compareCells($a[$target] ?? null, $b[$target] ?? null, $desc);
            if ($c !== 0) {
                return $c;
            }
            foreach ($secondary as $k) {
                $c = $this->compareCells($a[$k] ?? null, $b[$k] ?? null, false);
                if ($c !== 0) {
                    return $c;
                }
            }

            return 0;
        });

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function distinctMockRows(array $rows, ?string $distinctColumn): array
    {
        if (! $distinctColumn) {
            return $rows;
        }
        $seen = [];

        return array_values(array_filter($rows, function ($r) use ($distinctColumn, &$seen) {
            $val = (string) ($r[$distinctColumn] ?? '');
            if (isset($seen[$val])) {
                return false;
            }
            $seen[$val] = true;

            return true;
        }));
    }

    /** @return array<int, array<string, mixed>> */
    private function runDuckDb(string $sql): array
    {
        $output = shell_exec('duckdb -json -c '.escapeshellarg($sql).' 2>/dev/null');
        if (! $output) {
            return [];
        }
        $rows = json_decode($output, true);

        return is_array($rows) ? $rows : [];
    }

    private function duckDbFilterClause(string $col, string $op, string $rawValue): string
    {
        $val = "'".str_replace("'", "''", $rawValue)."'";
        $colN = $this->duckDbNumericExpr($col);
        $valN = $this->duckDbNumericExpr($val);

        return match ($op) {
            '=' => "{$col} = {$val}",
            '!=' => "{$col} != {$val}",
            '>' => "{$colN} > {$valN}",
            '>=' => "{$colN} >= {$valN}",
            '<' => "{$colN} < {$valN}",
            '<=' => "{$colN} <= {$valN}",
            'contains' => "LOWER({$col}) LIKE LOWER(CONCAT('%', {$val}, '%'))",
            'not_contains' => "LOWER({$col}) NOT LIKE LOWER(CONCAT('%', {$val}, '%'))",
            'in' => $this->duckDbInClause($col, $rawValue, false),
            'not_in' => $this->duckDbInClause($col, $rawValue, true),
            default => '1=1',
        };
    }

    /**
     * Expression SQL DuckDB qui extrait la valeur numérique d'une colonne/valeur
     * potentiellement décorée : « 90 % », « 1 234 », « 10,000+ », « 12 € »… On
     * retire les séparateurs de milliers (espace, virgule) puis toute décoration
     * non numérique avant `TRY_CAST`. Sans quoi `TRY_CAST('90%' AS DOUBLE)` = NULL
     * et le point disparaît des graphiques / le filtre <,> exclut la ligne.
     *
     * Aligné sur `NumericValueParser::parse()` (PHP) et `parseNumericValue()` (front).
     */
    private function duckDbNumericExpr(string $sqlExpr): string
    {
        // On isole d'abord chiffres et séparateurs (« 47,8 % » → « 47,8 »), puis :
        // « 12,5 » (une seule virgule, pas de point, 1-2 décimales) → virgule décimale ;
        // sinon la virgule est un séparateur de milliers (« 1,234 », « 10,000 »).
        $v = "REGEXP_REPLACE(CAST({$sqlExpr} AS VARCHAR), '[^0-9,.+-]', '', 'g')";
        $commaNorm = "CASE WHEN REGEXP_MATCHES({$v}, '^[^.,]*,[0-9]{1,2}$') "
            ."THEN REPLACE({$v}, ',', '.') ELSE REPLACE({$v}, ',', '') END";
        $digitsOnly = "REGEXP_REPLACE({$commaNorm}, '[^0-9.-]', '', 'g')";

        return "TRY_CAST(NULLIF({$digitsOnly}, '') AS DOUBLE)";
    }

    // ─── Colonnes calculées (combinaisons arithmétiques) ────────────────────────

    private const CALC_OPS = ['+', '-', '*', '/'];

    /**
     * Valide les specs `calc[]` de la requête. Chaque opérande : `op` dans une liste
     * fixe + exactement un de `column` (chaîne opaque, résolue plus tard) / `value`
     * (numérique). Aucune SQL brute.
     *
     * @return array<int, array{id: string, operands: array<int, array{op?: string, column?: string, value?: float}>}>
     */
    private function parseCalcSpecs(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $spec) {
            if (! is_array($spec)) {
                continue;
            }
            $id = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($spec['id'] ?? ''));
            $operandsRaw = is_array($spec['operands'] ?? null) ? $spec['operands'] : [];
            if ($id === '' || $operandsRaw === []) {
                continue;
            }

            $operands = [];
            foreach (array_values($operandsRaw) as $i => $o) {
                if (! is_array($o)) {
                    continue;
                }
                $entry = [];
                if ($i > 0) {
                    $op = (string) ($o['op'] ?? '+');
                    $entry['op'] = in_array($op, self::CALC_OPS, true) ? $op : '+';
                }
                if (isset($o['column']) && (string) $o['column'] !== '') {
                    $entry['column'] = (string) $o['column'];
                } elseif (isset($o['value']) && is_numeric($o['value'])) {
                    $entry['value'] = (float) $o['value'];
                } else {
                    continue; // opérande vide → ignorée
                }
                $operands[] = $entry;
            }

            if ($operands !== []) {
                $out[] = ['id' => $id, 'operands' => $operands];
            }
        }

        return $out;
    }

    /**
     * Expression SQL d'une colonne calculée : `(numexpr(a) op numexpr(b) …)`.
     * `$keyOf` mappe une réf de colonne d'opérande vers sa clé de ligne (nom nu,
     * ou `alias."col"` en multi-sources).
     *
     * @param  array<int, array{op?: string, column?: string, value?: float}>  $operands
     */
    private function calcColumnSql(array $operands, callable $keyOf): string
    {
        $sql = '';
        foreach ($operands as $i => $o) {
            $term = isset($o['column'])
                ? $this->duckDbNumericExpr($keyOf($o['column']))
                : (string) ($o['value'] ?? 0);
            if ($i === 0) {
                $sql = $term;

                continue;
            }
            $op = $o['op'] ?? '+';
            $sql = $op === '/'
                ? "({$sql}) / NULLIF({$term}, 0)"
                : "({$sql}) {$op} {$term}";
        }

        return "({$sql})";
    }

    /**
     * Ajoute les colonnes calculées à des lignes déjà chargées (chemin mock / PHP).
     * `$keyOf` mappe une réf d'opérande vers la clé de ligne.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function applyCalcColumns(array $rows, callable $keyOf): array
    {
        if ($this->calcSpecs === []) {
            return $rows;
        }

        return array_map(function ($row) use ($keyOf) {
            foreach ($this->calcSpecs as $spec) {
                $acc = null;
                foreach ($spec['operands'] as $i => $o) {
                    $val = isset($o['column'])
                        ? NumericValueParser::parse($row[$keyOf($o['column'])] ?? null)
                        : (float) ($o['value'] ?? 0);
                    if ($i === 0) {
                        $acc = $val;

                        continue;
                    }
                    if ($acc === null || $val === null) {
                        $acc = null;

                        continue;
                    }
                    $acc = match ($o['op'] ?? '+') {
                        '-' => $acc - $val,
                        '*' => $acc * $val,
                        '/' => $val == 0.0 ? null : $acc / $val,
                        default => $acc + $val,
                    };
                }
                $row['calc:'.$spec['id']] = $acc;
            }

            return $row;
        }, $rows);
    }

    /**
     * Clause `col IN (...)` (insensible à la casse) pour un filtre `in` / `not_in`
     * dont la valeur est un tableau JSON.
     */
    private function duckDbInClause(string $col, string $rawValue, bool $negate): string
    {
        $list = $this->decodeFilterList($rawValue);
        if ($list === []) {
            return $negate ? '1=1' : '1=0';
        }

        $quoted = implode(', ', array_map(
            fn ($v) => "LOWER('".str_replace("'", "''", $v)."')",
            $list,
        ));

        return $negate
            ? "LOWER({$col}::VARCHAR) NOT IN ({$quoted})"
            : "LOWER({$col}::VARCHAR) IN ({$quoted})";
    }

    /**
     * Wraps an existing row-level SQL query (built from the same filter/join/search
     * logic used everywhere else) in an outer aggregate SELECT, so the aggregation
     * runs over every matching row instead of just the page that would otherwise be
     * returned. $aggregate must already be validated against the sum/avg/count/min/max
     * whitelist by the caller (parseQueryParams) before reaching here.
     *
     * @param  array<string>  $aggregateColumns
     * @param  array<string>  $groupBy
     */
    private function wrapAggregateSql(string $innerSql, array $aggregateSpecs, array $groupBy, ?string $sortColumn = null, string $sortDirection = 'asc'): string
    {
        $quote = fn (string $c) => '"'.str_replace('"', '""', $c).'"';

        $selectParts = array_map($quote, $groupBy);
        $seen = [];
        foreach ($aggregateSpecs as $spec) {
            $col = $spec['column'];
            if (isset($seen[$col])) {
                continue; // une seule fonction par colonne côté SQL (alias unique)
            }
            $seen[$col] = true;
            $fn = strtoupper($spec['fn']);
            $escaped = $quote($col);
            // Les colonnes texte décorées ("10,000+", "90 %", "12 €") ne sont pas
            // castables telles quelles : on extrait la valeur numérique avant l'agrégat.
            $expr = $fn === 'COUNT' ? 'COUNT(*)' : "{$fn}({$this->duckDbNumericExpr($escaped)})";
            $selectParts[] = "{$expr} AS {$escaped}";
        }

        $sql = 'SELECT '.implode(', ', $selectParts)." FROM ({$innerSql}) agg_sub";
        if (! empty($groupBy)) {
            $sql .= ' GROUP BY '.implode(', ', array_map($quote, $groupBy));
            // Le GROUP BY détruit l'ORDER BY de la sous-requête → on ré-ordonne la
            // sortie agrégée (sinon l'axe X d'un graphique ressort en vrac).
            $sql .= $this->aggregateOrderClause($groupBy, array_keys($seen), $sortColumn, $sortDirection);
        }

        return $sql;
    }

    /**
     * Clause ORDER BY pour une sortie agrégée. Tri sur $sortColumn si elle est une
     * clé de groupe ou une colonne agrégée ; sinon repli sur la 1re clé de groupe
     * (l'axe X), croissant.
     *
     * @param  array<int, string>  $groupBy
     * @param  array<int, string>  $aggColumns
     */
    private function aggregateOrderClause(array $groupBy, array $aggColumns, ?string $sortColumn, string $sortDirection): string
    {
        if ($groupBy === []) {
            return '';
        }

        $desc = strtolower($sortDirection) === 'desc';
        $isAggTarget = $sortColumn !== null && $sortColumn !== '' && in_array($sortColumn, $aggColumns, true) && ! in_array($sortColumn, $groupBy, true);
        $target = ($sortColumn !== null && $sortColumn !== '' && (in_array($sortColumn, $groupBy, true) || $isAggTarget))
            ? $sortColumn
            : $groupBy[0];
        if ($target === $groupBy[0] && $target !== $sortColumn) {
            $desc = false;
        }

        $q = '"'.str_replace('"', '""', $target).'"';
        $dir = $desc ? 'DESC' : 'ASC';

        // Une colonne agrégée est déjà numérique (AVG/SUM/…) — pas de décodage texte.
        // Une clé de groupe reste du texte brut, potentiellement décoré (« 90 % »).
        return $isAggTarget
            ? " ORDER BY {$q} {$dir} NULLS LAST"
            : ' ORDER BY '.$this->duckDbNumericExpr($q)." {$dir} NULLS LAST, {$q} {$dir}";
    }

    /**
     * Groups mock-parquet rows (already filtered/joined) by $groupBy and computes
     * $aggregate over each $aggregateColumns entry — the PHP-side equivalent of
     * wrapAggregateSql() for datasets that don't go through the DuckDB CLI.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string>  $aggregateColumns
     * @param  array<string>  $groupBy
     * @return array<int, array<string, mixed>>
     */
    private function aggregateRows(array $rows, array $aggregateSpecs, array $groupBy): array
    {
        // Une seule fonction par colonne (aligné sur wrapAggregateSql) : dernière gagne.
        $fnByColumn = [];
        foreach ($aggregateSpecs as $spec) {
            $fnByColumn[$spec['column']] = $spec['fn'];
        }

        $groups = [];
        foreach ($rows as $row) {
            $key = implode("\0", array_map(fn ($c) => (string) ($row[$c] ?? ''), $groupBy));
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'keyValues' => array_combine($groupBy, array_map(fn ($c) => $row[$c] ?? null, $groupBy)),
                    'rowCount' => 0,
                    'values' => [],
                ];
            }
            $groups[$key]['rowCount']++;
            foreach ($fnByColumn as $col => $fn) {
                $groups[$key]['values'][$col][] = $row[$col] ?? null;
            }
        }

        $result = [];
        foreach ($groups as $group) {
            $out = $group['keyValues'];
            foreach ($fnByColumn as $col => $fn) {
                if ($fn === 'count') {
                    $out[$col] = $group['rowCount'];

                    continue;
                }
                $vals = array_values(array_filter(
                    array_map(fn ($v) => NumericValueParser::parse($v), $group['values'][$col] ?? []),
                    fn ($v) => $v !== null,
                ));
                $out[$col] = match ($fn) {
                    'sum' => array_sum($vals),
                    'avg' => count($vals) ? array_sum($vals) / count($vals) : 0,
                    'min' => count($vals) ? min($vals) : null,
                    'max' => count($vals) ? max($vals) : null,
                    default => null,
                };
            }
            $result[] = $out;
        }

        return $result;
    }

    /**
     * @param  array<int, array{column: string, operator: string, value: string}>  $filters
     */
    private function resolveDistinctValues(Dataset $dataset, string $column, int $limit, string $search = '', array $filters = []): array
    {
        if ($dataset->isLive()) {
            // Les sources live servent leurs valeurs distinctes depuis l'échantillon
            // capturé à la création (déjà partiel) — les filtres n'y sont pas appliqués.
            return $this->liveQueryService->resolveDistinctValues($dataset, $column, $limit, $search);
        }

        $version = $dataset->latestVersion;

        if (! $version?->parquet_storage_path) {
            return [];
        }

        $cacheKey = $this->buildQueryCacheKey('distinct', $dataset, $version, [], [
            'column' => $column,
            'limit' => $limit,
            'search' => $search,
            'filters' => $filters,
        ]);

        return Cache::remember($cacheKey, self::CACHE_TTL, fn () => $this->fetchDistinctValues($dataset, $version, $column, $limit, $search, $filters));
    }

    /**
     * Runs the actual DuckDB / mock-parquet distinct-values query. Only called on a cache miss.
     */
    private function fetchDistinctValues(Dataset $dataset, DatasetVersion $version, string $column, int $limit, string $search, array $filters = []): array
    {
        $datasetsDisk = config('statsio.data_ingestion.datasets_disk', 'local');

        $raw = Storage::disk($datasetsDisk)->get($version->parquet_storage_path);
        if ($raw === null) {
            return [];
        }

        $decoded = json_decode($raw, true);

        // Mock parquet path — scan all rows, no hard limit before filtering
        if (is_array($decoded) && isset($decoded['__mock__'])) {
            $allColumns = $decoded['schema'] ?? [];
            $allRows = $decoded['data'] ?? [];
            $needle = mb_strtolower($search);

            $seen = [];
            foreach ($allRows as $row) {
                $assoc = array_is_list($row) ? array_combine($allColumns, $row) : $row;
                $val = $assoc[$column] ?? null;
                if ($val === null || $val === '') {
                    continue;
                }
                if (! $this->matchesFilters($assoc, $filters)) {
                    continue;
                }
                $str = (string) $val;
                if ($needle !== '' && mb_stripos($str, $needle) === false) {
                    continue;
                }
                $seen[$str] = true;
            }
            $values = array_keys($seen);
            sort($values);

            return array_slice($values, 0, $limit);
        }

        // Real Parquet via DuckDB — write to local temp
        $localParquet = tempnam(sys_get_temp_dir(), 'statsio_');
        file_put_contents($localParquet, $raw);
        $escapedPath = escapeshellarg($localParquet);
        $escapedCol = '"'.str_replace('"', '""', $column).'"';
        $whereSearch = $search !== ''
            ? " AND lower({$escapedCol}::VARCHAR) LIKE lower(".escapeshellarg('%'.$search.'%').')'
            : '';
        // buildDuckDbWhere() renvoie " WHERE a AND b" — on le raccorde en " AND ..."
        // à la clause WHERE déjà présente (IS NOT NULL).
        $filterClause = $this->buildDuckDbWhere($filters);
        if ($filterClause !== '') {
            $filterClause = ' AND '.substr($filterClause, strlen(' WHERE '));
        }
        $sql = "SELECT DISTINCT {$escapedCol} FROM read_parquet({$escapedPath}) WHERE {$escapedCol} IS NOT NULL{$filterClause}{$whereSearch} ORDER BY {$escapedCol} LIMIT {$limit}";
        $output = shell_exec('duckdb -json -c '.escapeshellarg($sql).' 2>/dev/null');

        if ($output) {
            $jsonRows = json_decode($output, true);
            if (is_array($jsonRows)) {
                return array_values(array_filter(array_map(fn ($r) => (string) ($r[$column] ?? ''), $jsonRows), fn ($v) => $v !== ''));
            }
        }

        return [];
    }

    /**
     * Builds a cache key for a parquet query/preview/distinct-values response.
     * Embeds the dataset version checksum (and, for joins, the joined datasets'
     * checksums) so the key changes — and the cache naturally invalidates —
     * whenever any involved dataset is re-ingested.
     *
     * @param  QueryGraph|array<int, mixed>  $graph  graphe multi-sources, ou [] pour les valeurs distinctes
     */
    private function buildQueryCacheKey(string $kind, Dataset $dataset, DatasetVersion $version, QueryGraph|array $graph, array $params): string
    {
        $graphSignature = $graph instanceof QueryGraph ? $graph->cacheSignature() : $graph;
        $secondaryDatasetIds = $graph instanceof QueryGraph
            ? array_values(array_filter($graph->datasetIds(), fn ($id) => $id !== $dataset->id))
            : [];

        $joinChecksums = $secondaryDatasetIds
            ? DatasetVersion::whereIn('dataset_id', $secondaryDatasetIds)
                ->orderByDesc('version_number')
                ->get(['dataset_id', 'checksum'])
                ->unique('dataset_id')
                ->pluck('checksum', 'dataset_id')
                ->sortKeys()
                ->toArray()
            : [];

        $paramsHash = md5(json_encode([$params, $graphSignature, $joinChecksums]));
        $versionKey = $version->checksum ?? "v{$version->id}";

        return "datasets.query.{$kind}.{$dataset->id}.{$versionKey}.{$paramsHash}";
    }

    private function matchesFilters(array $row, array $filters): bool
    {
        foreach ($filters as $filter) {
            $col = $filter['column'] ?? '';
            $operator = $filter['operator'] ?? '=';
            $value = (string) ($filter['value'] ?? '');

            if (! isset($row[$col])) {
                continue;
            }

            $cell = (string) $row[$col];
            // Comparaisons numériques : on extrait la valeur (« 90 % » → 90) des deux
            // côtés plutôt que d'exiger une chaîne déjà numérique.
            $cellNum = NumericValueParser::parse($cell);
            $valueNum = NumericValueParser::parse($value);

            $match = match ($operator) {
                '=' => strtolower($cell) === strtolower($value),
                '!=' => strtolower($cell) !== strtolower($value),
                '>' => $cellNum !== null && $valueNum !== null && $cellNum > $valueNum,
                '>=' => $cellNum !== null && $valueNum !== null && $cellNum >= $valueNum,
                '<' => $cellNum !== null && $valueNum !== null && $cellNum < $valueNum,
                '<=' => $cellNum !== null && $valueNum !== null && $cellNum <= $valueNum,
                'contains' => str_contains(strtolower($cell), strtolower($value)),
                'not_contains' => ! str_contains(strtolower($cell), strtolower($value)),
                'in' => in_array(strtolower($cell), array_map('strtolower', $this->decodeFilterList($value)), true),
                'not_in' => ! in_array(strtolower($cell), array_map('strtolower', $this->decodeFilterList($value)), true),
                default => true,
            };

            if (! $match) {
                return false;
            }
        }

        return true;
    }

    /**
     * Décode la valeur d'un filtre `in` / `not_in` : un tableau JSON (`["a","b"]`).
     * Tolère une valeur simple (traitée comme liste à un élément) pour rester robuste
     * aux filtres legacy.
     *
     * @return array<int, string>
     */
    private function decodeFilterList(string $value): array
    {
        $decoded = json_decode($value, true);

        if (is_array($decoded)) {
            return array_values(array_map(fn ($v) => (string) $v, $decoded));
        }

        return $value === '' ? [] : [$value];
    }

    private function matchesSearchQ(array $row, string $searchQ, array $searchCols): bool
    {
        if ($searchQ === '' || empty($searchCols)) {
            return true;
        }
        $needle = mb_strtolower($searchQ);
        foreach ($searchCols as $col) {
            if (mb_stripos((string) ($row[$col] ?? ''), $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function appendSearchClause(string $where, string $searchQ, array $searchCols, string $tableAlias = ''): string
    {
        if ($searchQ === '' || empty($searchCols)) {
            return $where;
        }
        $prefix = $tableAlias ? "{$tableAlias}." : '';
        $val = "'".str_replace("'", "''", $searchQ)."'";
        $clauses = array_map(function ($col) use ($prefix, $val) {
            $c = $prefix.'"'.str_replace('"', '""', $col).'"';

            return "LOWER({$c}::VARCHAR) LIKE LOWER(CONCAT('%', {$val}, '%'))";
        }, $searchCols);
        $clause = '('.implode(' OR ', $clauses).')';

        return $where === '' ? " WHERE {$clause}" : "{$where} AND {$clause}";
    }

    private function buildDuckDbWhere(array $filters, string $tableAlias = ''): string
    {
        if (empty($filters)) {
            return '';
        }

        $prefix = $tableAlias ? "{$tableAlias}." : '';
        $clauses = [];
        foreach ($filters as $filter) {
            $col = $prefix.'"'.str_replace('"', '""', $filter['column'] ?? '').'"';
            $val = "'".str_replace("'", "''", $filter['value'] ?? '')."'";
            $op = $filter['operator'] ?? '=';
            $colN = $this->duckDbNumericExpr($col);
            $valN = $this->duckDbNumericExpr($val);

            $clauses[] = match ($op) {
                '=' => "{$col} = {$val}",
                '!=' => "{$col} != {$val}",
                '>' => "{$colN} > {$valN}",
                '>=' => "{$colN} >= {$valN}",
                '<' => "{$colN} < {$valN}",
                '<=' => "{$colN} <= {$valN}",
                'contains' => "LOWER({$col}) LIKE LOWER(CONCAT('%', {$val}, '%'))",
                'not_contains' => "LOWER({$col}) NOT LIKE LOWER(CONCAT('%', {$val}, '%'))",
                'in' => $this->duckDbInClause($col, $filter['value'] ?? '', false),
                'not_in' => $this->duckDbInClause($col, $filter['value'] ?? '', true),
                default => '1=1',
            };
        }

        return ' WHERE '.implode(' AND ', $clauses);
    }

    public function update(Request $request, Dataset $dataset): JsonResponse
    {
        if ($dataset->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Non autorisé.'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string|max:1000',
        ]);

        $dataset->update($validated);

        return response()->json([
            'success' => true,
            'data' => $this->formatDataset($dataset->fresh(), $request->user()->id),
        ]);
    }

    public function destroy(Request $request, Dataset $dataset): JsonResponse
    {
        $userId = $request->user()->id;

        if (! $dataset->isOwnedBy($userId)) {
            // Utilisateur rattaché à une source publique (pas propriétaire) :
            // "supprimer" ne fait que retirer son rattachement, la source reste
            // intacte pour les autres comptes qui l'utilisent.
            if ($dataset->dataSource?->users()->where('user_id', $userId)->exists()) {
                $dataset->dataSource->users()->detach($userId);

                return response()->json(['success' => true, 'message' => 'Source retirée de vos sources.'], 200);
            }

            return response()->json(['success' => false, 'message' => 'Non autorisé.'], 403);
        }

        $dataSource = $dataset->dataSource;
        $datasetsDisk = config('statsio.data_ingestion.datasets_disk', 'local');

        // Delete parquet files from the datasets disk (local or R2)
        foreach ($dataset->versions as $version) {
            if ($version->parquet_storage_path) {
                Storage::disk($datasetsDisk)->delete($version->parquet_storage_path);
            }
        }
        // Raw file may already be null (deleted after conversion)
        if ($dataSource?->raw_storage_path) {
            Storage::delete($dataSource->raw_storage_path);
        }

        // Deleting the data_source cascades to dataset, columns, versions
        $dataSource?->delete() ?? $dataset->delete();

        return response()->json(['success' => true, 'message' => 'Source supprimée.'], 200);
    }

    private function formatDataset(Dataset $dataset, int $requestUserId): array
    {
        $data = [
            'id' => $dataset->id,
            'name' => $dataset->name,
            'description' => $dataset->description,
            'row_count' => $dataset->row_count,
            'status' => $dataset->status->value,
            'progress' => $dataset->progress,
            'created_at' => $dataset->created_at->toIso8601String(),
            'is_owner' => $dataset->isOwnedBy($requestUserId),
            'data_source_id' => $dataset->data_source_id,
        ];

        $dataSource = $dataset->dataSource;
        if ($dataSource && $dataSource->source_kind === 'api' && $dataSource->isOwnedBy($requestUserId)) {
            $data['source_kind'] = 'api';
            $data['materialization'] = $dataSource->materialization->value;
            if ($dataSource->isLive()) {
                $data['query_mapping'] = $dataSource->api_config['query_mapping'] ?? null;
            } else {
                $data['refresh_frequency'] = $dataSource->refresh_frequency->value;
                $data['last_refreshed_at'] = $dataSource->last_refreshed_at?->toIso8601String();
                $data['next_refresh_at'] = $dataSource->next_refresh_at?->toIso8601String();
            }
        }

        return $data;
    }

    private function formatDatasetFull(Dataset $dataset, int $requestUserId): array
    {
        return [
            ...$this->formatDataset($dataset, $requestUserId),
            'data_source_id' => $dataset->data_source_id,
            'columns' => $dataset->columns->map(fn ($col) => [
                'name' => $col->name,
                'type' => $col->type->value,
                'nullable' => $col->nullable,
                'sample_values' => $col->sample_values,
                'order' => $col->column_order,
            ])->values(),
            'versions' => $dataset->versions->map(fn ($v) => [
                'version_number' => $v->version_number,
                'row_count' => $v->row_count,
                'file_size_bytes' => $v->file_size_bytes,
                'created_at' => $v->created_at->toIso8601String(),
            ])->values(),
        ];
    }
}
