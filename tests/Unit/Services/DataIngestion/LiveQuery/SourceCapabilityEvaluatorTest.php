<?php

namespace Tests\Unit\Services\DataIngestion\LiveQuery;

use App\Services\DataIngestion\LiveQuery\SourceCapabilityEvaluator;
use Tests\TestCase;

class SourceCapabilityEvaluatorTest extends TestCase
{
    private function evaluator(): SourceCapabilityEvaluator
    {
        return new SourceCapabilityEvaluator;
    }

    private function richSchema(): array
    {
        return [
            'date_prelevement' => ['semantic_role' => 'temporal'],
            'code_departement' => ['semantic_role' => 'geographic'],
            'resultat' => ['semantic_role' => 'measure'],
        ];
    }

    public function test_full_capabilities_detected_for_a_well_shaped_source(): void
    {
        $queryMapping = [
            'filters' => [
                'code_departement' => ['param' => 'code_departement', 'operators' => ['eq']],
                'date_prelevement' => ['range' => ['gte_param' => 'date_min', 'lte_param' => 'date_max'], 'operators' => ['gte', 'lte']],
            ],
        ];
        $pagination = ['style' => 'page', 'page_size' => 100];

        $capabilities = $this->evaluator()->evaluate($this->richSchema(), $queryMapping, $pagination, 4500, 300, 'confirmed');

        $this->assertTrue($capabilities['supports_date_filter']);
        $this->assertTrue($capabilities['supports_numeric_metrics']);
        $this->assertTrue($capabilities['supports_grouping']);
        $this->assertTrue($capabilities['supports_geographic']);
        $this->assertFalse($capabilities['supports_pivot']);
        $this->assertFalse($capabilities['supports_joins']);
        $this->assertSame(4500, $capabilities['estimated_max_rows']);
        $this->assertSame(300, $capabilities['response_time_ms']);

        $this->assertContains('serie_temporelle', $capabilities['compatible_chart_types']);
        $this->assertContains('carte', $capabilities['compatible_chart_types']);
        $this->assertContains('kpi', $capabilities['compatible_chart_types']);
        $this->assertContains('histogramme', $capabilities['compatible_chart_types']);
        $this->assertContains('pivot', $capabilities['incompatible_chart_types']);
        $this->assertContains('jointure', $capabilities['incompatible_chart_types']);
    }

    public function test_date_filter_requires_a_range_mapping_not_just_a_temporal_column(): void
    {
        // Colonne temporelle présente mais aucun filtre range détecté dessus.
        $capabilities = $this->evaluator()->evaluate($this->richSchema(), ['filters' => []], ['style' => 'none'], null, null, 'none');

        $this->assertFalse($capabilities['supports_date_filter']);
        $this->assertNotContains('serie_temporelle', $capabilities['compatible_chart_types']);
    }

    public function test_minimal_source_has_low_score_and_few_compatible_chart_types(): void
    {
        $schema = ['libelle' => ['semantic_role' => 'text']];

        $capabilities = $this->evaluator()->evaluate($schema, ['filters' => []], ['style' => 'none'], null, null, 'none');

        $this->assertFalse($capabilities['supports_date_filter']);
        $this->assertFalse($capabilities['supports_numeric_metrics']);
        $this->assertFalse($capabilities['supports_grouping']);
        $this->assertFalse($capabilities['supports_geographic']);
        $this->assertSame([], $capabilities['compatible_chart_types']);
        $this->assertLessThan(50, $capabilities['compatibility_score']);
    }

    public function test_score_is_capped_at_100(): void
    {
        $queryMapping = ['filters' => array_fill_keys(range(1, 10), ['param' => 'x', 'operators' => ['eq']])];

        $capabilities = $this->evaluator()->evaluate($this->richSchema(), $queryMapping, ['style' => 'page', 'page_size' => 100], 100, 100, 'confirmed');

        $this->assertLessThanOrEqual(100, $capabilities['compatibility_score']);
    }

    public function test_slow_response_time_does_not_earn_the_speed_bonus(): void
    {
        $fast = $this->evaluator()->evaluate($this->richSchema(), ['filters' => []], ['style' => 'none'], null, 500, 'none');
        $slow = $this->evaluator()->evaluate($this->richSchema(), ['filters' => []], ['style' => 'none'], null, 5000, 'none');

        $this->assertGreaterThan($slow['compatibility_score'], $fast['compatibility_score']);
    }
}
