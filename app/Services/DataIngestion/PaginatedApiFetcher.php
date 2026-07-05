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
 * réservée au job asynchrone — voir FetchApiDataSourcePagesAction.
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
        $page = $this->httpProbe->fetchPage($url, $method, $headers, $this->firstPageQuery($pagination), $timeout);
        $this->assertResponseSize($page['raw_size']);

        return ['records' => $this->extractRecords($page['body'], $dataPath)];
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $pagination
     * @return array{records: array, truncated: bool, stopped_reason: ?string, pages_fetched: int}
     *
     * @throws ApiSourceFetchException
     */
    public function fetchAll(string $url, string $method, array $headers, ?string $dataPath, array $pagination): array
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
        $pagesFetched = 0;
        $truncated = false;
        $stoppedReason = null;

        $currentUrl = $url;
        $currentQuery = $this->firstPageQuery($pagination);

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

            $page = $this->httpProbe->fetchPage($currentUrl, $method, $headers, $currentQuery, $requestTimeout);
            $this->assertResponseSize($page['raw_size']);
            $records = $this->extractRecords($page['body'], $dataPath);
            $pagesFetched++;
            array_push($allRecords, ...$records);

            if (count($allRecords) >= $maxRows) {
                $allRecords = array_slice($allRecords, 0, $maxRows);
                $truncated = true;
                $stoppedReason = 'max_rows';
                break;
            }

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
            $currentQuery = $next['query'];
        }

        return [
            'records' => $allRecords,
            'truncated' => $truncated,
            'stopped_reason' => $stoppedReason,
            'pages_fetched' => $pagesFetched,
        ];
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

        if (! is_array($records) || ! array_is_list($records)) {
            throw new ApiSourceFetchException(
                "La réponse de l'API ne contient pas de tableau d'enregistrements".($dataPath ? " au chemin '{$dataPath}'." : '.')
            );
        }

        return array_values($records);
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
}
