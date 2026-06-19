<?php

namespace App\Services\DataIngestion\Parsers;

use App\Domain\DataIngestion\DTOs\ParsedFileDTO;
use App\Domain\DataIngestion\Exceptions\FileParsingException;
use App\Services\DataIngestion\Contracts\FileParserInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as SpreadsheetDate;

class XlsxParser implements FileParserInterface
{
    public function parse(string $absolutePath, int $maxRows): ParsedFileDTO
    {
        try {
            $spreadsheet = IOFactory::load($absolutePath);
        } catch (\Throwable $e) {
            throw new FileParsingException(basename($absolutePath), $e->getMessage(), $e);
        }

        $sheet = $spreadsheet->getActiveSheet();

        // toArray(nullValue, calculateFormulas, formatData, returnCellRef)
        $rawData = $sheet->toArray(null, true, false, false);

        // Free memory immediately
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        if (empty($rawData)) {
            throw new FileParsingException(basename($absolutePath), 'La feuille de calcul est vide');
        }

        $headers = array_map(
            fn ($h) => trim((string) ($h ?? '')),
            array_shift($rawData)
        );

        if (empty(array_filter($headers))) {
            throw new FileParsingException(basename($absolutePath), 'La première ligne doit contenir les en-têtes de colonnes');
        }

        $columnCount = count($headers);
        $rows = [];

        foreach ($rawData as $rawRow) {
            if (count($rows) >= $maxRows) {
                break;
            }

            // Skip completely empty rows
            if (empty(array_filter($rawRow, fn ($v) => $v !== null && $v !== ''))) {
                continue;
            }

            $rawRow = array_pad(array_slice($rawRow, 0, $columnCount), $columnCount, null);

            $normalizedRow = [];
            foreach ($headers as $i => $header) {
                $value = $rawRow[$i] ?? null;
                // Convert Excel date serials to ISO 8601 strings
                if (is_numeric($value) && $value > 25569 && $value < 109574) {
                    $value = SpreadsheetDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
                }
                $normalizedRow[$header] = $value !== null ? (string) $value : null;
            }

            $rows[] = $normalizedRow;
        }

        if (empty($rows)) {
            throw new FileParsingException(basename($absolutePath), 'Le fichier ne contient aucune ligne de données');
        }

        return new ParsedFileDTO(
            headers: $headers,
            rows: $rows,
            rowCount: count($rows),
        );
    }
}
