<?php

namespace App\Domain\Ai\StudioTools;

use App\Domain\Ai\StudioAgentContext;
use App\Domain\Ai\StudioTools\Concerns\DecodesJsonArg;
use App\Domain\Ai\Support\StudioSourceReader;
use App\Domain\Content\Support\PremiumBlockGate;

/**
 * Met à jour un bloc existant (config, mapping, filtres, dataset).
 *
 * Autorisé aussi sur les blocs `locked` : on ne peut pas les déplacer/supprimer,
 * mais on peut les configurer (searchColumns, resultTitleParts, …).
 *
 * Un bloc premium déjà en place reste grandfathéré (affiché, déplaçable, supprimable)
 * si l'auteur perd le Premium — mais ne peut plus être modifié via cet outil (voir
 * PremiumBlockGate::canMutateBlock()).
 */
class UpdateBlockTool implements StudioAgentTool
{
    use DecodesJsonArg;

    public function __construct(
        private readonly StudioSourceReader $reader,
        private readonly PremiumBlockGate $premiumGate,
    ) {}

    public function name(): string
    {
        return 'update_block';
    }

    public function description(): string
    {
        return 'Modifie un bloc existant (y compris verrouillé) : config_json / field_mapping_json / '
            .'filters_json (objets JSON encodés en chaîne, fusionnés), dataset_id. Barre de recherche : dataset_id '
            .'+ field_mapping_json = {"searchColumns":["<col>",...],"resultTitleParts":[{"ref":"<col>"}],'
            .'"resultDescParts":[{"ref":"<col>"}]} et config_json = {"searchPlaceholder":"..."}. Bloc param : '
            .'field_mapping_json = {"paramColumn":"<col>","paramName":"<nom simple>"} + dataset_id.';
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

        $channel = $this->premiumGate->channelForContent($context->content);
        if (! $this->premiumGate->canMutateBlock($context->user, $channel, $block)) {
            return ['error' => "Le bloc « {$blockRef} » ({$block['type']}) est réservé à l'offre Premium — passe à Premium pour le modifier."];
        }

        $datasetId = isset($input['dataset_id']) ? (int) $input['dataset_id'] : null;
        if ($datasetId !== null && $this->reader->datasetSchema($context->user, $datasetId) === null) {
            return ['error' => "Dataset {$datasetId} introuvable ou non accessible."];
        }

        $fieldMapping = $this->jsonArg($input, 'field_mapping_json');

        if ($error = $this->validateSearchColumns($block, $fieldMapping, $datasetId, $context)) {
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
     * @param  array{ref:string,type:string,sectionRef:string,col:int,locked:bool,loopRef:?string,datasetId:?int}  $block
     * @param  array<string,mixed>  $fieldMapping
     */
    private function validateSearchColumns(array $block, array $fieldMapping, ?int $datasetId, StudioAgentContext $context): ?string
    {
        if ($block['type'] !== 'search' || ! isset($fieldMapping['searchColumns'])) {
            return null;
        }

        // Le dataset validé est celui fourni dans cet appel, sinon celui déjà connu du bloc.
        $effectiveDatasetId = $datasetId ?? $block['datasetId'];
        if ($effectiveDatasetId === null) {
            return null; // pas encore de dataset connu (ex. add_block dans le même tour, pas encore rejoué) — rien à valider.
        }

        $schema = $this->reader->datasetSchema($context->user, $effectiveDatasetId);
        if ($schema === null) {
            return "searchColumns : dataset {$effectiveDatasetId} introuvable ou non accessible.";
        }
        $known = array_column($schema['columns'], 'name');

        $referenced = array_map('strval', array_merge(
            (array) ($fieldMapping['searchColumns'] ?? []),
            (array) ($fieldMapping['searchAltColumns'] ?? []),
        ));
        foreach (['resultTitleParts', 'resultDescParts'] as $key) {
            foreach ((array) ($fieldMapping[$key] ?? []) as $part) {
                if (is_array($part) && isset($part['ref'])) {
                    $referenced[] = (string) $part['ref'];
                }
            }
        }

        if ($unknown = array_diff(array_unique($referenced), $known)) {
            return 'Colonnes inconnues dans ce dataset : '.implode(', ', $unknown);
        }

        return null;
    }
}
