<?php

namespace Tests\Unit\Services\DataIngestion;

use App\Domain\DataIngestion\Enums\ColumnTypeEnum;
use App\Services\DataIngestion\ColumnSemanticClassifier;
use Tests\TestCase;

class ColumnSemanticClassifierTest extends TestCase
{
    private function column(ColumnTypeEnum $type, int $distinctCount = 5, int $sampledCount = 200): array
    {
        return ['type' => $type, 'nullable' => false, 'sample_values' => [], 'distinct_count' => $distinctCount, 'sampled_count' => $sampledCount];
    }

    public function test_temporal_columns_are_classified_as_temporal(): void
    {
        $roles = (new ColumnSemanticClassifier)->classify([
            'date_prelevement' => $this->column(ColumnTypeEnum::DATE),
            'created_at' => $this->column(ColumnTypeEnum::DATETIME),
        ]);

        $this->assertSame('temporal', $roles['date_prelevement']);
        $this->assertSame('temporal', $roles['created_at']);
    }

    public function test_numeric_column_matching_geographic_name_is_geographic_not_measure(): void
    {
        // Cas explicitement documenté dans le plan : un code_departement numérique
        // n'est pas une mesure, c'est un code de dimension géographique.
        $roles = (new ColumnSemanticClassifier)->classify([
            'code_departement' => $this->column(ColumnTypeEnum::INTEGER),
        ]);

        $this->assertSame('geographic', $roles['code_departement']);
    }

    public function test_string_geographic_name_is_geographic(): void
    {
        $roles = (new ColumnSemanticClassifier)->classify([
            'nom_commune' => $this->column(ColumnTypeEnum::STRING),
            'latitude' => $this->column(ColumnTypeEnum::FLOAT),
        ]);

        $this->assertSame('geographic', $roles['nom_commune']);
        $this->assertSame('geographic', $roles['latitude']);
    }

    public function test_id_suffix_or_exact_name_is_identifier(): void
    {
        $roles = (new ColumnSemanticClassifier)->classify([
            'id' => $this->column(ColumnTypeEnum::INTEGER),
            'user_id' => $this->column(ColumnTypeEnum::INTEGER),
        ]);

        $this->assertSame('identifier', $roles['id']);
        $this->assertSame('identifier', $roles['user_id']);
    }

    public function test_high_uniqueness_ratio_without_id_name_is_identifier(): void
    {
        $roles = (new ColumnSemanticClassifier)->classify([
            'reference' => $this->column(ColumnTypeEnum::STRING, distinctCount: 200, sampledCount: 200),
        ]);

        $this->assertSame('identifier', $roles['reference']);
    }

    public function test_numeric_column_without_special_name_is_measure(): void
    {
        $roles = (new ColumnSemanticClassifier)->classify([
            'resultat' => $this->column(ColumnTypeEnum::FLOAT, distinctCount: 150, sampledCount: 200),
        ]);

        $this->assertSame('measure', $roles['resultat']);
    }

    public function test_low_cardinality_string_is_dimension(): void
    {
        $roles = (new ColumnSemanticClassifier)->classify([
            'code_parametre' => $this->column(ColumnTypeEnum::STRING, distinctCount: 8, sampledCount: 200),
        ]);

        $this->assertSame('dimension', $roles['code_parametre']);
    }

    public function test_high_cardinality_string_below_identifier_threshold_is_text(): void
    {
        // Cardinalité élevée (au-dessus du seuil "dimension") mais pas assez uniforme
        // pour ressembler à un identifiant (ratio < 90%) — typiquement un champ texte libre.
        $roles = (new ColumnSemanticClassifier)->classify([
            'commentaire' => $this->column(ColumnTypeEnum::STRING, distinctCount: 150, sampledCount: 200),
        ]);

        $this->assertSame('text', $roles['commentaire']);
    }

    public function test_boolean_column_without_special_name_is_unknown(): void
    {
        $roles = (new ColumnSemanticClassifier)->classify([
            'actif' => $this->column(ColumnTypeEnum::BOOLEAN),
        ]);

        $this->assertSame('unknown', $roles['actif']);
    }
}
