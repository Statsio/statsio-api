<?php

namespace App\Services\DataIngestion;

use App\Models\DataIngestion\DataSource;
use App\Models\DataIngestion\Dataset;
use App\Models\DataIngestion\DatasetColumn;
use App\Models\DataIngestion\DatasetVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Ingère un fichier Parquet natif sans transformation :
 * lit le schéma via DuckDB depuis le fichier local, upload sur R2, puis supprime le local.
 */
class ParquetIngestionService
{
    public function ingest(DataSource $dataSource): Dataset
    {
        $dataSource->markAsProcessing();

        try {
            $rawAbsPath = Storage::path($dataSource->raw_storage_path);

            // 1. Read schema from local raw file (DuckDB needs a local path)
            [$columns, $rowCount] = $this->readSchema($rawAbsPath);

            $fileSizeBytes = filesize($rawAbsPath) ?: null;
            $checksum      = md5_file($rawAbsPath) ?: null;

            // 2. Upload to configured storage disk
            $r2Path = $this->buildR2Path($dataSource);
            $datasetsDisk = config('statsio.data_ingestion.datasets_disk', 'local');
            $stream = fopen($rawAbsPath, 'r');
            Storage::disk($datasetsDisk)->put($r2Path, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            // 3. Persist to DB
            $dataset = DB::transaction(function () use ($dataSource, $columns, $rowCount, $r2Path, $fileSizeBytes, $checksum) {
                $dataset = $dataSource->dataset ?? Dataset::make([
                    'data_source_id' => $dataSource->id,
                    'user_id'        => $dataSource->user_id,
                ]);
                $dataset->fill([
                    'name'         => $dataSource->name,
                    'parquet_path' => $r2Path,
                    'row_count'    => $rowCount,
                    'status'       => 'ready',
                ])->save();

                if (!empty($columns)) {
                    $records = [];
                    foreach ($columns as $i => $col) {
                        $records[] = [
                            'dataset_id'   => $dataset->id,
                            'name'         => $col['name'],
                            'type'         => $col['type'],
                            'nullable'     => true,
                            'sample_values'=> json_encode([]),
                            'column_order' => $i,
                            'created_at'   => now(),
                            'updated_at'   => now(),
                        ];
                    }
                    DatasetColumn::insert($records);
                }

                DatasetVersion::create([
                    'dataset_id'           => $dataset->id,
                    'version_number'       => 1,
                    'parquet_storage_path' => $r2Path,
                    'file_size_bytes'      => $fileSizeBytes,
                    'row_count'            => $rowCount,
                    'checksum'             => $checksum,
                ]);

                return $dataset;
            });

            $dataSource->markAsReady();

            // 4. Cleanup local raw file
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

    /**
     * Lit le schéma et le nombre de lignes via DuckDB CLI.
     * Retourne [columns[], rowCount] avec un fallback vide si DuckDB est absent.
     *
     * @return array{array<int, array{name: string, type: string}>, int}
     */
    private function readSchema(string $absolutePath): array
    {
        $escaped = escapeshellarg($absolutePath);

        $describeOutput = shell_exec("duckdb -json -c \"DESCRIBE SELECT * FROM read_parquet({$escaped})\" 2>/dev/null");
        $countOutput    = shell_exec("duckdb -json -c \"SELECT COUNT(*) AS n FROM read_parquet({$escaped})\" 2>/dev/null");

        $columns  = [];
        $rowCount = 0;

        if ($describeOutput) {
            $rows = json_decode($describeOutput, true);
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $columns[] = [
                        'name' => (string) ($row['column_name'] ?? $row['Field'] ?? ''),
                        'type' => $this->mapDuckDbType((string) ($row['column_type'] ?? $row['Type'] ?? 'varchar')),
                    ];
                }
            }
        }

        if ($countOutput) {
            $rows = json_decode($countOutput, true);
            if (is_array($rows) && isset($rows[0])) {
                $rowCount = (int) ($rows[0]['n'] ?? $rows[0]['count'] ?? 0);
            }
        }

        return [$columns, $rowCount];
    }

    private function mapDuckDbType(string $t): string
    {
        $t = strtolower($t);
        return match (true) {
            str_contains($t, 'int') || $t === 'bigint' || $t === 'hugeint' || $t === 'smallint' || $t === 'tinyint' => 'integer',
            str_contains($t, 'float') || str_contains($t, 'double') || str_contains($t, 'decimal') || str_contains($t, 'numeric') || $t === 'real' => 'float',
            str_contains($t, 'bool') => 'boolean',
            str_starts_with($t, 'date') && !str_contains($t, 'time') => 'date',
            str_contains($t, 'timestamp') || str_contains($t, 'datetime') => 'datetime',
            default => 'string',
        };
    }

    private function buildR2Path(DataSource $dataSource): string
    {
        return 'datasets/' . $dataSource->user_id . '/' . Str::uuid() . '/v1.parquet';
    }
}
