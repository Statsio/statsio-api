<?php

namespace App\Domain\DataIngestion\Actions;

use App\Domain\DataIngestion\Enums\DataSourceTypeEnum;
use App\Domain\DataIngestion\Exceptions\ApiSourceFetchException;
use App\Jobs\ProcessDataSourceJob;
use App\Models\DataIngestion\DataSource;
use App\Services\DataIngestion\PaginatedApiFetcher;
use Illuminate\Support\Facades\Storage;

/**
 * Ré-appelle l'API d'une source existante et relance le pipeline d'ingestion —
 * utilisé par le bouton "Actualiser maintenant", par la reconfiguration de
 * connexion dans UpdateDataSourceAction, et par la commande planifiée
 * (data-sources:refresh-due). Ne fait qu'une validation 1-page synchrone ;
 * la récupération complète (toutes les pages) est faite par
 * FetchApiDataSourcePagesAction dans ProcessDataSourceJob.
 */
class RefreshApiDataSourceAction
{
    public function __construct(
        private readonly PaginatedApiFetcher $fetcher,
    ) {}

    /**
     * @param  array<string, mixed>  $configOverrides  Champs api_config à fusionner avant le fetch (URL, headers, pagination, etc.)
     *
     * @throws ApiSourceFetchException
     */
    public function execute(DataSource $dataSource, array $configOverrides = []): DataSource
    {
        $config = array_merge($dataSource->api_config ?? [], $configOverrides);

        $url = $config['url'] ?? null;
        $method = $config['method'] ?? 'GET';
        $headers = $config['headers'] ?? [];
        $dataPath = $config['data_path'] ?? null;
        $pagination = $config['pagination'] ?? ['style' => 'none'];

        if (! $url) {
            throw new ApiSourceFetchException("L'URL de la source est requise.");
        }

        $this->fetcher->fetchFirstPage($url, $method, $headers, $dataPath, $pagination);

        $this->resetDatasetForReprocessing($dataSource);

        $dataSource->update([
            'type' => DataSourceTypeEnum::JSON,
            'api_config' => $config,
            'raw_storage_path' => null,
            'file_size_bytes' => 0,
            'status' => 'pending',
            'error_message' => null,
            'is_partial' => false,
            'partial_reason' => null,
        ]);

        $dataSource->scheduleNextRefresh();

        ProcessDataSourceJob::dispatch($dataSource);

        return $dataSource->fresh();
    }

    /**
     * Supprime les anciennes versions parquet (fichiers + lignes) et colonnes
     * avant de relancer le pipeline sur le même dataset — sans cela,
     * DataIngestionOrchestrator::process() tenterait de recréer la version 1,
     * ce qui violerait la contrainte unique (dataset_id, version_number).
     */
    private function resetDatasetForReprocessing(DataSource $dataSource): void
    {
        $dataset = $dataSource->dataset;
        if (! $dataset) {
            return;
        }

        $datasetsDisk = config('statsio.data_ingestion.datasets_disk', 'local');
        foreach ($dataset->versions as $version) {
            if ($version->parquet_storage_path) {
                Storage::disk($datasetsDisk)->delete($version->parquet_storage_path);
            }
        }
        $dataset->versions()->delete();
        $dataset->columns()->delete();
        $dataset->update(['status' => 'pending', 'row_count' => 0]);
    }
}
