<?php

namespace App\Domain\DataIngestion\Actions;

use App\Domain\DataIngestion\Enums\DataSourceMaterializationEnum;
use App\Domain\DataIngestion\Enums\DataSourceRefreshFrequencyEnum;
use App\Domain\DataIngestion\Enums\DataSourceTypeEnum;
use App\Domain\DataIngestion\Exceptions\ApiSourceFetchException;
use App\Jobs\ProcessDataSourceJob;
use App\Models\DataIngestion\Dataset;
use App\Models\DataIngestion\DataSource;
use App\Models\User\User;
use App\Services\DataIngestion\PaginatedApiFetcher;
use Carbon\CarbonImmutable;

class CreateApiDataSourceAction
{
    public function __construct(
        private readonly PaginatedApiFetcher $fetcher,
    ) {}

    /**
     * Valide la connexion avec un seul appel HTTP (1 page) et crée la source
     * en statut "pending" — la récupération complète (toutes les pages, si
     * la source est paginée) est effectuée en tâche de fond par
     * FetchApiDataSourcePagesAction, appelée depuis ProcessDataSourceJob.
     *
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $pagination
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
        DataSourceRefreshFrequencyEnum $refreshFrequency = DataSourceRefreshFrequencyEnum::NONE,
        array $pagination = ['style' => 'none'],
    ): DataSource {
        $this->fetcher->fetchFirstPage($url, $method, $headers, $dataPath, $pagination);

        $now = CarbonImmutable::now();

        $dataSource = DataSource::create([
            'user_id' => $user->id,
            'name' => $name,
            'type' => DataSourceTypeEnum::JSON,
            'source_kind' => 'api',
            'materialization' => DataSourceMaterializationEnum::SNAPSHOT,
            'api_config' => [
                'url' => $url,
                'method' => strtoupper($method),
                'auth_type' => $authType,
                'headers' => $headers,
                'data_path' => $dataPath,
                'pagination' => $pagination,
            ],
            'refresh_frequency' => $refreshFrequency,
            'last_refreshed_at' => $now,
            'next_refresh_at' => $refreshFrequency->nextOccurrenceFrom($now),
            'original_filename' => "{$name}.json",
            'raw_storage_path' => null,
            'file_size_bytes' => 0,
            'status' => 'pending',
            'visibility' => $visibility,
            'categories' => $categories,
            'provenance_id' => $provenanceId,
            'provenance_other_label' => $provenanceOtherLabel,
        ]);

        Dataset::create([
            'data_source_id' => $dataSource->id,
            'user_id' => $dataSource->user_id,
            'name' => $dataSource->name,
            'status' => 'pending',
            'row_count' => 0,
        ]);

        ProcessDataSourceJob::dispatch($dataSource);

        return $dataSource;
    }
}
