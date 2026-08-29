<?php

namespace App\Domain\Ai\StudioTools;

use App\Domain\Ai\StudioAgentContext;
use App\Domain\Ai\StudioTools\Concerns\DecodesJsonArg;
use App\Domain\Ai\Support\StudioSourceReader;

/**
 * Met à jour un bloc existant (config, mapping, filtres, dataset).
 *
 * Autorisé aussi sur les blocs `locked` : on ne peut pas les déplacer/supprimer,
 * mais on peut les configurer (searchSources, resultTitleColumn, …).
 */
class UpdateBlockTool implements StudioAgentTool
{
    use DecodesJsonArg;

    public function __construct(private readonly StudioSourceReader $reader) {}

    public function name(): string
    {
        return 'update_block';
    }

    public function description(): string
    {
        return 'Modifie un bloc existant (y compris verrouillé) : config_json / field_mapping_json / '
            .'filters_json (objets JSON encodés en chaîne, fusionnés), dataset_id. Barre de recherche : '
            .'field_mapping_json = {"searchSources":[{"datasetId":"<id>","columns":[...]}],'
            .'"resultTitleColumn":"<col>","resultDescColumns":["<col>"]} et config_json = '
            .'{"title":"...","searchPlaceholder":"..."}. Bloc param : field_mapping_json = '
            .'{"paramColumn":"<col>","paramName":"<nom simple>"} + dataset_id.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'block_ref' => ['type' => 'string', 'description' => 'Ref ou id du bloc (visible dans la structure).'],
                'dataset_id' => ['type' => 'integer'],
                'field_mapping_json' => ['type' => 'string'],
                'config_json' => ['type' => 'string'],
                'filters_json' => ['type' => 'string'],
                'comparison_filters_json' => ['type' => 'string'],
            ],
            'required' => ['block_ref'],
        ];
    }

    public function execute(array $input, StudioAgentContext $context): array
    {
        $blockRef = trim((string) ($input['block_ref'] ?? ''));
        $block = $context->block($blockRef);

        if ($block === null) {
            return ['error' => "Bloc « {$blockRef} » inconnu."];
        }

        $datasetId = isset($input['dataset_id']) ? (int) $input['dataset_id'] : null;
        if ($datasetId !== null && $this->reader->datasetSchema($context->user, $datasetId) === null) {
            return ['error' => "Dataset {$datasetId} introuvable ou non accessible."];
        }

        $fieldMapping = $this->jsonArg($input, 'field_mapping_json');

        if ($error = $this->validateSearchSources($fieldMapping, $context)) {
            return ['error' => $error];
        }

        $op = array_filter([
            'op' => 'updateBlock',
            'blockRef' => $blockRef,
            'datasetId' => $datasetId,
            'fieldMapping' => $fieldMapping ?: null,
            'config' => $this->jsonArg($input, 'config_json') ?: null,
            'filters' => ($f = array_values($this->jsonArg($input, 'filters_json'))) ? $f : null,
            'comparisonFilters' => ($cf = array_values($this->jsonArg($input, 'comparison_filters_json'))) ? $cf : null,
        ], fn ($v) => $v !== null);

        if (count($op) <= 2) {
            return ['error' => 'Rien à modifier : fournis au moins un champ.'];
        }

        $context->pushOp($op);

        return ['ok' => true];
    }

    /**
     * @param  array<string,mixed>  $fieldMapping
     */
    private function validateSearchSources(array $fieldMapping, StudioAgentContext $context): ?string
    {
        $sourceColumns = [];

        foreach ($fieldMapping['searchSources'] ?? [] as $source) {
            if (! is_array($source)) {
                continue;
            }
            $datasetId = (int) ($source['datasetId'] ?? 0);
            $schema = $datasetId ? $this->reader->datasetSchema($context->user, $datasetId) : null;
            if ($schema === null) {
                return "searchSources : dataset {$datasetId} introuvable ou non accessible.";
            }
            $known = array_column($schema['columns'], 'name');
            $cols = array_map('strval', $source['columns'] ?? []);
            if ($unknown = array_diff($cols, $known)) {
                return 'searchSources : colonnes inconnues — '.implode(', ', $unknown);
            }
            $sourceColumns = [...$sourceColumns, ...$cols];
        }

        // resultTitleColumn / resultDescColumns / urlParams doivent être des colonnes de recherche.
        if ($sourceColumns !== []) {
            $referenced = array_filter([
                ...(array) ($fieldMapping['urlParams'] ?? []),
                ...(array) ($fieldMapping['resultDescColumns'] ?? []),
                $fieldMapping['resultTitleColumn'] ?? null,
            ]);
            if ($bad = array_diff(array_map('strval', $referenced), $sourceColumns)) {
                return 'La barre de recherche référence des colonnes absentes de searchSources : '
                    .implode(', ', $bad).'. Ajoute-les à searchSources.columns.';
            }
        }

        return null;
    }
}
