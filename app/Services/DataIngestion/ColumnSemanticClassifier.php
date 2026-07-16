<?php

namespace App\Services\DataIngestion;

use App\Domain\DataIngestion\Enums\ColumnTypeEnum;

/**
 * Classe chaque colonne d'un schéma inféré dans un rôle sémantique
 * (temporal/measure/dimension/geographic/identifier/text/unknown), en
 * complément du type technique déjà produit par SchemaInferenceService —
 * permet de savoir ce qu'on peut faire d'une source (série temporelle,
 * carte, KPI...) sans encore décider quel graphique construire (ça reste
 * au Studio).
 *
 * Heuristique v1, déterministe, basée uniquement sur des données déjà
 * collectées (nom de colonne, type, valeurs d'exemple) — aucune requête
 * HTTP supplémentaire. Le dictionnaire de conventions géographiques est
 * volontairement limité au français/open-data, cohérent avec le ciblage
 * déjà assumé par FilterCapabilityProbe (Hub'Eau/Sandre/data.gouv.fr).
 */
class ColumnSemanticClassifier
{
    private const GEOGRAPHIC_NAMES = [
        'code_insee', 'code_commune', 'code_departement', 'code_postal', 'code_region',
        'nom_commune', 'nom_departement', 'nom_region',
        'commune', 'departement', 'region',
        'latitude', 'longitude', 'lat', 'lon', 'lng',
    ];

    /** Ratio distinct/échantillonné au-delà duquel une colonne est considérée comme un identifiant (quasi toutes valeurs uniques). */
    private const IDENTIFIER_UNIQUENESS_RATIO = 0.9;

    /** Nombre de valeurs distinctes max (sur l'échantillon) au-delà duquel une colonne string n'est plus une "dimension" mais du "texte". */
    private const DIMENSION_CARDINALITY_THRESHOLD = 50;

    /**
     * @param  array<string, array{type: ColumnTypeEnum, nullable: bool, sample_values: array, distinct_count: int, sampled_count: int}>  $schema
     * @return array<string, string> nom de colonne => 'temporal'|'measure'|'dimension'|'geographic'|'identifier'|'text'|'unknown'
     */
    public function classify(array $schema): array
    {
        $roles = [];

        foreach ($schema as $column => $meta) {
            $roles[$column] = $this->classifyColumn(
                $column,
                $meta['type'],
                (int) ($meta['distinct_count'] ?? 0),
                (int) ($meta['sampled_count'] ?? 0),
            );
        }

        return $roles;
    }

    private function classifyColumn(string $column, ColumnTypeEnum $type, int $distinctCount, int $sampledCount): string
    {
        if ($type->isTemporal()) {
            return 'temporal';
        }

        if ($this->looksGeographic($column)) {
            return 'geographic';
        }

        if ($this->looksIdentifier($column, $distinctCount, $sampledCount)) {
            return 'identifier';
        }

        if ($type->isNumeric()) {
            return 'measure';
        }

        if ($type === ColumnTypeEnum::STRING) {
            return $this->classifyStringColumn($distinctCount);
        }

        return 'unknown';
    }

    private function looksGeographic(string $column): bool
    {
        $normalized = strtolower($column);

        return in_array($normalized, self::GEOGRAPHIC_NAMES, true);
    }

    private function looksIdentifier(string $column, int $distinctCount, int $sampledCount): bool
    {
        $normalized = strtolower($column);

        if ($normalized === 'id' || str_ends_with($normalized, '_id')) {
            return true;
        }

        if ($sampledCount < 2) {
            return false;
        }

        return ($distinctCount / $sampledCount) >= self::IDENTIFIER_UNIQUENESS_RATIO;
    }

    private function classifyStringColumn(int $distinctCount): string
    {
        if ($distinctCount === 0) {
            return 'unknown';
        }

        return $distinctCount <= self::DIMENSION_CARDINALITY_THRESHOLD ? 'dimension' : 'text';
    }
}
