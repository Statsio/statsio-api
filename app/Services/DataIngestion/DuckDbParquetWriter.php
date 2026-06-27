<?php

namespace App\Services\DataIngestion;

use App\Domain\DataIngestion\DTOs\ParsedFileDTO;
use App\Domain\DataIngestion\Exceptions\ParquetConversionException;
use App\Services\DataIngestion\Contracts\ParquetWriterInterface;

class DuckDbParquetWriter implements ParquetWriterInterface
{
    public function write(ParsedFileDTO $data, string $destinationPath): string
    {
        $directory = dirname($destinationPath);

        if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
            throw new ParquetConversionException("Impossible de créer le répertoire : {$directory}");
        }

        $csvPath = tempnam(sys_get_temp_dir(), 'statsio_') . '.csv';

        try {
            // Write headers + rows as CSV
            $csv = fopen($csvPath, 'w');
            if ($csv === false) {
                throw new ParquetConversionException("Impossible d'écrire le fichier CSV temporaire : {$csvPath}");
            }

            fputcsv($csv, $data->headers, ',', '"', '');

            foreach ($data->rows as $row) {
                $line = [];
                foreach ($data->headers as $header) {
                    $line[] = $row[$header] ?? '';
                }
                fputcsv($csv, $line, ',', '"', '');
            }

            fclose($csv);

            // Convert CSV to Parquet via DuckDB
            $escapedCsv = escapeshellarg($csvPath);
            $escapedParquet = escapeshellarg($destinationPath);
            $headers = implode(', ', array_map(fn($h) => '"' . str_replace('"', '""', $h) . '"', $data->headers));

            $sql = "COPY (SELECT {$headers} FROM read_csv_auto({$escapedCsv}, header=true, all_varchar=true, delim=',')) TO {$escapedParquet} (FORMAT PARQUET, COMPRESSION ZSTD)";
            $output = shell_exec("duckdb -c " . escapeshellarg($sql) . " 2>/dev/null");

            if (!file_exists($destinationPath)) {
                throw new ParquetConversionException("DuckDB n'a pas produit le fichier Parquet : {$destinationPath}");
            }

            return $destinationPath;
        } catch (\Throwable $e) {
            if ($e instanceof ParquetConversionException) throw $e;
            throw new ParquetConversionException(
                "Erreur lors de la conversion CSV→Parquet : " . $e->getMessage(),
                0,
                $e
            );
        } finally {
            if (file_exists($csvPath)) {
                unlink($csvPath);
            }
        }
    }
}
