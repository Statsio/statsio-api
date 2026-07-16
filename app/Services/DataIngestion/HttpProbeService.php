<?php

namespace App\Services\DataIngestion;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class HttpProbeService
{
    private const TIMEOUT_SECONDS = 10;

    /**
     * Calls an external URL and returns the decoded JSON body.
     *
     * @param  array<string, string>  $headers
     *
     * @throws \RuntimeException if the request fails or the response isn't JSON.
     */
    public function fetch(string $url, string $method, array $headers = []): array
    {
        $response = Http::withHeaders($headers)
            ->timeout(self::TIMEOUT_SECONDS)
            ->send(strtoupper($method), $url);

        if ($response->failed()) {
            throw new \RuntimeException(
                "La requête a échoué avec le statut {$response->status()}."
            );
        }

        $decoded = $response->json();

        if (! is_array($decoded)) {
            throw new \RuntimeException('La réponse ne contient pas de JSON valide.');
        }

        return $decoded;
    }

    /**
     * Lightweight connectivity check used by the "Tester la connexion" button —
     * only verifies the endpoint answers with a non-error status.
     */
    public function probe(string $url, string $method, array $headers = []): void
    {
        $response = Http::withHeaders($headers)
            ->timeout(self::TIMEOUT_SECONDS)
            ->send(strtoupper($method), $url);

        if ($response->failed()) {
            throw new \RuntimeException(
                "La requête a échoué avec le statut {$response->status()}."
            );
        }
    }

    /**
     * Bas niveau : une requête, avec accès aux headers de réponse (nécessaire
     * pour lire un header `Link: <url>; rel="next"`) et un timeout configurable —
     * utilisé par PaginatedApiFetcher pour boucler sur plusieurs pages.
     *
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $query
     * @return array{body: array, headers: array<string, string>, status: int, raw_size: int}
     *
     * @throws \RuntimeException si la requête échoue ou si le corps n'est pas du JSON valide.
     */
    public function fetchPage(string $url, string $method, array $headers, array $query, int $timeoutSeconds): array
    {
        // Guzzle remplace (et non fusionne) la query string de l'URI avec l'option 'query' —
        // on fusionne donc nous-mêmes $query dans la query string déjà présente dans $url
        // (ex. "?size=5000" saisi par l'utilisateur, ou des filtres persistants appliqués à
        // chaque page d'une agrégation en streaming) et on passe l'URL déjà complète, sans
        // jamais utiliser l'option 'query' qui écraserait l'existant.
        $url = $query !== [] ? $this->mergeQueryIntoUrl($url, $query) : $url;
        $retryTimes = (int) config('statsio.data_ingestion.pagination.page_retry_times', 2);
        $retryDelayMs = (int) config('statsio.data_ingestion.pagination.page_retry_delay_ms', 500);

        // Un ralentissement ponctuel d'une seule page (timeout, DNS, connexion refusée) ne
        // doit pas faire échouer tout un import qui a déjà récupéré plusieurs pages avec
        // succès — retry avec backoff avant de laisser l'échec remonter à l'appelant.
        $response = Http::withHeaders($headers)
            ->timeout($timeoutSeconds)
            ->retry($retryTimes, $retryDelayMs)
            ->send(strtoupper($method), $url);

        if ($response->failed()) {
            throw new \RuntimeException(
                "La requête a échoué avec le statut {$response->status()}."
            );
        }

        $decoded = $response->json();

        if (! is_array($decoded)) {
            throw new \RuntimeException('La réponse ne contient pas de JSON valide.');
        }

        return [
            'body' => $decoded,
            'headers' => array_map(fn ($v) => is_array($v) ? implode(', ', $v) : $v, $response->headers()),
            'status' => $response->status(),
            'raw_size' => strlen($response->body()),
        ];
    }

