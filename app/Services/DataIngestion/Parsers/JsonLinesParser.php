<?php

namespace App\Services\DataIngestion\Parsers;

use App\Domain\DataIngestion\DTOs\ParsedFileDTO;
use App\Domain\DataIngestion\Exceptions\FileParsingException;
use App\Services\DataIngestion\Contracts\FileParserInterface;

/**
 * Parse un fichier JSONL (une ligne = un enregistrement JSON).
 *
 * Contrairement à JsonParser, ne charge jamais l'ensemble du fichier en
 * mémoire : une première passe légère ne décode chaque ligne que pour
 * collecter l'union des clés (headers) et compter les lignes, sans rien
 * garder ; ParsedFileDTO->rows est ensuite un JsonLinesRowIterator qui
 * relit le fichier à chaque parcours (sample() puis écriture Parquet).
 *
 * Non câblée actuellement (les sources API sont désormais toujours "live",
 * jamais matérialisées via un fichier JSONL) — conservée car générique et
 * testée indépendamment, réutilisable pour un futur besoin de streaming JSONL.
 */
class JsonLinesParser implements FileParserInterface
{
    public function parse(string $absolutePath, int $maxRows): ParsedFileDTO
    {
        [$headers, $rowCount] = $this->scanHeadersAndCount($absolutePath, $maxRows);

        if ($rowCount === 0) {
            throw new FileParsingException(basename($absolutePath), 'Le fichier JSONL ne contient aucun enregistrement');
        }

        return new ParsedFileDTO(
            headers: $headers,
            rows: new JsonLinesRowIterator($absolutePath, $headers, $maxRows),
            rowCount: $rowCount,
        );
    }

    /**
     * @return array{0: string[], 1: int}
     */
    private function scanHeadersAndCount(string $absolutePath, int $maxRows): array
    {
        $handle = fopen($absolutePath, 'r');
        if ($handle === false) {
            throw new FileParsingException(basename($absolutePath), 'Impossible de lire le fichier');
        }

        $headers = [];
        $count = 0;

        try {
            while ($count < $maxRows && ($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                $record = json_decode($line, true);
                if (json_last_error() !== JSON_ERROR_NONE || ! is_array($record)) {
                    throw new FileParsingException(basename($absolutePath), 'Ligne JSONL invalide : '.json_last_error_msg());
                }

                foreach (array_keys($record) as $key) {
                    if (! in_array($key, $headers, true)) {
                        $headers[] = $key;
                    }
                }

                $count++;
            }
        } finally {
            fclose($handle);
        }

        return [$headers, $count];
    }
}
