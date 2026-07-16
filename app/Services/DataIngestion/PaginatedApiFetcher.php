<?php

namespace App\Services\DataIngestion;

use App\Domain\DataIngestion\Exceptions\ApiSourceFetchException;
use Illuminate\Support\Arr;

/**
 * Récupère les enregistrements d'une source API, potentiellement sur plusieurs
 * pages distantes (offset/page numérique, curseur, ou lien "next" en body/header).
 *
 * `fetchFirstPage()` (une seule requête) est utilisée pour la validation rapide
 * à la création/reconfiguration d'une source. `fetchAll()` (boucle complète) est
 * utilisée par le requêtage en streaming des sources "live" (agrégations —
 * voir LiveDatasetQueryService).
 */
class PaginatedApiFetcher
{
    public function __construct(
        private readonly HttpProbeService $httpProbe,
    ) {}

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $pagination
     * @return array{records: array}
     *
     * @throws ApiSourceFetchException
     */
    public function fetchFirstPage(string $url, string $method, array $headers, ?string $dataPath, array $pagination): array
    {
        $timeout = (int) config('statsio.data_ingestion.pagination.request_timeout_seconds', 15);
        $page = $this->fetchPageOrFail($url, $method, $headers, $this->firstPageQuery($pagination), $timeout);
        $this->assertResponseSize($page['raw_size']);

        return ['records' => $this->extractRecords($page['body'], $dataPath)];
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $pagination
     * @param  ?callable(array): void  $onPage  Si fourni, chaque page est passée à ce callback au lieu
     *                                          d'être accumulée dans `records` — permet à l'appelant de
     *                                          streamer les enregistrements (ex. vers un fichier) sans
     *                                          jamais garder le dataset complet en mémoire PHP. `records`
     *                                          reste alors vide dans la valeur de retour.
     * @param  array<string, mixed>  $staticQuery  Paramètres fusionnés dans la query de CHAQUE page (première
     *                                              et suivantes) — ex. des filtres résolus pour une agrégation
     *                                              en streaming sur une source live, qui doivent rester
     *                                              appliqués tout au long de la pagination.
     * @return array{records: array, truncated: bool, stopped_reason: ?string, pages_fetched: int, row_count: int}
     *
     * @throws ApiSourceFetchException
     */
    public function fetchAll(string $url, string $method, array $headers, ?string $dataPath, array $pagination, ?callable $onPage = null, array $staticQuery = []): array
    {
        $style = $pagination['style'] ?? 'none';
        $maxRows = (int) config('statsio.data_ingestion.max_rows', 500_000);
        $hardCap = (int) config('statsio.data_ingestion.pagination.max_pages_hard_cap', 500);
        $maxPages = min(
            (int) ($pagination['max_pages'] ?? config('statsio.data_ingestion.pagination.default_max_pages', 100)),
            $hardCap,
        );
        $requestTimeout = (int) config('statsio.data_ingestion.pagination.request_timeout_seconds', 15);
        $deadline = microtime(true) + (int) config('statsio.data_ingestion.pagination.time_budget_seconds', 90);

        $allRecords = [];
        $rowCount = 0;
        $pagesFetched = 0;
        $truncated = false;
        $stoppedReason = null;

        $currentUrl = $url;
        $currentQuery = empty($staticQuery) ? array_merge($this->firstPageQuery($pagination), $staticQuery) : $staticQuery;

        while (true) {
            if ($pagesFetched >= $maxPages) {
                $truncated = true;
                $stoppedReason = 'max_pages';
                break;
            }

            if (microtime(true) >= $deadline) {
                $truncated = true;
                $stoppedReason = 'time_budget';
                break;
            }

            try {
                $page = $this->fetchPageOrFail($currentUrl, $method, $headers, $currentQuery, $requestTimeout);
            } catch (ApiSourceFetchException $e) {
                // Une page qui échoue après retry (ex. ralentissement ponctuel d'une API publique
                // en plein import) ne doit pas faire perdre les pages déjà récupérées avec succès —
                // traité comme une troncature normale (même mécanisme que max_pages/time_budget)
                // plutôt qu'un échec total, sauf si c'est la toute première page (rien à sauver).
                if ($pagesFetched === 0) {
                    throw $e;
                }

                $truncated = true;
                $stoppedReason = 'page_fetch_error';
                break;
            }

            $this->assertResponseSize($page['raw_size']);
            $records = $this->extractRecords($page['body'], $dataPath);
            $pagesFetched++;

            $overshoot = ($rowCount + count($records)) - $maxRows;
            if ($overshoot >= 0) {
                $records = $overshoot > 0 ? array_slice($records, 0, count($records) - $overshoot) : $records;
                $rowCount += count($records);
                $this->emitPage($onPage, $allRecords, $records);
                $truncated = true;
                $stoppedReason = 'max_rows';
                break;
            }

            $rowCount += count($records);
            $this->emitPage($onPage, $allRecords, $records);

            if ($style === 'none') {
                break;
            }

            $next = match ($style) {
                'offset', 'page' => $this->nextOffsetOrPage($pagination, $currentUrl, $pagesFetched, count($records), $page['body']),
                'cursor' => $this->nextCursor($pagination, $currentUrl, $page['body']),
                'next_link' => $this->nextLink($pagination, $page['body'], $page['headers']),
                default => null,
            };

            if ($next === null) {
                break;
            }

            $currentUrl = $next['url'];
            $currentQuery = array_merge($next['query'], $staticQuery);
        }

        return [
            'records' => $allRecords,
            'truncated' => $truncated,
            'stopped_reason' => $stoppedReason,
            'pages_fetched' => $pagesFetched,
            'row_count' => $rowCount,
        ];
    }

    /**
     * @param  ?callable(array): void  $onPage
     * @param  array<int, mixed>  $allRecords
     * @param  array<int, mixed>  $records
     */
    private function emitPage(?callable $onPage, array &$allRecords, array $records): void
    {
        if ($onPage) {
            $onPage($records);

            return;
        }

        array_push($allRecords, ...$records);
    }

    /**
     * Construit la query de la première page pour une config de pagination —
     * exposée publiquement pour être réutilisée par la découverte de schéma/
     * mapping des sources "live" (FilterCapabilityProbe, CreateLiveApiDataSourceAction),
     * qui appellent HttpProbeService directement plutôt que fetchFirstPage/fetchAll.
     *
     * @param  array<string, mixed>  $pagination
     * @return array<string, mixed>
     */
    public function buildFirstPageQuery(array $pagination): array
    {
        return $this->firstPageQuery($pagination);
    }

    /**
     * Extrait le tableau d'enregistrements d'un corps de réponse déjà décodé —
     * exposé publiquement pour le même besoin que buildFirstPageQuery() ci-dessus.
     *
     * @throws ApiSourceFetchException
     */
    public function extractRecordsFromBody(array $body, ?string $dataPath): array
    {
        return $this->extractRecords($body, $dataPath);
    }

    /**
     * @param  array<string, mixed>  $pagination
     * @return array<string, mixed>
     */
    private function firstPageQuery(array $pagination): array
    {
        return match ($pagination['style'] ?? 'none') {
            'offset' => $this->numericQuery($pagination, (int) ($pagination['param_start'] ?? 0)),
            'page' => $this->numericQuery($pagination, (int) ($pagination['param_start'] ?? 1)),
            'cursor' => $this->sizeOnlyQuery($pagination),
            default => [],
        };
    }

    private function numericQuery(array $pagination, int $value): array
    {
        $query = [($pagination['param_name'] ?? 'page') => $value];

        return array_merge($query, $this->sizeOnlyQuery($pagination));
    }

    private function sizeOnlyQuery(array $pagination): array
    {
        if (empty($pagination['size_param'])) {
            return [];
        }

        return [$pagination['size_param'] => (int) ($pagination['page_size'] ?? 100)];
    }

    /**
     * @return array{url: string, query: array<string, mixed>}|null
     */
    private function nextOffsetOrPage(array $pagination, string $url, int $pagesFetched, int $lastPageRecordCount, array $body): ?array
    {
        $pageSize = (int) ($pagination['page_size'] ?? 100);

        // Une page plus courte que la taille demandée signale la fin des résultats.
        if ($pageSize > 0 && $lastPageRecordCount < $pageSize) {
            return null;
        }

        if (! empty($pagination['total_path'])) {
            $total = Arr::get($body, $pagination['total_path']);
            if (is_numeric($total)) {
                $reachedTotal = ($pagination['total_mode'] ?? 'items') === 'pages'
                    ? $pagesFetched >= (int) $total
                    : $pagesFetched * $pageSize >= (int) $total;

                if ($reachedTotal) {
                    return null;
                }
            }
        }

        $style = $pagination['style'];
        $paramName = $pagination['param_name'] ?? ($style === 'page' ? 'page' : 'offset');
        $paramStart = (int) ($pagination['param_start'] ?? ($style === 'page' ? 1 : 0));
        $nextValue = $style === 'page'
            ? $paramStart + $pagesFetched
            : $paramStart + $pagesFetched * $pageSize;

        return ['url' => $url, 'query' => array_merge([$paramName => $nextValue], $this->sizeOnlyQuery($pagination))];
    }

    /**
     * @return array{url: string, query: array<string, mixed>}|null
     */
    private function nextCursor(array $pagination, string $url, array $body): ?array
    {
        $cursor = Arr::get($body, $pagination['cursor_path'] ?? 'next_cursor');

        if ($cursor === null || $cursor === '') {
            return null;
        }

        $cursorParam = $pagination['cursor_param'] ?? 'cursor';

        return ['url' => $url, 'query' => array_merge([$cursorParam => $cursor], $this->sizeOnlyQuery($pagination))];
    }

    /**
     * @param  array<string, string>  $headers
     * @return array{url: string, query: array<string, mixed>}|null
     */
    private function nextLink(array $pagination, array $body, array $headers): ?array
    {
        if (($pagination['next_link_source'] ?? 'body') === 'header') {
            $link = $headers['Link'] ?? $headers['link'] ?? null;
            if (! $link || ! preg_match('/<([^>]+)>;\s*rel="?next"?/', $link, $matches)) {
                return null;
            }

            return ['url' => $matches[1], 'query' => []];
        }

        $next = Arr::get($body, $pagination['next_link_path'] ?? 'next_page_url');
        if (! $next || ! is_string($next)) {
            return null;
        }

        return ['url' => $next, 'query' => []];
    }

    /**
     * @throws ApiSourceFetchException
     */
    private function extractRecords(array $body, ?string $dataPath): array
    {
        $records = $dataPath ? Arr::get($body, $dataPath) : $body;

        if (is_array($records) && array_is_list($records)) {
            return array_values($records);
        }

        // Une même API peut envelopper ses réponses paginées (`{"data": [...]}`) mais renvoyer
        // un tableau JSON brut sur un endpoint différent (ex. recherche, cf. medicaments-api) —
        // on accepte ce cas plutôt que d'échouer dès que le data_path habituel ne s'applique pas.
        if (array_is_list($body)) {
            return array_values($body);
        }

        throw new ApiSourceFetchException(
            "La réponse de l'API ne contient pas de tableau d'enregistrements".($dataPath ? " au chemin '{$dataPath}'." : '.')
        );
    }

    /**
     * @throws ApiSourceFetchException
     */
    private function assertResponseSize(int $rawSize): void
    {
        $max = (int) config('statsio.data_ingestion.pagination.max_response_bytes_per_page', 20 * 1024 * 1024);

        if ($rawSize > $max) {
            throw new ApiSourceFetchException(
                'La réponse de l\'API dépasse la taille maximale autorisée par page ('.intdiv($max, 1024 * 1024).' Mo).'
            );
        }
    }

    /**
     * Convertit toute erreur de bas niveau (timeout, DNS, statut HTTP en échec, JSON
     * invalide — voir HttpProbeService::fetchPage()) en ApiSourceFetchException, pour
     * que l'appelant (contrôleur) puisse toujours répondre 422 avec un message clair
     * plutôt que de laisser fuiter une exception réseau générique en 500 opaque.
     *
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $query
     * @return array{body: array, headers: array<string, string>, status: int, raw_size: int}
     *
     * @throws ApiSourceFetchException
     */
    private function fetchPageOrFail(string $url, string $method, array $headers, array $query, int $timeoutSeconds): array
    {
        try {
            return $this->httpProbe->fetchPage($url, $method, $headers, $query, $timeoutSeconds);
        } catch (ApiSourceFetchException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ApiSourceFetchException("Impossible de contacter l'API : {$e->getMessage()}", 0, $e);
        }
    }
}
