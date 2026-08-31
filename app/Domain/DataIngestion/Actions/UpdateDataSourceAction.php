<?php

namespace App\Domain\DataIngestion\Actions;

use App\Domain\DataIngestion\Enums\DataSourceTypeEnum;
use App\Domain\DataIngestion\Exceptions\ApiSourceFetchException;
use App\Domain\DataIngestion\Exceptions\UnsupportedFileTypeException;
use App\Jobs\ProcessDataSourceJob;
use App\Jobs\ProcessParquetJob;
use App\Models\DataIngestion\DataSource;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UpdateDataSourceAction
{
    private const API_FIELDS = ['url', 'method', 'auth_type', 'headers', 'data_path', 'pagination'];

    public function __construct(
        private readonly RefreshApiDataSourceAction $refreshApiAction,
        private readonly CreateLiveApiDataSourceAction $createLiveApiAction,
    ) {}

    /**
     * Met à jour le nom/visibilité/provenance d'une source existante, et — si
     * fourni — remplace son fichier (source "upload") ou reconfigure/relance
     * sa connexion (source "api"). Les deux derniers cas relancent le pipeline
     * d'ingestion, la donnée précédente (colonnes/versions parquet) est donc
     * réinitialisée avant de dispatcher le job.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ApiSourceFetchException
     * @throws UnsupportedFileTypeException
     */
    public function execute(DataSource $dataSource, array $attributes, ?UploadedFile $file = null): DataSource
    {
        $metaFields = Arr::only($attributes, ['name', 'visibility', 'categories', 'provenance_id', 'provenance_other_label']);

        if (! empty($metaFields)) {
            $dataSource->update($metaFields);

            if (array_key_exists('name', $metaFields) && $dataSource->dataset) {
                $dataSource->dataset->update(['name' => $metaFields['name']]);
            }
        }

        if (array_key_exists('refresh_frequency', $attributes) && $dataSource->source_kind === 'api' && ! $dataSource->isLive()) {
            $dataSource->update(['refresh_frequency' => $attributes['refresh_frequency']]);
            $dataSource->scheduleNextRefresh($dataSource->last_refreshed_at ? CarbonImmutable::instance($dataSource->last_refreshed_at) : null);
        }

        if ($dataSource->source_kind === 'upload' && $file) {
            $this->replaceFile($dataSource, $file, Arr::only($attributes, ['sheet_name', 'header_row', 'excluded_rows']));
        } elseif ($dataSource->source_kind === 'api' && $dataSource->isLive()
            && (! empty(Arr::only($attributes, self::API_FIELDS)) || array_key_exists('query_mapping', $attributes))) {
            // Source "live" : re-sonde et redétecte le mapping de filtres et les capacités,
            // synchrone — pas de job d'ingestion à relancer.
            $this->createLiveApiAction->reconfigure($dataSource, Arr::only($attributes, self::API_FIELDS), $attributes['query_mapping'] ?? null);
        } elseif ($dataSource->source_kind === 'api' && ! empty(Arr::only($attributes, self::API_FIELDS))) {
            $this->refreshApiAction->execute($dataSource, Arr::only($attributes, self::API_FIELDS));
        }

        return $dataSource->fresh();
    }

    /**
     * @param  array{sheet_name?: ?string, header_row?: ?int, excluded_rows?: ?array}  $sheetSelection
     *
     * @throws UnsupportedFileTypeException
     */
    private function replaceFile(DataSource $dataSource, UploadedFile $file, array $sheetSelection = []): void
    {
        $extension = strtolower($file->getClientOriginalExtension());

        try {
            $type = DataSourceTypeEnum::fromExtension($extension);
        } catch (\ValueError) {
            throw new UnsupportedFileTypeException($extension);
        }

        $this->resetDatasetForReprocessing($dataSource);

        $originalFilename = $file->getClientOriginalName();
        $uuid = Str::uuid();
        $storagePath = "datasources/{$uuid}/raw.{$extension}";
        Storage::put($storagePath, file_get_contents($file->getRealPath()));

        $dataSource->update([
            'type' => $type,
            'original_filename' => $originalFilename,
            'sheet_name' => $sheetSelection['sheet_name'] ?? null,
            'header_row' => $sheetSelection['header_row'] ?? null,
            'excluded_rows' => $sheetSelection['excluded_rows'] ?? null,
            'raw_storage_path' => $storagePath,
            'file_size_bytes' => $file->getSize(),
            'status' => 'pending',
            'error_message' => null,
        ]);

        if ($type === DataSourceTypeEnum::PARQUET) {
            ProcessParquetJob::dispatch($dataSource);
        } else {
            ProcessDataSourceJob::dispatch($dataSource);
        }
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
