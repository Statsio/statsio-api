<?php

namespace App\Domain\Ai\StudioTools;

use App\Domain\Ai\BlockCatalog\StudioBlockCatalog;
use App\Domain\Ai\StudioAgentContext;
use App\Domain\Ai\StudioTools\Concerns\DecodesJsonArg;
use App\Domain\Ai\Support\StudioSourceReader;

/**
 * Ajoute un bloc dans une zone (colonne) d'une section, avec son mapping de champs
 * et sa config. Valide le type contre la palette du type de contenu et les colonnes
 * contre le schéma du dataset.
 */
class AddBlockTool implements StudioAgentTool
{
    use DecodesJsonArg;

    public function __construct(
        private readonly StudioBlockCatalog $catalog,
        private readonly StudioSourceReader $reader,
    ) {}

    public function name(): string
    {
        return 'add_block';
    }

    public function description(): string
    {
        return 'Ajoute un bloc dans une zone (section + colonne). field_mapping_json / config_json / '
            .'filters_json sont des objets JSON encodés en chaîne, conformes à la palette.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'ref' => ['type' => 'string', 'description' => 'Référence locale unique (ex. "b1").'],
                'section_ref' => ['type' => 'string', 'description' => 'Section cible. Omis si loop_ref est fourni.'],
                'col' => ['type' => 'integer', 'description' => 'Index de colonne dans la section (0-based). Omis si loop_ref est fourni.'],
                'loop_ref' => ['type' => 'string', 'description' => "Ref d'un bloc conteneur (loop ou if) : le bloc devient son enfant (section_ref/col ignorés)."],
                'type' => ['type' => 'string', 'description' => 'Type de bloc (voir palette).'],
                'dataset_id' => ['type' => 'integer', 'description' => 'Requis pour les blocs de données.'],
                'field_mapping_json' => ['type' => 'string'],
                'config_json' => ['type' => 'string'],
                'filters_json' => ['type' => 'string', 'description' => 'Tableau JSON [{column,operator,value}].'],
            ],
            'required' => ['ref', 'section_ref', 'col', 'type'],
        ];
    }

    public function execute(array $input, StudioAgentContext $context): array
    {
        $ref = trim((string) ($input['ref'] ?? ''));
        $sectionRef = trim((string) ($input['section_ref'] ?? ''));
        $col = (int) ($input['col'] ?? 0);
        $loopRef = trim((string) ($input['loop_ref'] ?? '')) ?: null;
        $type = (string) ($input['type'] ?? '');

        if ($ref === '') {
            return ['error' => 'ref est obligatoire.'];
        }
        if ($context->block($ref) !== null) {
            return ['error' => "La ref de bloc « {$ref} » est déjà utilisée."];
        }

        $meta = $this->catalog->get($type);
        if ($meta === null || ! $this->catalog->isAllowed($type, $context->contentType())) {
            return ['error' => "Le bloc « {$type} » n'est pas autorisé pour le type « {$context->contentType()} »."];
        }

        if ($loopRef !== null) {
            if (! $context->blockIsContainer($loopRef)) {
                return ['error' => "loop_ref « {$loopRef} » ne désigne pas un bloc conteneur (loop / if)."];
            }
            // Scripts imbriqués autorisés ; seuls search, param et formulaires sont interdits.
            $forbidden = ['search', 'param', 'choice', 'checkboxes', 'dropdown', 'scale', 'rating'];
            if (in_array($type, $forbidden, true)) {
                return ['error' => "Un bloc « {$type} » ne peut pas être placé dans un bloc de script."];
            }
        } else {
            if ($sectionRef === '') {
                return ['error' => 'section_ref (ou loop_ref) est obligatoire.'];
            }
            $section = $context->section($sectionRef);
            if ($section === null) {
                return ['error' => "Section « {$sectionRef} » inconnue. Crée-la d'abord avec add_section."];
            }
            if ($col < 0 || $col >= $section['cols']) {
                return ['error' => "col hors limites : la section a {$section['cols']} colonne(s)."];
            }
        }

        $datasetId = isset($input['dataset_id']) ? (int) $input['dataset_id'] : null;
        $fieldMapping = $this->jsonArg($input, 'field_mapping_json');
        $config = $this->jsonArg($input, 'config_json');
        $filters = array_values($this->jsonArg($input, 'filters_json'));

        if ($meta['requiresDataset']) {
            if ($datasetId === null) {
                return ['error' => "Le bloc « {$type} » nécessite dataset_id."];
            }
            $schema = $this->reader->datasetSchema($context->user, $datasetId);
            if ($schema === null) {
                return ['error' => "Dataset {$datasetId} introuvable ou non accessible."];
            }
            $columns = array_column($schema['columns'], 'name');
            if ($unknown = $this->unknownColumns($fieldMapping, $filters, $columns)) {
                return ['error' => 'Colonnes inconnues dans ce dataset : '.implode(', ', $unknown)];
            }
        }

        $context->registerBlock($ref, $type, $sectionRef, $col, false, $loopRef);

        $op = array_filter([
            'op' => 'addBlock',
            'ref' => $ref,
            'sectionRef' => $loopRef === null ? $sectionRef : null,
            'col' => $loopRef === null ? $col : null,
            'loopRef' => $loopRef,
            'type' => $type,
            'datasetId' => $datasetId,
            'fieldMapping' => $fieldMapping ?: null,
            'config' => $config ?: null,
            'filters' => $filters ?: null,
        ], fn ($v) => $v !== null);

        $context->pushOp($op);

        return ['ok' => true, 'ref' => $ref];
    }

    /**
     * @param  array<string,mixed>  $fieldMapping
     * @param  array<int,mixed>  $filters
     * @param  string[]  $columns
     * @return string[]
     */
    private function unknownColumns(array $fieldMapping, array $filters, array $columns): array
    {
        $referenced = [];

        foreach (['xAxis', 'yAxis', 'label', 'value', 'series', 'valueColumn', 'comparisonColumn', 'searchColumn', 'sortColumn', 'distinctColumn', 'resultTitleColumn', 'loopColumn', 'paramColumn'] as $key) {
            if (isset($fieldMapping[$key]) && is_string($fieldMapping[$key])) {
                $referenced[] = $fieldMapping[$key];
            }
        }
        foreach (['yAxes', 'columns', 'resultDescColumns'] as $key) {
            foreach ((array) ($fieldMapping[$key] ?? []) as $c) {
                if (is_string($c)) {
                    $referenced[] = $c;
                }
            }
        }
        foreach ($filters as $filter) {
            if (is_array($filter) && isset($filter['column']) && is_string($filter['column'])) {
                $referenced[] = $filter['column'];
            }
        }

        return array_values(array_unique(array_diff($referenced, $columns)));
    }
}
