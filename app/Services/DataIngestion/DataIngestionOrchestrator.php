<?php

namespace App\Services\DataIngestion;

use App\Models\DataIngestion\DataSource;
use App\Models\DataIngestion\Dataset;
use App\Models\DataIngestion\DatasetVersion;
use App\Services\DataIngestion\Contracts\ParquetWriterInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DataIngestionOrchestrator
{
    private int $maxRows;

    public function __construct(
        private readonly ParserFactory $parserFactory,
        private readonly SchemaInferenceService $schemaInferenceService,
        private readonly ParquetWriterInterface $parquetWriter,
        private readonly DatasetColumnPersister $columnPersister,
    ) {
        $this->maxRows = (int) config('statsio.data_ingestion.max_rows', 500_000);
    }

    /**
     * Exécute le pipeline complet pour une DataSource donnée :
     * parse → inférence schema → écriture Parquet locale → upload R2 → persistance en base.
     *
     * @throws \App\Domain\DataIngestion\Exceptions\FileParsingException
     * @throws \App\Domain\DataIngestion\Exceptions\ParquetConversionException
     * @throws \Throwable
     */
    public function process(DataSource $dataSource): Dataset
    {
        $dataSource->markAsProcessing();
        $dataSource->dataset?->updateProgress(5);
        $localTempPath = null;

        try {
            // 1. Parse raw file (local) — pipeline réservé aux sources "upload" (les
            // sources API sont désormais toujours "live", jamais matérialisées en Parquet).
            $absolutePath = Storage::path($dataSource->raw_storage_path);
            $parser = $this->parserFactory->make($dataSource->type);
            $parsed = $parser->parse($absolutePath, $this->maxRows);
            $dataSource->dataset?->updateProgress(25);

            // 2. Infer schema
            $schema = $this->schemaInferenceService->infer($parsed);
            $dataSource->dataset?->updateProgress(40);

            // 3. Write Parquet to local temp path
            $r2Path = $this->buildR2Path($dataSource);
            $localTempPath = storage_path('app/temp/' . $r2Path);
            $this->ensureDirectory(dirname($localTempPath));
            $this->parquetWriter->write($parsed, $localTempPath);
            $dataSource->dataset?->updateProgress(60);

            // 4. Compute metadata from local file
            $fileSizeBytes = filesize($localTempPath) ?: null;
            $checksum = md5_file($localTempPath) ?: null;

            // 5. Upload to configured storage disk
            $datasetsDisk = config('statsio.data_ingestion.datasets_disk', 'local');
            $stream = fopen($localTempPath, 'r');
            Storage::disk($datasetsDisk)->put($r2Path, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
            $dataSource->dataset?->updateProgress(85);

            // 6. Persist to DB
            $dataset = DB::transaction(function () use ($dataSource, $parsed, $schema, $r2Path, $fileSizeBytes, $checksum) {
                $dataset = $dataSource->dataset ?? Dataset::make([
                    'data_source_id' => $dataSource->id,
                    'user_id'        => $dataSource->user_id,
                ]);
                $dataset->fill([
                    'name'         => $dataSource->name,
                    'parquet_path' => $r2Path,
                    'row_count'    => $parsed->rowCount,
                    'status'       => 'ready',
                    'progress'     => 100,
                ])->save();

                $this->columnPersister->persist($dataset, $schema, $parsed->headers);

                DatasetVersion::create([
                    'dataset_id'           => $dataset->id,
                    'version_number'       => 1,
                    'parquet_storage_path' => $r2Path,
                    'file_size_bytes'      => $fileSizeBytes,
                    'row_count'            => $parsed->rowCount,
                    'checksum'             => $checksum,
                ]);

                return $dataset;
            });

            $dataSource->markAsReady();

            // 7. Cleanup local files
            if ($localTempPath && file_exists($localTempPath)) {
                unlink($localTempPath);
            }
            if ($dataSource->raw_storage_path && Storage::exists($dataSource->raw_storage_path)) {
                Storage::delete($dataSource->raw_storage_path);
            }
            $dataSource->update(['raw_storage_path' => null]);

            return $dataset;
        } catch (\Throwable $e) {
            if ($localTempPath && file_exists($localTempPath)) {
                unlink($localTempPath);
            }
            $dataSource->markAsFailed($e->getMessage());
            $dataSource->dataset?->markAsFailed();
            throw $e;
        }
    }

    private function buildR2Path(DataSource $dataSource): string
    {
        $uuid = Str::uuid();
        return "datasets/{$dataSource->user_id}/{$uuid}/v1.parquet";
    }

    private function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
