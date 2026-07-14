<?php

namespace App\Domain\DataIngestion\Actions;

use App\Domain\DataIngestion\Enums\DataSourceMaterializationEnum;
use App\Domain\DataIngestion\Enums\DataSourceRefreshFrequencyEnum;
use App\Domain\DataIngestion\Enums\DataSourceTypeEnum;
use App\Domain\DataIngestion\Exceptions\ApiSourceFetchException;
use App\Models\DataIngestion\Dataset;
use App\Models\DataIngestion\DataSource;
use App\Models\User\User;
use App\Services\DataIngestion\DatasetColumnPersister;
use App\Services\DataIngestion\HttpProbeService;
use App\Services\DataIngestion\LiveQuery\FilterCapabilityProbe;
use App\Services\DataIngestion\PaginatedApiFetcher;
use App\Services\DataIngestion\Parsers\JsonParser;
use App\Services\DataIngestion\SchemaInferenceService;
use Illuminate\Support\Arr;

/**
 * Crée une source API "live" de façon synchrone : contrairement à
 * CreateApiDataSourceAction (source snapshot), il n'y a ni job d'ingestion ni
 * matérialisation Parquet — un seul appel HTTP sert à la fois de validation
 * de connexion, d'échantillon pour l'inférence de schéma, et de base pour la
 * détection automatique du mapping de filtres (FilterCapabilityProbe).
 */
class CreateLiveApiDataSourceAction
{
    private const SAMPLE_ROWS = 200;

    public function __construct(
        private readonly HttpProbeService $httpProbe,
        private readonly PaginatedApiFetcher $fetcher,
        private readonly SchemaInferenceService $schemaInferenceService,
        private readonly DatasetColumnPersister $columnPersister,
        private readonly FilterCapabilityProbe $filterProbe,
    ) {}

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $pagination
     * @param  array<string, mixed>|null  $queryMappingOverrides  Corrections manuelles de l'utilisateur, prioritaires sur la détection automatique
     *
     * @throws ApiSourceFetchException
     */
    public function execute(
        User $user,
        string $name,
        string $url,
        string $method,
        array $headers,
        ?string $dataPath,
        string $authType = 'none',
        string $visibility = 'private',
        array $categories = [],
        ?int $provenanceId = null,
        ?string $provenanceOtherLabel = null,
        array $pagination = ['style' => 'none'],
        ?array $queryMappingOverrides = null,
    ): DataSource {
        [$schema, $parsed, $queryMapping, $rowCountHint] = $this->probe($name, $url, $method, $headers, $dataPath, $pagination, $queryMappingOverrides);

        $dataSource = DataSource::create([
            'user_id' => $user->id,
            'name' => $name,
            'type' => DataSourceTypeEnum::JSON,
            'source_kind' => 'api',
            'materialization' => DataSourceMaterializationEnum::LIVE,
            'api_config' => [
                'url' => $url,
                'method' => strtoupper($method),
                'auth_type' => $authType,
                'headers' => $headers,
                'data_path' => $dataPath,
                'pagination' => $pagination,
                'query_mapping' => $queryMapping,
            ],
            'refresh_frequency' => DataSourceRefreshFrequencyEnum::NONE,
            'original_filename' => "{$name}.json",
            'raw_storage_path' => null,
            'file_size_bytes' => 0,
            'status' => 'ready',
            'visibility' => $visibility,
            'categories' => $categories,
            'provenance_id' => $provenanceId,
            'provenance_other_label' => $provenanceOtherLabel,
        ]);

        $dataset = Dataset::create([
            'data_source_id' => $dataSource->id,
            'user_id' => $dataSource->user_id,
            'name' => $dataSource->name,
            'status' => 'ready',
            'parquet_path' => null,
            'row_count' => is_numeric($rowCountHint) ? (int) $rowCountHint : 0,
        ]);

        $this->columnPersister->persist($dataset, $schema, $parsed->headers);

        return $dataSource;
    }

    /**
     * Reconfigure une source "live" existante : ré-appelle l'API avec la config
     * fusionnée (URL/headers/pagination éventuellement modifiés), ré-infère le
     * schéma et redétecte le mapping de filtres, puis remplace les colonnes —
     * synchrone, comme la création, sans job d'ingestion.
     *
     * @param  array<string, mixed>  $configOverrides  Champs api_config à fusionner avant le probe (url, headers, pagination, etc.)
     * @param  array<string, mixed>|null  $queryMappingOverrides
     *
     * @throws ApiSourceFetchException
     */
    public function reconfigure(DataSource $dataSource, array $configOverrides = [], ?array $queryMappingOverrides = null): DataSource
    {
        $config = array_merge($dataSource->api_config ?? [], $configOverrides);

        $url = $config['url'] ?? null;
        if (! $url) {
            throw new ApiSourceFetchException("L'URL de la source est requise.");
        }

        [$schema, $parsed, $queryMapping, $rowCountHint] = $this->probe(
            $dataSource->name,
            $url,
            $config['method'] ?? 'GET',
            $config['headers'] ?? [],
            $config['data_path'] ?? null,
            $config['pagination'] ?? ['style' => 'none'],
            $queryMappingOverrides,
        );

        $config['query_mapping'] = $queryMapping;
        $dataSource->update(['api_config' => $config, 'error_message' => null]);

        $dataset = $dataSource->dataset;
        if ($dataset) {
            $dataset->columns()->delete();
            $dataset->update(['row_count' => is_numeric($rowCountHint) ? (int) $rowCountHint : 0]);
            $this->columnPersister->persist($dataset, $schema, $parsed->headers);
        }

        return $dataSource->fresh();
    }

    /**
     * Un seul appel HTTP sert à la fois de validation de connexion, d'échantillon
     * pour l'inférence de schéma, et de base pour la détection automatique du
     * mapping de filtres — partagé entre execute() (création) et reconfigure().
     *
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $pagination
     * @param  array<string, mixed>|null  $queryMappingOverrides
     * @return array{0: array, 1: \App\Domain\DataIngestion\DTOs\ParsedFileDTO, 2: array, 3: mixed}
     *
     * @throws ApiSourceFetchException
     */
    private function probe(string $name, string $url, string $method, array $headers, ?string $dataPath, array $pagination, ?array $queryMappingOverrides): array
    {
        $query = $this->fetcher->buildFirstPageQuery($pagination);
        $timeout = (int) config('statsio.data_ingestion.pagination.request_timeout_seconds', 15);

        try {
            $page = $this->httpProbe->fetchPage($url, $method, $headers, $query, $timeout);
        } catch (\Throwable $e) {
            throw new ApiSourceFetchException("Impossible de se connecter à l'API : {$e->getMessage()}", 0, $e);
        }

        $records = $this->fetcher->extractRecordsFromBody($page['body'], $dataPath);
        $parsed = JsonParser::fromRecords($records, self::SAMPLE_ROWS, $name);
        $schema = $this->schemaInferenceService->infer($parsed);

        $detectedMapping = $this->filterProbe->detect($url, $method, $headers, $dataPath, $pagination, $page['body'], $schema, $parsed->rows);
        $queryMapping = $queryMappingOverrides
            ? array_replace_recursive($detectedMapping, $queryMappingOverrides)
            : $detectedMapping;

        $rowCountHint = $queryMapping['count_path'] ? Arr::get($page['body'], $queryMapping['count_path']) : null;

        return [$schema, $parsed, $queryMapping, $rowCountHint];
    }
}
