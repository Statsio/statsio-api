<?php

namespace App\Services\DataIngestion;

use App\Domain\DataIngestion\Exceptions\ApiSourceFetchException;
use App\Services\DataIngestion\LiveQuery\FilterCapabilityProbe;
use Illuminate\Support\Arr;

/**
 * Étant donné seulement une URL de base (+ headers optionnels, pour les APIs
 * qui exigent une clé même pour sonder), détecte automatiquement la méthode
 * HTTP, l'enveloppe de réponse (`data_path`) et le style de pagination —
 * pour alimenter LiveApiSourceProber sans que l'utilisateur ait à les
 * configurer manuellement. Voir §1.2 du plan de refonte des sources API.
 *
 * N'appelle pas FilterCapabilityProbe::detect() lui-même (hormis sa méthode
 * partagée detectCountPath()) — la détection des filtres reste du ressort de
 * LiveApiSourceProber, appelée après, pour garder les budgets de requêtes
 * "structure" et "filtres" séparés.
 */
class ApiStructureDetector
{
    private const ENVELOPE_NAME_PRIORITY = ['data', 'results', 'items', 'records'];

    private const NEXT_LINK_BODY_CANDIDATES = ['next', 'next_page_url', 'next_page', 'links.next'];

    private const CURSOR_BODY_CANDIDATES = ['next_cursor', 'cursor', 'scroll_id', 'nextCursor'];

    private const SIZE_PARAM_CANDIDATES = ['size', 'per_page', 'limit', 'page_size', 'pageSize'];

    private const MAX_PAGINATION_PROBE_REQUESTS = 4;

    public function __construct(
        private readonly HttpProbeService $httpProbe,
        private readonly FilterCapabilityProbe $filterProbe,
    ) {}

    /**
     * @param  array<string, string>  $headers
     * @return array{method: string, body: array, headers: array<string, string>, data_path: ?string, data_path_confidence: string, pagination: array, pagination_confidence: string, raw_size: int, response_time_ms: int}
     *
     * @throws ApiSourceFetchException si ni GET ni POST ne renvoie de JSON exploitable
     */
    public function detect(string $url, array $headers = [], ?float $deadline = null): array
    {
        $timeout = (int) config('statsio.data_ingestion.live_query.detect_probe_request_timeout_seconds', 4);

        [$method, $page, $responseTimeMs] = $this->detectMethod($url, $headers, $timeout);

        [$dataPath, $dataPathConfidence] = $this->detectDataPath($page['body']);

        // Sans enveloppe exploitable, l'appelant court-circuite de toute façon avant
        // d'aller plus loin (voir StatsDataSourceController::detectStructure) — inutile
        // de dépenser du budget de requêtes à sonder une pagination qui ne servira pas.
        if ($dataPathConfidence === 'not_found') {
            $pagination = ['style' => 'none'];
            $paginationConfidence = 'none';
        } else {
            [$pagination, $paginationConfidence] = $this->detectPagination(
                $url, $method, $headers, $dataPath, $page['body'], $page['headers'], $timeout, $deadline,
            );
        }

        return [
            'method' => $method,
            'body' => $page['body'],
            'headers' => $page['headers'],
            'data_path' => $dataPath,
            'data_path_confidence' => $dataPathConfidence,
            'pagination' => $pagination,
            'pagination_confidence' => $paginationConfidence,
            'raw_size' => $page['raw_size'],
            'response_time_ms' => $responseTimeMs,
        ];
    }

