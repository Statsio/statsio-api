<?php

namespace App\Domain\DataIngestion\Query;

/**
 * Résultat d'une requête dataset : colonnes retournées, lignes (tableaux
 * associatifs), total avant pagination, et — pour les requêtes multi-sources —
 * la table de correspondance `refDemandée => cléRéelleDeLaLigne` (voir
 * QueryGraph::resolveRef). Sérialisable tel quel dans le cache.
 */
final class QueryResult
{
    /**
     * @param  array<int, string>  $columns
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, string>  $columnMap
     */
    public function __construct(
        public readonly array $columns,
        public readonly array $rows,
        public readonly int $total,
        public readonly array $columnMap = [],
    ) {}
}
