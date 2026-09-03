<?php

namespace App\Services\DataIngestion\DataGouv;

use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Client de lecture du catalogue data.gouv.fr, utilisé par le wizard
 * « Ajouter une source » pour rechercher un jeu de données puis importer
 * l'une de ses ressources sans quitter Statsio.
 *
 * Ne fait qu'exposer, sous une forme normalisée et mise en cache, l'API
 * publique data.gouv (`/api/1/datasets`) et l'API tabulaire
 * (`tabular-api.data.gouv.fr`) qui indique quelles ressources sont
 * réellement requêtables — c'est cette dernière URL que Statsio ingère.
 */
class DataGouvCatalogClient
{
    /** Formats de ressource susceptibles d'être exposés par l'API tabulaire. */
    private const TABULAR_FORMATS = ['csv', 'xlsx', 'xls', 'parquet', 'ods'];

    private string $catalogBaseUrl;

    private string $tabularApiBaseUrl;

    private string $siteBaseUrl;

    private int $timeout;

    private int $cacheTtl;

    public function __construct()
    {
        $config = config('statsio.data_ingestion.data_gouv');
        $this->catalogBaseUrl = rtrim($config['catalog_base_url'], '/');
        $this->tabularApiBaseUrl = rtrim($config['tabular_api_base_url'], '/');
        $this->siteBaseUrl = rtrim($config['site_base_url'], '/');
        $this->timeout = (int) $config['timeout_seconds'];
        $this->cacheTtl = (int) $config['cache_ttl_seconds'];
    }

