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
use App\Services\DataIngestion\LiveApiSourceProber;

/**
 * Crée une source API "live" de façon synchrone : il n'y a ni job d'ingestion
 * ni matérialisation Parquet — le sondage complet (validation de connexion,
 * inférence de schéma, détection du mapping de filtres et des capacités) est
 * délégué à LiveApiSourceProber, partagé avec le endpoint de détection
 * pré-création (StatsDataSourceController::detectStructure).
 */
class CreateLiveApiDataSourceAction
{
    public function __construct(
        private readonly LiveApiSourceProber $prober,
        private readonly DatasetColumnPersister $columnPersister,
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
        $probed = $this->prober->probe($name, $url, $method, $headers, $dataPath, $pagination, $queryMappingOverrides);

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
                'query_mapping' => $probed['query_mapping'],
                'capabilities' => $probed['capabilities'],
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
            'row_count' => is_numeric($probed['row_count_hint']) ? (int) $probed['row_count_hint'] : 0,
        ]);

        $this->columnPersister->persist($dataset, $probed['schema'], $probed['parsed']->headers);

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

        $probed = $this->prober->probe(
            $dataSource->name,
            $url,
            $config['method'] ?? 'GET',
            $config['headers'] ?? [],
            $config['data_path'] ?? null,
            $config['pagination'] ?? ['style' => 'none'],
            $queryMappingOverrides,
        );

        $config['query_mapping'] = $probed['query_mapping'];
        $config['capabilities'] = $probed['capabilities'];
        $dataSource->update(['api_config' => $config, 'error_message' => null]);

        $dataset = $dataSource->dataset;
        if ($dataset) {
            $dataset->columns()->delete();
            $dataset->update(['row_count' => is_numeric($probed['row_count_hint']) ? (int) $probed['row_count_hint'] : 0]);
            $this->columnPersister->persist($dataset, $probed['schema'], $probed['parsed']->headers);
        }

        return $dataSource->fresh();
    }
}
