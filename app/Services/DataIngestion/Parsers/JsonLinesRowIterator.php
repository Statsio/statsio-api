<?php

namespace App\Services\DataIngestion\Parsers;

use App\Domain\DataIngestion\Exceptions\FileParsingException;

/**
 * Itère les lignes d'un fichier JSONL en relisant le fichier depuis le disque à
 * chaque foreach() plutôt qu'en gardant les enregistrements en mémoire — permet
 * à ParsedFileDTO::sample() (200 lignes, pour l'inférence de schéma) puis à
 * DuckDbParquetWriter::write() (toutes les lignes, pour le CSV intermédiaire) de
 * parcourir indépendamment et intégralement le même ParsedFileDTO->rows sans
 * jamais matérialiser l'ensemble du dataset en RAM PHP.
 */
class JsonLinesRowIterator implements \IteratorAggregate
{
    /** @param  string[]  $headers */
    public function __construct(
        private readonly string $absolutePath,
        private readonly array $headers,
        private readonly int $maxRows,
    ) {}

    public function getIterator(): \Generator
    {
        $handle = fopen($this->absolutePath, 'r');
        if ($handle === false) {
            throw new FileParsingException(basename($this->absolutePath), 'Impossible de lire le fichier');
        }

        try {
            $count = 0;
            while ($count < $this->maxRows && ($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                $record = json_decode($line, true);
                if (json_last_error() !== JSON_ERROR_NONE || ! is_array($record)) {
                    throw new FileParsingException(basename($this->absolutePath), 'Ligne JSONL invalide : '.json_last_error_msg());
                }

                yield JsonParser::coerceRow($record, $this->headers);
                $count++;
            }
        } finally {
            fclose($handle);
        }
    }
}
