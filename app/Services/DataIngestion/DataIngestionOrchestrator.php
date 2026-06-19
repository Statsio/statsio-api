<?php

namespace App\Services\DataIngestion;

use App\Domain\DataIngestion\Enums\DataSourceTypeEnum;
use App\Models\DataIngestion\DataSource;
use App\Models\DataIngestion\Dataset;
use App\Models\DataIngestion\DatasetColumn;
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
    ) {
        $this->maxRows = (int) config('statsio.data_ingestion.max_rows', 500_000);
    }

    /**
     * Exécute le pipeline complet pour une DataSource donnée :
     * parse → inférence schema → écriture Parquet → persistance en base.
     *
     * @throws \App\Domain\DataIngestion\Exceptions\FileParsingException
     * @throws \App\Domain\DataIngestion\Exceptions\ParquetConversionException
     * @throws \Throwable
     */
    public function process(DataSource $dataSource): Dataset
    {
        $dataSource->markAsProcessing();

        try {
            $dataset = DB::transaction(function () use ($dataSource) {
                // 1. Parse the raw file
                $absolutePath = Storage::path($dataSource->raw_storage_path);
                $parser = $this->parserFactory->make($dataSource->type);
                $parsed = $parser->parse($absolutePath, $this->maxRows);

                // 2. Infer schema
                $schema = $this->schemaInferenceService->infer($parsed);

                // 3. Determine Parquet destination path
                $parquetStoragePath = $this->buildParquetPath($dataSource);
                $parquetAbsolutePath = Storage::path($parquetStoragePath);

                // 4. Write Parquet file
                $this->parquetWriter->write($parsed, $parquetAbsolutePath);

                $fileSizeBytes = filesize($parquetAbsolutePath) ?: null;
                $checksum = md5_file($parquetAbsolutePath) ?: null;

                // 5. Create Dataset record
                $dataset = Dataset::create([
                    'data_source_id' => $dataSource->id,
                    'user_id' => $dataSource->user_id,
                    'name' => $dataSource->name,
                    'parquet_path' => $parquetStoragePath,
                    'row_count' => $parsed->rowCount,
                    'status' => 'ready',
                ]);

                // 6. Create DatasetColumn records
                $columnRecords = [];
                foreach ($schema as $columnName => $columnMeta) {
                    $columnRecords[] = [
                        'dataset_id' => $dataset->id,
                        'name' => $columnName,
                        'type' => $columnMeta['type']->value,
                        'nullable' => $columnMeta['nullable'],
                        'sample_values' => json_encode($columnMeta['sample_values']),
                        'column_order' => array_search($columnName, $parsed->headers),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                DatasetColumn::insert($columnRecords);

                // 7. Create DatasetVersion record (first version)
                DatasetVersion::create([
                    'dataset_id' => $dataset->id,
                    'version_number' => 1,
                    'parquet_storage_path' => $parquetStoragePath,
                    'file_size_bytes' => $fileSizeBytes,
                    'row_count' => $parsed->rowCount,
                    'checksum' => $checksum,
                ]);

                return $dataset;
            });

            $dataSource->markAsReady();

            // Delete raw file now that Parquet is written — original_filename and type are kept in DB
            if ($dataSource->raw_storage_path && Storage::exists($dataSource->raw_storage_path)) {
                Storage::delete($dataSource->raw_storage_path);
            }
            $dataSource->update(['raw_storage_path' => null]);

            return $dataset;
        } catch (\Throwable $e) {
            $dataSource->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    private function buildParquetPath(DataSource $dataSource): string
    {
        $uuid = Str::uuid();
        return "private/datasets/{$uuid}/v1.parquet";
    }
}
