<?php

namespace App\Services\DataIngestion\Contracts;

use App\Domain\DataIngestion\DTOs\ParsedFileDTO;

interface ParquetWriterInterface
{
    /**
     * Convertit les données parsées en un fichier Parquet et le persiste à $destinationPath.
     * Retourne le chemin absolu du fichier écrit.
     *
     * @throws \App\Domain\DataIngestion\Exceptions\ParquetConversionException
     */
    public function write(ParsedFileDTO $data, string $destinationPath): string;
}
