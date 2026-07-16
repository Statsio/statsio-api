<?php

namespace App\Services\DataIngestion\LiveQuery;

use App\Domain\DataIngestion\Exceptions\ApiSourceFetchException;
use App\Domain\DataIngestion\Exceptions\LiveApiQueryException;
use App\Domain\DataIngestion\Exceptions\UnsupportedLiveQueryOperationException;
use App\Jobs\RefreshLiveAggregateJob;
use App\Models\DataIngestion\Dataset;
use App\Services\DataIngestion\NumericValueParser;
use App\Services\DataIngestion\PaginatedApiFetcher;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Point d'entrée appelé par DatasetController pour un dataset "live" — résout
 * le mapping de requête, applique cache et rate-limit auto-imposé, interroge
 * l'API externe, et reformate la réponse dans le même contrat de tuple
 * [columns, rows, total_rows] que le chemin Parquet existant.
 */
class LiveDatasetQueryService
{
    public function __construct(
        private readonly LiveQueryMappingResolver $resolver,
        private readonly LiveApiQueryClient $client,
        private readonly PaginatedApiFetcher $fetcher,
    ) {}

    /**
     * Miroir de la signature de DatasetController::resolveRows() — les paramètres
     * non pertinents pour le chemin live (joins/aggregate/search/sort/distinct)
     * ne sont utilisés que pour rejeter explicitement l'opération si non vide.
     *
     * @param  array<int, array{column: string, operator: string, value: string}>  $filters
     * @param  array<int, array>  $joins
     * @return array{0: array<string>, 1: array<int, array<string, mixed>>, 2: int}
     *
     * @throws UnsupportedLiveQueryOperationException
     * @throws LiveApiQueryException
     */
    public function resolveRows(
        Dataset $dataset,
        ?array $selectColumns,
        array $filters,
        int $limit,
        array $joins = [],
        int $userId = 0,
        string $searchQ = '',
        array $searchCols = [],
        ?string $distinctColumn = null,
        ?string $sortColumn = null,
        string $sortDirection = 'asc',
        ?string $aggregate = null,
        array $aggregateColumns = [],
        array $groupBy = [],
    ): array {
        $dataSource = $dataset->dataSource;
        $config = $dataSource->api_config ?? [];
        $queryMapping = $config['query_mapping'] ?? [];

        // Cas simple d'un KPI scalaire (une seule colonne agrégée, pas de group by/jointure/tri/
        // distinct) : calculable en streaming sur les vraies données filtrées, sans snapshot — voir
        // computeAggregate(). Toute combinaison plus riche (group by, jointure...) reste rejetée
        // par assertSupportedOperation() ci-dessous, non supportée en v1.
        if ($aggregate !== null && empty($joins) && empty($groupBy) && count($aggregateColumns) === 1
            && $distinctColumn === null && $sortColumn === null) {
            return $this->computeAggregate($dataset, $aggregate, $aggregateColumns[0], $filters);
        }

        $this->resolver->assertSupportedOperation($queryMapping, $joins, $distinctColumn, $sortColumn, $aggregate);

        $resolvedFilters = $this->resolver->resolveFilters($filters, $queryMapping);
        $this->assertRateLimitNotExceeded($dataSource->id);

        $maxLimit = (int) config('statsio.data_ingestion.live_query.max_limit', 5000);
        $limit = min($limit, $maxLimit);

        $ttl = (int) config('statsio.data_ingestion.live_query.cache_ttl_seconds', 60);

        if ($searchQ !== '') {
            $searchColumnParams = $this->resolver->resolveSearchColumnParams($searchCols, $queryMapping);
            $cacheKey = $this->buildCacheKey($dataset, $resolvedFilters, $limit, $selectColumns, $searchQ, $searchCols);

            return Cache::remember(
                $cacheKey,
                $ttl,
                fn () => $this->fetchLiveSearchRows($dataset, $config, $searchColumnParams, $searchQ, $resolvedFilters, $limit, $selectColumns),
            );
        }

        $cacheKey = $this->buildCacheKey($dataset, $resolvedFilters, $limit, $selectColumns);

        return Cache::remember(
            $cacheKey,
            $ttl,
            fn () => $this->fetchLiveRows($dataset, $config, $queryMapping, $resolvedFilters, $limit, $selectColumns),
        );
    }

