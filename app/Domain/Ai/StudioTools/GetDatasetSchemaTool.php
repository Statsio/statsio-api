<?php

namespace App\Domain\Ai\StudioTools;

use App\Domain\Ai\StudioAgentContext;
use App\Domain\Ai\Support\StudioSourceReader;

/**
 * Schéma détaillé d'un dataset accessible, avec échantillons de valeurs par colonne.
 */
class GetDatasetSchemaTool implements StudioAgentTool
{
    public function __construct(private readonly StudioSourceReader $reader) {}

    public function name(): string
    {
        return 'get_dataset_schema';
    }

    public function description(): string
    {
        return 'Récupère le schéma complet d\'un dataset (colonnes, types, rôle sémantique, '
            .'échantillons de valeurs) pour choisir précisément les colonnes d\'un bloc.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'dataset_id' => ['type' => 'integer', 'description' => 'Identifiant du dataset.'],
            ],
            'required' => ['dataset_id'],
        ];
    }

    public function execute(array $input, StudioAgentContext $context): array
    {
        $datasetId = (int) ($input['dataset_id'] ?? 0);
        $schema = $this->reader->datasetSchema($context->user, $datasetId);

        if ($schema === null) {
            return ['error' => "Dataset {$datasetId} introuvable ou non accessible."];
        }

        return $schema;
    }
}
