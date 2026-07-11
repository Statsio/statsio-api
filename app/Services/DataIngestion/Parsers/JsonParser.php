<?php

namespace App\Services\DataIngestion\Parsers;

use App\Domain\DataIngestion\DTOs\ParsedFileDTO;
use App\Domain\DataIngestion\Exceptions\FileParsingException;
use App\Services\DataIngestion\Contracts\FileParserInterface;

class JsonParser implements FileParserInterface
{
    public function parse(string $absolutePath, int $maxRows): ParsedFileDTO
    {
        $content = @file_get_contents($absolutePath);

        if ($content === false) {
            throw new FileParsingException(basename($absolutePath), 'Impossible de lire le fichier');
        }

        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new FileParsingException(basename($absolutePath), 'JSON invalide : ' . json_last_error_msg());
        }

        // Support root-level array or { "data": [...] } wrapper
        $records = match (true) {
            is_array($decoded) && array_is_list($decoded) => $decoded,
            is_array($decoded) && isset($decoded['data']) && is_array($decoded['data']) => $decoded['data'],
            default => throw new FileParsingException(
                basename($absolutePath),
                'Le JSON doit être un tableau ou un objet avec une clé "data" contenant un tableau'
            ),
        };

        if (empty($records)) {
            throw new FileParsingException(basename($absolutePath), 'Le fichier JSON ne contient aucun enregistrement');
        }

        return self::fromRecords($records, $maxRows, basename($absolutePath));
    }

    /**
     * Met en forme une liste d'enregistrements JSON déjà décodés (union des clés en
     * headers, coercition des valeurs en chaînes) — factorisé pour être utilisé à la
     * fois par le parsing d'un fichier complet (ci-dessus) et par l'échantillon d'une
     * page d'API live (pas de fichier, juste les enregistrements déjà en mémoire).
     *
     * @param  array<int, array>  $records
     *
     * @throws FileParsingException
     */
    public static function fromRecords(array $records, int $maxRows, string $label = 'échantillon'): ParsedFileDTO
    {
        $records = array_slice($records, 0, $maxRows);

        // Collect all unique keys as headers (union of all records)
        $headers = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                throw new FileParsingException($label, 'Chaque enregistrement JSON doit être un objet');
            }
            foreach (array_keys($record) as $key) {
                if (!in_array($key, $headers, true)) {
                    $headers[] = $key;
                }
            }
        }

        $rows = [];
        foreach ($records as $record) {
            $row = [];
            foreach ($headers as $key) {
                $value = $record[$key] ?? null;
                $row[$key] = match (true) {
                    is_bool($value) => $value ? 'true' : 'false',
                    is_array($value), is_object($value) => json_encode($value),
                    $value === null => null,
                    default => (string) $value,
                };
            }
            $rows[] = $row;
        }

        return new ParsedFileDTO(
            headers: $headers,
            rows: $rows,
            rowCount: count($rows),
        );
    }
}