    /**
     * Liste des valeurs uniques d'une colonne, pour peupler un filtre de l'UI —
     * servie depuis les échantillons capturés à la création (DatasetColumn::sample_values),
     * jamais depuis un scan complet upstream. Nécessairement partielle.
     *
     * @return array<string>
     *
     * @throws UnsupportedLiveQueryOperationException
     */
    public function resolveDistinctValues(Dataset $dataset, string $column, int $limit, string $search = ''): array
    {
        $queryMapping = $dataset->dataSource->api_config['query_mapping'] ?? [];
        $this->resolver->assertDistinctValuesSupported($queryMapping, $column);

        $col = $dataset->columns->firstWhere('name', $column);
        $values = $col?->sample_values ?? [];

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $values = array_values(array_filter($values, fn ($v) => mb_stripos((string) $v, $needle) !== false));
        }

        return array_slice($values, 0, $limit);
    }

    /**
     * Calcule une vraie agrégation (sum/avg/count/min/max) sur une source live, en streaming
     * à travers toutes les pages upstream correspondant aux filtres — sans jamais matérialiser
     * les lignes ni faire de snapshot. Contrairement à resolveRows() sur le chemin ligne (une
     * seule page bornée par $limit), ceci parcourt potentiellement de nombreuses pages via
     * PaginatedApiFetcher::fetchAll() — mêmes garde-fous que l'ingestion snapshot (max_pages,
     * time_budget, retry par page, arrêt gracieux si une page échoue en cours de route). Le
     * résultat est donc exact sur ce qui a pu être scanné ; s'il a fallu s'arrêter avant d'avoir
     * tout parcouru (dataset filtré trop volumineux pour le budget de temps), il est partiel —
     * pas une approximation par échantillonnage, une vraie agrégation sur un préfixe réel des
     * données.
     *
     * Stale-while-revalidate plutôt qu'un cache à expiration dure : une agrégation sans filtre
     * sur un gros dataset peut prendre plusieurs minutes, bien plus qu'aucun timeout HTTP
     * raisonnable côté frontend. Dès qu'une valeur a été calculée une première fois, elle est
     * conservée indéfiniment (RefreshLiveAggregateJob::REFRESH_TTL) et servie instantanément ;
     * une fois "périmée" (aggregate_cache_ttl_seconds), l'appel suivant déclenche un
     * rafraîchissement en tâche de fond SANS attendre — la requête HTTP en cours reçoit
     * immédiatement l'ancienne valeur. Seul le tout premier calcul (aucune valeur connue) est
     * synchrone, faute d'avoir quoi que ce soit à afficher en attendant.
     *
     * @param  array<int, array{column: string, operator: string, value: string}>  $filters
     * @return array{0: array<string>, 1: array<int, array<string, mixed>>, 2: int}
     *
     * @throws LiveApiQueryException
     */
    private function computeAggregate(Dataset $dataset, string $aggregate, string $column, array $filters): array
    {
        $dataSource = $dataset->dataSource;
        $config = $dataSource->api_config ?? [];
        $queryMapping = $config['query_mapping'] ?? [];
        $resolvedFilters = $this->resolver->resolveFilters($filters, $queryMapping);

        [$valueKey, $freshKey, $lockKey] = $this->aggregateCacheKeys($dataset, $aggregate, $column, $resolvedFilters);
        $cached = Cache::get($valueKey);

        if ($cached !== null) {
            if (! Cache::has($freshKey) && ! Cache::has($lockKey)) {
                // Verrou de courte durée : évite d'empiler un job par requête HTTP si plusieurs
                // arrivent avant que le rafraîchissement précédent ne soit terminé.
                Cache::put($lockKey, true, 120);
                RefreshLiveAggregateJob::dispatch($dataset->id, $aggregate, $column, $filters);
            }

            return $cached;
        }

        // Aucune valeur connue pour ce filtre : premier calcul, forcément synchrone.
        $this->assertRateLimitNotExceeded($dataSource->id);
        $result = $this->streamAggregate($config, $resolvedFilters, $aggregate, $column);
        $this->storeAggregateResult($valueKey, $freshKey, $result);

        return $result;
    }

    /**
     * Recalcule et remet à jour le cache d'une agrégation déjà connue — appelée uniquement
     * par RefreshLiveAggregateJob, jamais depuis une requête HTTP synchrone.
     *
     * @param  array<int, array{column: string, operator: string, value: string}>  $filters
     */
    public function refreshAggregateCache(Dataset $dataset, string $aggregate, string $column, array $filters): void
    {
        $dataSource = $dataset->dataSource;
        $config = $dataSource->api_config ?? [];
        $queryMapping = $config['query_mapping'] ?? [];
        $resolvedFilters = $this->resolver->resolveFilters($filters, $queryMapping);

        [$valueKey, $freshKey, $lockKey] = $this->aggregateCacheKeys($dataset, $aggregate, $column, $resolvedFilters);

        try {
            $this->assertRateLimitNotExceeded($dataSource->id);
            $result = $this->streamAggregate($config, $resolvedFilters, $aggregate, $column);
            $this->storeAggregateResult($valueKey, $freshKey, $result);
        } finally {
            Cache::forget($lockKey);
        }
    }

    /** @return array{0: string, 1: string, 2: string} [valueKey, freshKey, lockKey] */
    private function aggregateCacheKeys(Dataset $dataset, string $aggregate, string $column, array $resolvedFilters): array
    {
        $valueKey = "datasets.query.live.aggregate.{$dataset->id}.".md5(json_encode([$aggregate, $column, $resolvedFilters]));

        return [$valueKey, "{$valueKey}.fresh", "{$valueKey}.refreshing"];
    }

    /** @param  array{0: array<string>, 1: array<int, array<string, mixed>>, 2: int}  $result */
    private function storeAggregateResult(string $valueKey, string $freshKey, array $result): void
    {
        $ttl = (int) config('statsio.data_ingestion.live_query.aggregate_cache_ttl_seconds', 300);
        Cache::put($valueKey, $result, now()->addDay());
        Cache::put($freshKey, true, $ttl);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $resolvedFilters
     * @return array{0: array<string>, 1: array<int, array<string, mixed>>, 2: int}
     *
     * @throws LiveApiQueryException
     */
    private function streamAggregate(array $config, array $resolvedFilters, string $aggregate, string $column): array
    {
        $url = $config['url'] ?? null;
        $method = $config['method'] ?? 'GET';
        $headers = $config['headers'] ?? [];
        $dataPath = $config['data_path'] ?? null;
        $pagination = $config['pagination'] ?? ['style' => 'none'];
        $maxPageSize = $config['query_mapping']['max_page_size'] ?? null;
        if ($maxPageSize) {
            $pagination = array_merge($pagination, ['page_size' => $maxPageSize]);
        }

        $sum = 0.0;
        $count = 0;
        $min = null;
        $max = null;

        $onPage = function (array $records) use ($column, &$sum, &$count, &$min, &$max) {
            foreach ($records as $record) {
                $value = NumericValueParser::parse($record[$column] ?? null);
                if ($value === null) {
                    continue;
                }
                $sum += $value;
                $count++;
                $min = $min === null ? $value : min($min, $value);
                $max = $max === null ? $value : max($max, $value);
            }
        };

        try {
            $this->fetcher->fetchAll($url, $method, $headers, $dataPath, $pagination, $onPage, $resolvedFilters);
        } catch (ApiSourceFetchException $e) {
            throw new LiveApiQueryException("Impossible de calculer l'agrégation : {$e->getMessage()}", 502, $e);
        }

        $value = match ($aggregate) {
            'count' => $count,
            'sum' => $sum,
            'avg' => $count > 0 ? $sum / $count : null,
            'min' => $min,
            'max' => $max,
            default => null,
        };

        return [[$column], [[$column => $value]], 1];
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $queryMapping
     * @param  array<string, mixed>  $resolvedFilters
     * @return array{0: array<string>, 1: array<int, array<string, mixed>>, 2: int}
     *
     * @throws LiveApiQueryException
     */
    private function fetchLiveRows(Dataset $dataset, array $config, array $queryMapping, array $resolvedFilters, int $limit, ?array $selectColumns): array
    {
        $countPath = $queryMapping['count_path'] ?? null;
        [$rows, $total] = $this->fetchOnePage($config, $resolvedFilters, $limit, $countPath);

        $columns = $this->collectColumns($rows, $dataset);

        if ($selectColumns) {
            $rows = array_map(fn ($r) => array_intersect_key($r, array_flip($selectColumns)), $rows);
        }

        return [$columns, $rows, $total ?? count($rows)];
    }

    /**
     * Recherche texte libre (search_q/search_columns) : une requête upstream par
     * colonne recherchée (fusionnée avec les filtres explicites déjà résolus),
     * les API REST classiques ne combinant pas un OR entre plusieurs paramètres
     * différents en une seule requête. Toutes ces requêtes sont exécutées EN
     * PARALLÈLE via LiveApiQueryClient::fetchMany() (pool HTTP) plutôt qu'une
     * par une : sur une API upstream lente (ex. Hub'Eau, ~20s/page), une
     * recherche sur 3 colonnes enchaînées séquentiellement pouvait prendre
     * jusqu'à une minute, contre le temps d'une seule page en parallèle.
     * Résultats fusionnés et dédoublonnés (une même ligne peut matcher
     * plusieurs colonnes) ; le total retourné est la taille de cet ensemble
     * fusionné — les `count` upstream par colonne ne peuvent pas être combinés
     * en un total OR exact sans tout parcourir.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, string>  $searchColumnParams  colonne => paramètre upstream
     * @param  array<string, mixed>  $resolvedFilters
     * @return array{0: array<string>, 1: array<int, array<string, mixed>>, 2: int}
     *
     * @throws LiveApiQueryException
     */
    private function fetchLiveSearchRows(Dataset $dataset, array $config, array $searchColumnParams, string $searchQ, array $resolvedFilters, int $limit, ?array $selectColumns): array
    {
        $url = $config['url'] ?? null;
        $method = $config['method'] ?? 'GET';
        $headers = $config['headers'] ?? [];
        $dataPath = $config['data_path'] ?? null;
        $searchQ = $this->sanitizeSearchTerm($searchQ);

        if ($searchQ === '') {
            return [$this->collectColumns([], $dataset), [], 0];
        }

        if (isset($searchColumnParams['*'])) {
            // Use the global search param
            $globalSearchParam = $searchColumnParams['*'];
            $query = array_merge($resolvedFilters, [$globalSearchParam => $searchQ]);
            $page = $this->client->fetch($url, $method, $headers, $query);
            $records = $this->fetcher->extractRecordsFromBody($page['body'], $dataPath);
            $merged = array_map(fn ($r) => $this->shapeRow($r), $records);
        } else {
            // Use per-column search params
            $queriesByColumn = [];
            foreach ($searchColumnParams as $column => $param) {
                $queriesByColumn[$column] = array_merge($resolvedFilters, [$param => $searchQ]);
            }
            $pages = $this->client->fetchMany($url, $method, $headers, $queriesByColumn);

            $merged = [];
            $seen = [];
            foreach ($searchColumnParams as $column => $param) {
                $records = $this->fetcher->extractRecordsFromBody($pages[$column]['body'], $dataPath);
                foreach ($records as $record) {
                    $row = $this->shapeRow($record);
                    $key = md5(json_encode($row));
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $merged[] = $row;
                    if (count($merged) >= $limit) {
                        break 2;
                    }
                }
            }
        }

        $columns = $this->collectColumns($merged, $dataset);

        if ($selectColumns) {
            $merged = array_map(fn ($r) => array_intersect_key($r, array_flip($selectColumns)), $merged);
        }

        return [$columns, $merged, count($merged)];
    }

    /**
     * Beaucoup d'API de ce type valident strictement le terme de recherche saisi par
     * l'utilisateur (accents refusés, jeu de caractères limité — cf. medicaments-api.giygas.dev
     * qui rejette tout sauf lettres/chiffres/espaces/tirets/points/slash/apostrophe/plus) : on
     * translittère en ASCII et on retire le reste plutôt que de laisser échouer une recherche
     * française toute simple ("énergisant" -> "energisant"). Ne réduit jamais la pertinence pour
     * une API plus permissive, seulement pour celles qui auraient de toute façon rejeté l'original.
     */
    private function sanitizeSearchTerm(string $term): string
    {
        $ascii = Str::ascii($term);
        $stripped = preg_replace("/[^A-Za-z0-9 .\/'+-]+/", ' ', $ascii) ?? '';

        return trim(preg_replace('/\s+/', ' ', $stripped) ?? '');
    }

    /**
     * Un seul appel HTTP upstream : construit la requête (filtres + pagination
     * bornée à $limit), l'exécute, et retourne les lignes déjà mises en forme
     * + le total upstream si `$countPath` est fourni et présent dans la réponse.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @return array{0: array<int, array<string, mixed>>, 1: ?int}
     *
     * @throws LiveApiQueryException
     */
    private function fetchOnePage(array $config, array $query, int $limit, ?string $countPath): array
    {
        $url = $config['url'] ?? null;
        $method = $config['method'] ?? 'GET';
        $headers = $config['headers'] ?? [];
        $dataPath = $config['data_path'] ?? null;
        $pagination = $config['pagination'] ?? ['style' => 'none'];
        $maxPageSize = $config['query_mapping']['max_page_size'] ?? null;

        // Une seule requête upstream par colonne par appel Studio : le plafond `max_limit`
        // (5000 par défaut) reste sous la taille de page max habituelle de ce type d'API
        // (ex. 20000 sur Hub'Eau), donc une page suffit dans l'usage normal. Si l'upstream
        // a une page plus petite que ce qui est demandé, on retourne simplement moins de
        // lignes que `limit` (comme le ferait un simple LIMIT SQL non atteint), sans boucler.
        $requestedSize = $maxPageSize ? min($limit, (int) $maxPageSize) : $limit;
        $paginationForRequest = array_merge($pagination, ['page_size' => $requestedSize]);
        // If there are filter parameters, don't add pagination parameters (for APIs that only allow one query param at a time)
        $fullQuery = empty($query) ? array_merge($query, $this->fetcher->buildFirstPageQuery($paginationForRequest)) : $query;

        $page = $this->client->fetch($url, $method, $headers, $fullQuery);
        $records = $this->fetcher->extractRecordsFromBody($page['body'], $dataPath);

        $total = null;
        if ($countPath) {
            $countValue = Arr::get($page['body'], $countPath);
            $total = is_numeric($countValue) ? (int) $countValue : null;
        }

        return [array_map(fn ($record) => $this->shapeRow($record), $records), $total];
    }

    /**
     * Coercion identique à JsonParser::fromRecords() (bool -> 'true'/'false', tableaux/objets
     * imbriqués sérialisés en JSON, reste casté en chaîne) — pour que les valeurs d'une ligne
     * live aient exactement le même typage que celles d'un dataset snapshot côté frontend.
     */
    private function shapeRow(array $record): array
    {
        $row = [];
        foreach ($record as $key => $value) {
            $row[$key] = match (true) {
                is_bool($value) => $value ? 'true' : 'false',
                is_array($value), is_object($value) => json_encode($value),
                $value === null => null,
                default => (string) $value,
            };
        }

        return $row;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string>
     */
    private function collectColumns(array $rows, Dataset $dataset): array
    {
        if (! empty($rows)) {
            return array_keys($rows[0]);
        }

        return $dataset->columns->pluck('name')->toArray();
    }

    private function assertRateLimitNotExceeded(int $dataSourceId): void
    {
        $limit = (int) config('statsio.data_ingestion.live_query.rate_limit_per_minute', 30);
        $key = "live_query.rate.{$dataSourceId}";
        $count = (int) Cache::get($key, 0);

        if ($count >= $limit) {
            throw new LiveApiQueryException(
                'Trop de requêtes vers cette source en direct pour le moment. Réessayez dans quelques instants.',
                429,
            );
        }

        if ($count === 0) {
            Cache::put($key, 1, 60);
        } else {
            Cache::increment($key);
        }
    }

    /**
     * @param  array<string, mixed>  $resolvedFilters
     * @param  string[]  $searchCols
     */
    private function buildCacheKey(Dataset $dataset, array $resolvedFilters, int $limit, ?array $selectColumns, string $searchQ = '', array $searchCols = []): string
    {
        $hash = md5(json_encode([$resolvedFilters, $limit, $selectColumns, $searchQ, $searchCols]));

        return "datasets.query.live.{$dataset->id}.{$hash}";
    }
}
