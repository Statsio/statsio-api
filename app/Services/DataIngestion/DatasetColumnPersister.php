<?php

namespace App\Services\DataIngestion;

use App\Models\DataIngestion\Dataset;
use App\Models\DataIngestion\DatasetColumn;

/**
 * Persiste les colonnes (`DatasetColumn`) à partir d'un schéma inféré
 * (`SchemaInferenceService::infer()`) — factorisé pour être appelé aussi bien
 * par DataIngestionOrchestrator (pipeline snapshot complet) que par la
 * découverte de schéma d'une source API "live" (échantillon seul, pas
 * d'ingestion complète).
 */
class DatasetColumnPersister
{
    /**
     * @param  array<string, array{type: \App\Domain\DataIngestion\Enums\ColumnTypeEnum, nullable: bool, sample_values: array}>  $schema
     * @param  string[]  $headers
     */
    public function persist(Dataset $dataset, array $schema, array $headers): void
    {
        $columnRecords = [];
        foreach ($schema as $columnName => $columnMeta) {
            $columnRecords[] = [
                'dataset_id' => $dataset->id,
                'name' => $columnName,
                'type' => $columnMeta['type']->value,
                'nullable' => $columnMeta['nullable'],
                'sample_values' => json_encode($columnMeta['sample_values']),
                'column_order' => array_search($columnName, $headers),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DatasetColumn::insert($columnRecords);
    }
}
