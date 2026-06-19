<?php

namespace App\Services\DataIngestion\Parsers;

use App\Domain\DataIngestion\DTOs\ParsedFileDTO;
use App\Domain\DataIngestion\Exceptions\FileParsingException;
use App\Services\DataIngestion\Contracts\FileParserInterface;

class CsvParser implements FileParserInterface
{
    public function parse(string $absolutePath, int $maxRows): ParsedFileDTO
    {
        $handle = @fopen($absolutePath, 'r');

        if ($handle === false) {
            throw new FileParsingException(basename($absolutePath), 'Impossible d\'ouvrir le fichier');
        }

        try {
            // Detect BOM (UTF-8) and skip it
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($handle);
            }

            $headers = fgetcsv($handle, 0, ',');

            if ($headers === false || $headers === null) {
                throw new FileParsingException(basename($absolutePath), 'Le fichier est vide ou mal formaté');
            }

            $headers = array_map('trim', $headers);

            // Try semicolon separator if only one column found
            if (count($headers) === 1) {
                rewind($handle);
                $bom2 = fread($handle, 3);
                if ($bom2 !== "\xEF\xBB\xBF") {
                    rewind($handle);
                }
                $headers = fgetcsv($handle, 0, ';');
                if ($headers !== false && $headers !== null) {
                    $headers = array_map('trim', $headers);
                    return $this->readRows($handle, $headers, ';', $absolutePath, $maxRows);
                }
                rewind($handle);
                fgetcsv($handle, 0, ',');
            }

            return $this->readRows($handle, $headers, ',', $absolutePath, $maxRows);
        } finally {
            fclose($handle);
        }
    }

    private function readRows($handle, array $headers, string $separator, string $path, int $maxRows): ParsedFileDTO
    {
        $rows = [];
        $columnCount = count($headers);

        while (($line = fgetcsv($handle, 0, $separator)) !== false) {
            if (count($rows) >= $maxRows) {
                break;
            }

            // Normalize row length to match header count
            $line = array_pad(array_slice($line, 0, $columnCount), $columnCount, null);
            $rows[] = array_combine($headers, $line);
        }

        if (empty($rows)) {
            throw new FileParsingException(basename($path), 'Le fichier ne contient aucune ligne de données');
        }

        return new ParsedFileDTO(
            headers: $headers,
            rows: $rows,
            rowCount: count($rows),
        );
    }
}
