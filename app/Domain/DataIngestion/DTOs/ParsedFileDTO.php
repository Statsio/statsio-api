<?php

namespace App\Domain\DataIngestion\DTOs;

/**
 * Résultat normalisé du parsing d'un fichier source.
 * Toutes les valeurs sont des chaînes brutes — le typage est résolu par SchemaInferenceService.
 */
final class ParsedFileDTO
{
    /**
     * @param string[] $headers Noms des colonnes dans l'ordre du fichier
     * @param iterable<int, array<string, string|null>> $rows Lignes sous forme de tableaux associatifs.
     *        Pour les gros fichiers (ex. JsonLinesParser), il s'agit d'un IteratorAggregate qui relit
     *        le fichier depuis le disque à chaque parcours plutôt qu'un array chargé en RAM — sample()
     *        et un consommateur final (ex. DuckDbParquetWriter) peuvent donc chacun le parcourir
     *        intégralement et indépendamment sans jamais matérialiser l'ensemble des lignes en mémoire.
     */
    public function __construct(
        public readonly array $headers,
        public readonly iterable $rows,
        public readonly int $rowCount,
    ) {}

    public function isEmpty(): bool
    {
        return $this->rowCount === 0;
    }

    /** Retourne les N premières lignes (pour l'inférence de schéma). */
    public function sample(int $n = 100): array
    {
        $sample = [];
        foreach ($this->rows as $row) {
            if (count($sample) >= $n) {
                break;
            }
            $sample[] = $row;
        }

        return $sample;
    }
}
