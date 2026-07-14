<?php

namespace App\Services\DataIngestion\LiveQuery;

use App\Domain\DataIngestion\Exceptions\UnsupportedLiveQueryOperationException;

/**
 * Traduit les paramètres de requête Studio (filtres/tri/jointures/agrégation,
 * déjà parsés par DatasetController::parseQueryParams()) en paramètres de
 * requête upstream, via le `query_mapping` d'une source "live" — ou rejette
 * explicitement l'opération si le mapping ne la couvre pas.
 */
class LiveQueryMappingResolver
{
    /** Opérateurs Studio traduisibles ; tout le reste (>, <, !=, contains...) est rejeté. */
    private const OPERATOR_MAP = [
        '=' => 'eq',
        '>=' => 'gte',
        '<=' => 'lte',
    ];

    /**
     * @param  array<int, array{column: string, operator: string, value: string}>  $filters
     * @param  array<string, mixed>  $queryMapping
     * @return array<string, mixed> Paramètres upstream résolus (hors pagination)
     *
     * @throws UnsupportedLiveQueryOperationException
     */
    public function resolveFilters(array $filters, array $queryMapping): array
    {
        $mappingFilters = $queryMapping['filters'] ?? [];
        $resolved = [];

        foreach ($filters as $filter) {
            $column = (string) ($filter['column'] ?? '');
            $studioOperator = (string) ($filter['operator'] ?? '=');
            $value = (string) ($filter['value'] ?? '');

            $operator = self::OPERATOR_MAP[$studioOperator] ?? null;
            $mapping = $mappingFilters[$column] ?? null;

            if ($operator === null || $mapping === null) {
                throw new UnsupportedLiveQueryOperationException(
                    "Le filtre sur la colonne « {$column} » (opérateur « {$studioOperator} ») n'est pas supporté par cette source en direct."
                );
            }

            if ($operator === 'eq') {
                if (empty($mapping['param']) || ! in_array('eq', $mapping['operators'] ?? [], true)) {
                    throw new UnsupportedLiveQueryOperationException(
                        "Le filtre d'égalité sur « {$column} » n'est pas supporté par cette source en direct."
                    );
                }
                $resolved[$mapping['param']] = $value;

                continue;
            }

            // gte / lte : uniquement via un mapping de type "range" (bornes min/max)
            if (empty($mapping['range']) || ! in_array($operator, $mapping['operators'] ?? [], true)) {
                throw new UnsupportedLiveQueryOperationException(
                    "Le filtre « {$studioOperator} » sur « {$column} » n'est pas supporté par cette source en direct."
                );
            }

            $param = $operator === 'gte' ? $mapping['range']['gte_param'] : $mapping['range']['lte_param'];
            $resolved[$param] = $value;
        }

        return $resolved;
    }

    /**
     * Rejette les opérations que le chemin live ne sait pas traduire :
     * jointures, agrégation, tri hors sortable_columns, et dédoublonnage au
     * niveau ligne (distinct_column) — dédoublonner tout en conservant les
     * colonnes complètes exigerait de parcourir l'ensemble des pages
     * upstream, ce qui n'est pas fait en v1. La recherche texte libre
     * (search_q/search_columns) est validée séparément, voir
     * resolveSearchColumnParams() — elle est possible quand chaque colonne
     * recherchée a un paramètre upstream simple mappé.
     *
     * @param  array<string, mixed>  $queryMapping
     * @param  array<int, array>  $joins
     *
     * @throws UnsupportedLiveQueryOperationException
     */
    public function assertSupportedOperation(array $queryMapping, array $joins, ?string $distinctColumn, ?string $sortColumn, ?string $aggregate): void
    {
        if (! empty($joins)) {
            throw new UnsupportedLiveQueryOperationException('Les jointures ne sont pas supportées sur une source en direct.');
        }

        if ($aggregate !== null) {
            throw new UnsupportedLiveQueryOperationException('Les agrégations ne sont pas supportées sur une source en direct.');
        }

        if ($distinctColumn !== null) {
            throw new UnsupportedLiveQueryOperationException('Le dédoublonnage par colonne n\'est pas supporté sur une source en direct.');
        }

        if ($sortColumn !== null && ! in_array($sortColumn, $queryMapping['sortable_columns'] ?? [], true)) {
            throw new UnsupportedLiveQueryOperationException("Le tri sur « {$sortColumn} » n'est pas supporté par cette source en direct.");
        }
    }

    /**
     * Résout, pour une recherche texte libre (search_q/search_columns), le
     * paramètre upstream à utiliser pour chaque colonne recherchée — un appel
     * upstream distinct par colonne, fusionnés côté LiveDatasetQueryService
     * (les API REST classiques ne combinent pas un OR entre plusieurs
     * paramètres différents en une seule requête).
     *
     * Réutilise le paramètre `eq` déjà détecté : empiriquement, ce type de
     * paramètre "nom_*" sur ce genre d'API se comporte souvent déjà comme une
     * recherche partielle/floue côté serveur (ex. Hub'Eau), même quand notre
     * sondage ne confirme qu'une correspondance exacte. Dans le pire cas
     * (upstream réellement strict), la colonne concernée ne remonte
     * simplement aucune ligne — dégradation silencieuse, pas un crash.
     *
     * @param  string[]  $searchCols
     * @param  array<string, mixed>  $queryMapping
     * @return array<string, string> colonne => paramètre upstream
     *
     * @throws UnsupportedLiveQueryOperationException
     */
    public function resolveSearchColumnParams(array $searchCols, array $queryMapping): array
    {
        $mappingFilters = $queryMapping['filters'] ?? [];
        $resolved = [];

        foreach ($searchCols as $column) {
            $mapping = $mappingFilters[$column] ?? null;

            if (empty($mapping['param']) || ! in_array('eq', $mapping['operators'] ?? [], true)) {
                throw new UnsupportedLiveQueryOperationException(
                    "La recherche sur la colonne « {$column} » n'est pas supportée par cette source en direct."
                );
            }

            $resolved[$column] = $mapping['param'];
        }

        return $resolved;
    }

    /**
     * Pour resolveDistinctValues() (liste des valeurs uniques d'UNE colonne,
     * utilisée pour peupler les listes de filtres de l'UI) — servie depuis les
     * échantillons capturés à la création, jamais depuis un scan complet.
     *
     * @param  array<string, mixed>  $queryMapping
     *
     * @throws UnsupportedLiveQueryOperationException
     */
    public function assertDistinctValuesSupported(array $queryMapping, string $column): void
    {
        $mappingFilters = $queryMapping['filters'] ?? [];
        if (! isset($mappingFilters[$column])) {
            throw new UnsupportedLiveQueryOperationException(
                "Les valeurs distinctes de « {$column} » ne sont pas disponibles pour cette source en direct."
            );
        }
    }
}
