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
     * @param array<int, array<string, string|null>> $rows Lignes sous forme de tableaux associatifs
     */
    public function __construct(
        public readonly array $headers,
        public readonly array $rows,
        public readonly int $rowCount,
    ) {}

    public function isEmpty(): bool
    {
        return $this->rowCount === 0;
    }

    /** Retourne les N premières lignes (pour l'inférence de schéma). */
    public function sample(int $n = 100): array
    {
        return array_slice($this->rows, 0, $n);
    }
}
