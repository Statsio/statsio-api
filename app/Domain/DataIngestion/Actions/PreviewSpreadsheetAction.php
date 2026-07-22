<?php

namespace App\Domain\DataIngestion\Actions;

use App\Domain\DataIngestion\Exceptions\FileParsingException;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Aperçu brut d'un fichier xlsx/xls (liste des feuilles + premières lignes) —
 * utilisé par le wizard d'ajout de source pour laisser choisir la feuille et
 * la ligne d'en-têtes avant l'ingestion réelle (XlsxParser), notamment pour
 * les exports institutionnels avec titre/lignes de garde avant le tableau.
 */
class PreviewSpreadsheetAction
{
    private const PREVIEW_ROWS = 15;

    /**
     * @throws FileParsingException
     */
    public function execute(UploadedFile $file, ?string $sheetName = null): array
    {
        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
        } catch (\Throwable $e) {
            throw new FileParsingException($file->getClientOriginalName(), $e->getMessage(), $e);
        }

        $sheetNames = array_map(fn ($sheet) => $sheet->getTitle(), $spreadsheet->getAllSheets());

        $sheet = ($sheetName !== null && $spreadsheet->sheetNameExists($sheetName))
            ? $spreadsheet->getSheetByName($sheetName)
            : $spreadsheet->getActiveSheet();

        $resolvedSheetName = $sheet->getTitle();

        $rawData = $sheet->toArray(null, true, false, false);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        $previewRows = array_map(
            fn ($row) => array_map(fn ($v) => $v === null ? null : trim((string) $v), $row),
            array_slice($rawData, 0, self::PREVIEW_ROWS)
        );

        return [
            'sheets' => $sheetNames,
            'sheet_name' => $resolvedSheetName,
            'rows' => $previewRows,
            'suggested_header_row' => $this->suggestHeaderRow($previewRows),
        ];
    }

    /**
     * Première ligne comportant au moins deux cellules non vides — heuristique
     * simple pour présélectionner la ligne d'en-têtes, l'utilisateur reste
     * libre de la corriger dans l'aperçu.
     */
    private function suggestHeaderRow(array $previewRows): ?int
    {
        foreach ($previewRows as $i => $row) {
            $nonEmpty = array_filter($row, fn ($v) => $v !== null && $v !== '');
            if (count($nonEmpty) >= 2) {
                return $i + 1;
            }
        }

        return null;
    }
}
