<?php

namespace App\Services\DataIngestion\Contracts;

use App\Domain\DataIngestion\DTOs\ParsedFileDTO;

interface FileParserInterface
{
    /**
     * Parse le fichier situé à $absolutePath et retourne les données normalisées.
     *
     * @throws \App\Domain\DataIngestion\Exceptions\FileParsingException
     */
    public function parse(string $absolutePath, int $maxRows): ParsedFileDTO;
}
