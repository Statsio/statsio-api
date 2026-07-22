<?php

namespace App\Services\DataIngestion\Parsers;

use App\Domain\DataIngestion\DTOs\ParsedFileDTO;
use App\Domain\DataIngestion\Exceptions\FileParsingException;
use App\Services\DataIngestion\Contracts\FileParserInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as SpreadsheetDate;

class XlsxParser implements FileParserInterface
{
    /**
     * @param  $sheetName  Feuille à utiliser (nom exact) — feuille active par défaut si absente/inconnue.
     * @param  $headerRow  Numéro de ligne (1-indexé, dans la feuille sélectionnée) contenant les
     *                     en-têtes de colonnes — ligne 1 par défaut. Utile pour les exports avec
     *                     titre/lignes de garde avant le vrai tableau (rapports institutionnels).
     * @param  $excludedRows  Numéros de ligne (1-indexés, dans la feuille sélectionnée) à ignorer —
     *                        sous la ligne d'en-têtes (notes de bas de tableau, lignes d'unités...).
     */
    public function parse(
        string $absolutePath,
        int $maxRows,
        ?string $sheetName = null,
        ?int $headerRow = null,
        ?array $excludedRows = null,
    ): ParsedFileDTO {
        try {
            $spreadsheet = IOFactory::load($absolutePath);
        } catch (\Throwable $e) {
            throw new FileParsingException(basename($absolutePath), $e->getMessage(), $e);
        }

        $sheet = ($sheetName !== null && $spreadsheet->sheetNameExists($sheetName))
            ? $spreadsheet->getSheetByName($sheetName)
            : $spreadsheet->getActiveSheet();

        // toArray(nullValue, calculateFormulas, formatData, returnCellRef)
        $rawData = $sheet->toArray(null, true, false, false);

        // Free memory immediately
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        if (empty($rawData)) {
            throw new FileParsingException(basename($absolutePath), 'La feuille de calcul est vide');
        }

        $headerIndex = max(0, ($headerRow ?? 1) - 1);
        if ($headerIndex >= count($rawData)) {
            throw new FileParsingException(
                basename($absolutePath),
                "La ligne d'en-têtes indiquée ({$headerRow}) dépasse le nombre de lignes de la feuille"
            );
        }

        $headers = array_map(fn ($h) => trim((string) ($h ?? '')), $rawData[$headerIndex]);
        $rawData = array_slice($rawData, $headerIndex + 1);

        if (empty(array_filter($headers))) {
            throw new FileParsingException(basename($absolutePath), "La ligne d'en-têtes sélectionnée ne contient aucune colonne nommée");
        }

        $excludedSet = array_flip($excludedRows ?? []);
        $columnCount = count($headers);
        $rows = [];

        foreach ($rawData as $i => $rawRow) {
            if (count($rows) >= $maxRows) {
                break;
            }

            // Numéro de ligne absolu dans la feuille (1-indexé), pour le comparer à $excludedRows.
            $absoluteRowNumber = $headerIndex + 2 + $i;
            if (isset($excludedSet[$absoluteRowNumber])) {
                continue;
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
