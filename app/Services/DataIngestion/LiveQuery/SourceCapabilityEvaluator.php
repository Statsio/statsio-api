<?php

namespace App\Services\DataIngestion\LiveQuery;

/**
 * Évalue, à partir de tout ce qui a déjà été détecté pendant le sondage d'une
 * source API (schéma enrichi de rôles sémantiques, filtres détectés,
 * pagination, temps de réponse), ce que la source permet réellement de
 * construire côté Statsio — pas seulement "quels paramètres existent" mais
 * "peut-on faire une série temporelle / une carte / un KPI ?" — et un score
 * de compatibilité global.
 *
 * Aucune requête HTTP supplémentaire : tout est dérivé de données déjà
 * collectées par ApiStructureDetector/LiveApiSourceProber/FilterCapabilityProbe.
 * Calculée une seule fois à la création/redétection, jamais recalculée à
 * chaque requête (voir LiveDatasetQueryService, qui n'en a pas besoin).
 */
class SourceCapabilityEvaluator
{
    private const CHART_REQUIREMENTS = [
        'serie_temporelle' => ['supports_date_filter', 'supports_numeric_metrics'],
        'carte' => ['supports_geographic'],
        'kpi' => ['supports_numeric_metrics'],
        'histogramme' => ['supports_grouping', 'supports_numeric_metrics'],
    ];

    private const ALWAYS_INCOMPATIBLE_CHART_TYPES = ['pivot', 'jointure'];

    /**
     * @param  array<string, array{type: \App\Domain\DataIngestion\Enums\ColumnTypeEnum, semantic_role: string}>  $schema
     * @param  array<string, mixed>  $queryMapping
     * @param  array<string, mixed>  $pagination
     * @return array{
     *     supports_date_filter: bool, supports_numeric_metrics: bool, supports_grouping: bool,
     *     supports_geographic: bool, supports_large_queries: bool, supports_pivot: bool, supports_joins: bool,
     *     estimated_max_rows: ?int, response_time_ms: ?int, compatibility_score: int,
     *     compatible_chart_types: string[], incompatible_chart_types: string[],
     * }
     */
    public function evaluate(array $schema, array $queryMapping, array $pagination, mixed $rowCountHint, ?int $responseTimeMs, string $paginationConfidence): array
    {
        $filters = $queryMapping['filters'] ?? [];

        $temporalColumnsWithRange = array_filter(
            array_keys($schema),
            fn (string $col) => ($schema[$col]['semantic_role'] ?? null) === 'temporal' && ! empty($filters[$col]['range'] ?? null),
        );

        $hasMeasure = $this->anyColumnHasRole($schema, 'measure');
        $hasGeographic = $this->anyColumnHasRole($schema, 'geographic');
        $hasDimension = $hasGeographic || $this->anyColumnHasRole($schema, 'dimension');

        $estimatedMaxRows = is_numeric($rowCountHint) ? (int) $rowCountHint : null;
        $pageSize = (int) ($pagination['page_size'] ?? 0);
        $supportsLargeQueries = ($pagination['style'] ?? 'none') !== 'none' && $paginationConfidence === 'confirmed'
            || ($estimatedMaxRows !== null && $pageSize > 0 && $estimatedMaxRows <= $pageSize);

        $capabilities = [
            'supports_date_filter' => count($temporalColumnsWithRange) > 0,
            'supports_numeric_metrics' => $hasMeasure,
            'supports_grouping' => $hasDimension,
            'supports_geographic' => $hasGeographic,
            'supports_large_queries' => $supportsLargeQueries,
            // Les sources live ne supportent ni pivot ni jointure — voir
            // LiveQueryMappingResolver::assertSupportedOperation(), qui les rejette
            // déjà explicitement à l'exécution.
            'supports_pivot' => false,
            'supports_joins' => false,
            'estimated_max_rows' => $estimatedMaxRows,
            'response_time_ms' => $responseTimeMs,
        ];

        $filterCount = count($filters);
        $score = 10; // baseline : enveloppe + méthode détectées avec succès
        $score += match ($paginationConfidence) {
            'confirmed' => 20,
            'guessed' => 5,
            default => 10, // 'none' légitime (peu de données) considéré neutre-positif
        };
        $score += min($filterCount * 5, 20);
        $score += $estimatedMaxRows !== null ? 15 : 0;
        $score += $capabilities['supports_date_filter'] ? 15 : 0;
        $score += $capabilities['supports_geographic'] ? 10 : 0;
        $score += $capabilities['supports_numeric_metrics'] ? 10 : 0;
        $score += ($responseTimeMs !== null && $responseTimeMs < 2000) ? 10 : 0;

        $capabilities['compatibility_score'] = min($score, 100);

        [$compatible, $incompatible] = $this->chartTypeCompatibility($capabilities);
        $capabilities['compatible_chart_types'] = $compatible;
        $capabilities['incompatible_chart_types'] = $incompatible;

        return $capabilities;
    }

    /**
     * @param  array<string, array{semantic_role: string}>  $schema
     */
    private function anyColumnHasRole(array $schema, string $role): bool
    {
        foreach ($schema as $meta) {
            if (($meta['semantic_role'] ?? null) === $role) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, bool>  $capabilities
     * @return array{0: string[], 1: string[]}
     */
    private function chartTypeCompatibility(array $capabilities): array
    {
        $compatible = [];
        $incompatible = [];

        foreach (self::CHART_REQUIREMENTS as $chartType => $requiredCapabilities) {
            $ok = true;
            foreach ($requiredCapabilities as $required) {
                if (empty($capabilities[$required])) {
                    $ok = false;
                    break;
                }
            }
            $ok ? $compatible[] = $chartType : $incompatible[] = $chartType;
        }

        array_push($incompatible, ...self::ALWAYS_INCOMPATIBLE_CHART_TYPES);

        return [$compatible, $incompatible];
    }
}