    /**
     * Comme fetchPage(), mais exécute une requête par entrée de $queriesByKey vers
     * la même URL de base EN PARALLÈLE (pool de requêtes async), au lieu de les
     * enchaîner une par une — utilisé par la recherche multi-colonnes d'une source
     * live, qui attendait sinon la somme des latences de chaque colonne recherchée
     * (jusqu'à ~25s chacune) plutôt que le max de toutes.
     *
     * @param  array<string, string>  $headers
     * @param  array<array-key, array<string, mixed>>  $queriesByKey
     * @return array<array-key, array{body: array, headers: array<string, string>, status: int, raw_size: int}>
     *
     * @throws \RuntimeException si une des requêtes échoue ou ne renvoie pas de JSON valide.
     */
    public function fetchPages(string $url, string $method, array $headers, array $queriesByKey, int $timeoutSeconds): array
    {
        $retryTimes = (int) config('statsio.data_ingestion.pagination.page_retry_times', 2);
        $retryDelayMs = (int) config('statsio.data_ingestion.pagination.page_retry_delay_ms', 500);

        $urlsByKey = [];
        foreach ($queriesByKey as $key => $query) {
            $urlsByKey[$key] = $query !== [] ? $this->mergeQueryIntoUrl($url, $query) : $url;
        }

        $responses = Http::pool(fn (Pool $pool) => collect($urlsByKey)->map(
            fn (string $pooledUrl, $key) => $pool->as($key)
                ->withHeaders($headers)
                ->timeout($timeoutSeconds)
                ->retry($retryTimes, $retryDelayMs)
                ->send(strtoupper($method), $pooledUrl)
        )->all());

        $results = [];
        foreach ($responses as $key => $response) {
            if ($response instanceof RequestException && in_array($response->response->status(), [400, 404], true)) {
                // Convention REST courante pour une recherche/un filtre sans résultat (404) ou
                // une valeur rejetée par l'upstream (400, ex. accents/caractères non supportés) :
                // traité comme une page vide pour cette clé, pas une erreur qui ferait échouer le lot.
                $results[$key] = ['body' => [], 'headers' => [], 'status' => $response->response->status(), 'raw_size' => 0];

                continue;
            }

            if ($response instanceof \Throwable) {
                throw new \RuntimeException("La requête a échoué : {$response->getMessage()}", 0, $response);
            }

            if ($response->failed()) {
                throw new \RuntimeException("La requête a échoué avec le statut {$response->status()}.");
            }

            $decoded = $response->json();
            if (! is_array($decoded)) {
                throw new \RuntimeException('La réponse ne contient pas de JSON valide.');
            }

            $results[$key] = [
                'body' => $decoded,
                'headers' => array_map(fn ($v) => is_array($v) ? implode(', ', $v) : $v, $response->headers()),
                'status' => $response->status(),
                'raw_size' => strlen($response->body()),
            ];
        }

        return $results;
    }

    /**
     * Fusionne $query dans la query string déjà présente dans $url (les clés de $query
     * gagnent en cas de collision) — évite de dépendre du comportement "replace" de
     * l'option 'query' de Guzzle, qui perdrait soit les paramètres déjà dans $url
     * (si $query est vide) soit ceux ajoutés côté serveur dans un lien "next" paginé
     * (si $query est non-vide mais qu'on utilisait l'option au lieu de fusionner ici).
     *
     * @param  array<string, mixed>  $query
     */
    private function mergeQueryIntoUrl(string $url, array $query): string
    {
        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $existing);
        $merged = array_merge($existing, $query);

        $rebuilt = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');
        if (isset($parts['port'])) {
            $rebuilt .= ':'.$parts['port'];
        }
        $rebuilt .= $parts['path'] ?? '';
        if ($merged !== []) {
            $rebuilt .= '?'.http_build_query($merged, '', '&', PHP_QUERY_RFC3986);
        }
        if (isset($parts['fragment'])) {
            $rebuilt .= '#'.$parts['fragment'];
        }

        return $rebuilt;
    }
}