    /**
     * @param  array<string, string>  $headers
     * @return array{0: string, 1: array{body: array, headers: array, status: int, raw_size: int}, 2: int}
     *
     * @throws ApiSourceFetchException
     */
    private function detectMethod(string $url, array $headers, int $timeout): array
    {
        $commonQueries = [
            [], // First try empty query first
            ['page' => 1],
            ['offset' => 0],
            ['limit' => 100],
            ['pageSize' => 100],
        ];

        foreach (['GET', 'POST'] as $method) {
            foreach ($commonQueries as $query) {
                $startedAt = microtime(true);
                try {
                    $page = $this->httpProbe->fetchPage($url, $method, $headers, $query, $timeout);
                    return [$method, $page, $this->elapsedMs($startedAt)];
                } catch (\Throwable) {
                    // Continue to next query/method if this one fails
                }
            }
        }

        throw new ApiSourceFetchException(
            "Impossible d'interroger cette API en GET ou en POST avec les paramètres de pagination courants.",
        );
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    /**
     * @return array{0: ?string, 1: string} [data_path, confidence]
     */
    private function detectDataPath(array $body): array
    {
        if (array_is_list($body)) {
            return [null, 'high'];
        }

        $depth0 = $this->collectRecordListCandidates($body);
        if (! empty($depth0['candidates'])) {
            return $this->pickCandidate($depth0['candidates']);
        }

        $depth1Candidates = [];
        foreach ($body as $key => $value) {
            if (is_array($value) && ! array_is_list($value)) {
                $nested = $this->collectRecordListCandidates($value);
                foreach ($nested['candidates'] as $childKey => $childValue) {
                    $depth1Candidates["{$key}.{$childKey}"] = $childValue;
                }
            }
        }
        if (! empty($depth1Candidates)) {
            return $this->pickCandidate($depth1Candidates);
        }

        if ($depth0['empty_fallback'] !== null) {
            return [$depth0['empty_fallback'], 'empty_response'];
        }

        return [null, 'not_found'];
    }

    /**
     * Ne considère que les enfants directs de $arr (une seule profondeur).
     *
     * @return array{candidates: array<string, array>, empty_fallback: ?string}
     */
    private function collectRecordListCandidates(array $arr): array
    {
        $candidates = [];
        $emptyFallback = null;

        foreach ($arr as $key => $value) {
            if (! is_string($key) || ! is_array($value)) {
                continue;
            }

            if ($this->isRecordList($value)) {
                $candidates[$key] = $value;
            } elseif (array_is_list($value) && count($value) === 0 && $emptyFallback === null) {
                $emptyFallback = $key;
            }
        }

        return ['candidates' => $candidates, 'empty_fallback' => $emptyFallback];
    }

    private function isRecordList(array $value): bool
    {
        return array_is_list($value) && count($value) > 0 && is_array($value[0]);
    }

    /**
     * @param  array<string, array>  $candidates
     * @return array{0: string, 1: string} [chemin gagnant, confiance]
     */
    private function pickCandidate(array $candidates): array
    {
        if (count($candidates) === 1) {
            return [array_key_first($candidates), 'high'];
        }

        foreach (self::ENVELOPE_NAME_PRIORITY as $conventionalName) {
            foreach (array_keys($candidates) as $path) {
                $lastSegment = str_contains($path, '.') ? substr($path, strrpos($path, '.') + 1) : $path;
                if ($lastSegment === $conventionalName) {
                    return [$path, 'medium'];
                }
            }
        }

        return [array_key_first($candidates), 'low'];
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $responseHeaders
     * @return array{0: array<string, mixed>, 1: string} [config pagination, confiance]
     */
    private function detectPagination(string $url, string $method, array $headers, ?string $dataPath, array $body, array $responseHeaders, int $timeout, ?float $deadline): array
    {
        foreach (self::NEXT_LINK_BODY_CANDIDATES as $path) {
            $value = Arr::get($body, $path);
            if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) {
                return [
                    ['style' => 'next_link', 'next_link_source' => 'body', 'next_link_path' => $path],
                    'guessed',
                ];
            }
        }

        $link = $responseHeaders['Link'] ?? $responseHeaders['link'] ?? null;
        if ($link && preg_match('/<([^>]+)>;\s*rel="?next"?/', $link)) {
            return [
                ['style' => 'next_link', 'next_link_source' => 'header'],
                'guessed',
            ];
        }

        foreach (self::CURSOR_BODY_CANDIDATES as $path) {
            $value = Arr::get($body, $path);
            if ($value !== null && $value !== '') {
                return [
                    ['style' => 'cursor', 'cursor_path' => $path, 'cursor_param' => 'cursor', 'page_size' => 100],
                    'guessed',
                ];
            }
        }

        $countPath = $this->filterProbe->detectCountPath($body);
        if ($countPath !== null) {
            if ($deadline === null || microtime(true) < $deadline) {
                $confirmed = $this->probeOffsetPagination($url, $method, $headers, $dataPath, $timeout, $deadline, $countPath);
                if ($confirmed !== null) {
                    return [$confirmed, 'confirmed'];
                }
            }

            return [
                ['style' => 'page', 'param_name' => 'page', 'param_start' => 1, 'size_param' => 'size', 'page_size' => 100, 'total_path' => $countPath, 'total_mode' => 'items'],
                'guessed',
            ];
        }

        return [['style' => 'none'], 'none'];
    }

    /**
     * Teste, dans l'ordre, les noms de paramètre de taille de page candidats en
     * demandant une petite taille (5) et en vérifiant que le nombre d'enregistrements
     * retournés vaut bien 5 — preuve que le paramètre est honoré plutôt que deviné.
     *
     * @param  array<string, string>  $headers
     */
    private function probeOffsetPagination(string $url, string $method, array $headers, ?string $dataPath, int $timeout, ?float $deadline, string $countPath): ?array
    {
        $requestsUsed = 0;

        foreach (self::SIZE_PARAM_CANDIDATES as $sizeParam) {
            if ($requestsUsed >= self::MAX_PAGINATION_PROBE_REQUESTS || ($deadline !== null && microtime(true) >= $deadline)) {
                break;
            }

            try {
                $page = $this->httpProbe->fetchPage($url, $method, $headers, [$sizeParam => 5], $timeout);
                $requestsUsed++;

                $records = $dataPath !== null ? Arr::get($page['body'], $dataPath) : $page['body'];
                if (is_array($records) && array_is_list($records) && count($records) === 5) {
                    return [
                        'style' => 'page', 'param_name' => 'page', 'param_start' => 1,
                        'size_param' => $sizeParam, 'page_size' => 100, 'total_path' => $countPath, 'total_mode' => 'items',
                    ];
                }
            } catch (\Throwable) {
                $requestsUsed++;
            }
        }

        return null;
    }
}
