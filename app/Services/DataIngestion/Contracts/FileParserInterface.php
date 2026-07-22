<?php

namespace App\Services\DataIngestion\Contracts;

use App\Domain\DataIngestion\DTOs\ParsedFileDTO;

interface FileParserInterface
{
    /**
     * Parse le fichier situé à $absolutePath et retourne les données normalisées.
     *
     * $sheetName, $headerRow et $excludedRows ne sont exploités que par XlsxParser
     * (choix de la feuille, de la ligne d'en-têtes, lignes à ignorer) — ignorés par
     * les autres formats.
     *
     * @param  ?int[]  $excludedRows
     *
     * @throws \App\Domain\DataIngestion\Exceptions\FileParsingException
     */
    public function parse(
        string $absolutePath,
        int $maxRows,
        ?string $sheetName = null,
        ?int $headerRow = null,
        ?array $excludedRows = null,
    ): ParsedFileDTO;
}