    /**
     * Recherche de jeux de données par mot-clé.
     *
     * @return array{total: int, page: int, page_size: int, datasets: array<int, array<string, mixed>>}
     */
    public function searchDatasets(string $query, int $page = 1, int $pageSize = 20): array
    {
        $query = trim($query);
        $page = max(1, $page);
        $pageSize = max(1, min(50, $pageSize));

        $cacheKey = "datagouv:search:{$pageSize}:{$page}:".md5(Str::lower($query));

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($query, $page, $pageSize) {
            $response = Http::acceptJson()
                ->timeout($this->timeout)
                ->get("{$this->catalogBaseUrl}/datasets/", [
                    'q' => $query,
                    'page' => $page,
                    'page_size' => $pageSize,
                ]);

            $response->throw();

            $body = $response->json();

            return [
                'total' => (int) ($body['total'] ?? 0),
                'page' => $page,
                'page_size' => $pageSize,
                'datasets' => array_map(
                    fn (array $dataset) => $this->normalizeSummary($dataset),
                    $body['data'] ?? [],
                ),
            ];
        });
    }

    /**
     * Détail d'un jeu de données à partir d'un id, d'un slug ou d'une URL data.gouv.
     * Chaque ressource au format tabulaire est testée contre l'API tabulaire pour
     * savoir si elle est requêtable (`tabular_available`).
     *
     * @return array<string, mixed>|null null si le jeu de données est introuvable
     */
    public function getDataset(string $ref): ?array
    {
        [$identifier, $preselectResourceId] = $this->parseDatasetRef($ref);

        if ($identifier === '') {
            return null;
        }

        $cacheKey = 'datagouv:dataset:'.md5(Str::lower($identifier));

        $dataset = Cache::remember($cacheKey, $this->cacheTtl, function () use ($identifier) {
            $response = Http::acceptJson()
                ->timeout($this->timeout)
                ->get("{$this->catalogBaseUrl}/datasets/{$identifier}/");

            if ($response->status() === 404) {
                return null;
            }

            $response->throw();

            return $this->normalizeDetail($response->json());
        });

        if ($dataset === null) {
            return null;
        }

        $dataset['preselect_resource_id'] = $preselectResourceId;

        return $dataset;
    }

    /**
     * @param  array<string, mixed>  $dataset
     * @return array<string, mixed>
     */
    private function normalizeSummary(array $dataset): array
    {
        $slug = $dataset['slug'] ?? $dataset['id'] ?? '';

        return [
            'id' => $dataset['id'] ?? null,
            'slug' => $slug,
            'title' => $dataset['title'] ?? 'Sans titre',
            'page_url' => $dataset['page'] ?? "{$this->siteBaseUrl}/fr/datasets/{$slug}/",
            'organization' => $this->normalizeOrganization($dataset),
            'last_update' => $dataset['last_update'] ?? null,
            'resources_count' => is_array($dataset['resources'] ?? null)
                ? count($dataset['resources'])
                : (int) ($dataset['resources_count'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $dataset
     * @return array<string, mixed>
     */
    private function normalizeDetail(array $dataset): array
    {
        $slug = $dataset['slug'] ?? $dataset['id'] ?? '';
        $resources = array_values(array_filter(
            $dataset['resources'] ?? [],
            'is_array',
        ));

        $tabularCandidateIds = [];
        foreach ($resources as $resource) {
            if ($this->looksTabular($resource)) {
                $tabularCandidateIds[] = $resource['id'];
            }
        }

        $availability = $this->probeTabularAvailability($tabularCandidateIds);

        return [
            'id' => $dataset['id'] ?? null,
            'slug' => $slug,
            'title' => $dataset['title'] ?? 'Sans titre',
            'description' => $dataset['description'] ?? null,
            'page_url' => $dataset['page'] ?? "{$this->siteBaseUrl}/fr/datasets/{$slug}/",
            'organization' => $this->normalizeOrganization($dataset),
            'last_update' => $dataset['last_update'] ?? null,
            'resources' => array_map(function (array $resource) use ($availability) {
                $id = $resource['id'] ?? '';
                $available = $availability[$id] ?? false;

                return [
                    'id' => $id,
                    'title' => $resource['title'] ?? 'Ressource sans titre',
                    'format' => Str::lower((string) ($resource['format'] ?? '')),
                    'filesize' => $resource['filesize'] ?? null,
                    'tabular_available' => $available,
                    'tabular_url' => $available
                        ? "{$this->tabularApiBaseUrl}/resources/{$id}/data/"
                        : null,
                ];
            }, $resources),
        ];
    }

    /**
     * @param  array<string, mixed>  $dataset
     * @return array{name: string|null, page_url: string|null}
     */
    private function normalizeOrganization(array $dataset): array
    {
        $org = $dataset['organization'] ?? null;

        if (! is_array($org)) {
            return ['name' => null, 'page_url' => null];
        }

        return [
            'name' => $org['name'] ?? null,
            'page_url' => $org['page'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    private function looksTabular(array $resource): bool
    {
        if (empty($resource['id'])) {
            return false;
        }

        return in_array(Str::lower((string) ($resource['format'] ?? '')), self::TABULAR_FORMATS, true);
    }

    /**
     * Teste en parallèle si chaque ressource est exposée par l'API tabulaire
     * (`GET /api/resources/{id}/` → 200 disponible, 404 sinon).
     *
     * @param  array<int, string>  $resourceIds
     * @return array<string, bool>
     */
    private function probeTabularAvailability(array $resourceIds): array
    {
        if ($resourceIds === []) {
            return [];
        }

        $responses = Http::pool(fn (Pool $pool) => array_map(
            fn (string $id) => $pool->as($id)
                ->acceptJson()
                ->timeout($this->timeout)
                ->get("{$this->tabularApiBaseUrl}/resources/{$id}/"),
            $resourceIds,
        ));

        $result = [];
        foreach ($resourceIds as $id) {
            $response = $responses[$id] ?? null;
            $result[$id] = $response !== null
                && ! ($response instanceof \Throwable)
                && $response->successful();
        }

        return $result;
    }

    /**
     * Extrait un identifiant utilisable (id ou slug) depuis un id nu, un slug ou
     * une URL data.gouv, et repère un éventuel fragment `#/resources/{id}` pour
     * pré-sélectionner la bonne ressource.
     *
     * @return array{0: string, 1: string|null}
     */
    private function parseDatasetRef(string $ref): array
    {
        $ref = trim($ref);

        if ($ref === '') {
            return ['', null];
        }

        $preselectResourceId = null;
        if (preg_match('#resources/([0-9a-f-]{16,})#i', $ref, $m)) {
            $preselectResourceId = Str::lower($m[1]);
        }

        // Pas une URL : id ou slug fourni directement.
        if (! Str::contains($ref, '/') && ! Str::startsWith($ref, 'http')) {
            return [$ref, $preselectResourceId];
        }

        $path = parse_url($ref, PHP_URL_PATH) ?: $ref;
        $path = trim($path, '/');

        // .../datasets/{slug-ou-id}[/...]
        if (preg_match('#datasets/([^/]+)#i', $path, $m)) {
            return [urldecode($m[1]), $preselectResourceId];
        }

        // Dernier segment en repli.
        $segments = array_values(array_filter(explode('/', $path)));

        return [$segments === [] ? '' : urldecode(end($segments)), $preselectResourceId];
    }
}
