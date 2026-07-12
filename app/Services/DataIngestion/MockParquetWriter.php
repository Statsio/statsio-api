<?php

namespace App\Services\DataIngestion;

use App\Domain\DataIngestion\DTOs\ParsedFileDTO;
use App\Domain\DataIngestion\Exceptions\ParquetConversionException;
use App\Services\DataIngestion\Contracts\ParquetWriterInterface;

/**
 * Implémentation de développement : écrit les données en JSON compressé avec l'extension .parquet.
 *
 * PRODUCTION : remplacer cette classe par une implémentation qui produit de vrais fichiers
 * binaires Apache Parquet (ex: via un microservice Python/DuckDB, ou flow-php/parquet).
 * Il suffit de changer le binding dans AppServiceProvider.
 */
class MockParquetWriter implements ParquetWriterInterface
{
    public function write(ParsedFileDTO $data, string $destinationPath): string
    {
        $directory = dirname($destinationPath);

        if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
            throw new ParquetConversionException("Impossible de créer le répertoire : {$directory}");
        }

        $payload = json_encode([
            '__mock__' => true,
            '__notice__' => 'Fichier de développement. Remplacer MockParquetWriter par un vrai writer Parquet en production.',
            'schema' => $data->headers,
            'row_count' => $data->rowCount,
            'data' => is_array($data->rows) ? $data->rows : iterator_to_array($data->rows),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        if (file_put_contents($destinationPath, $payload) === false) {
            throw new ParquetConversionException("Impossible d'écrire le fichier : {$destinationPath}");
        }

        return $destinationPath;
    }
}
