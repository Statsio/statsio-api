<?php

namespace App\Domain\Ai\StudioTools;

use App\Domain\Ai\StudioAgentContext;
use App\Domain\Ai\StudioTools\Concerns\DecodesJsonArg;
use App\Domain\Ai\Support\StudioSourceReader;

/**
 * Ajoute une page au contenu. Une page peut déclarer des *paramètres* : des
 * variables `{{nom}}` que les blocs réutilisent dans leurs filtres / titres.
 * Un paramètre est alimenté par un bloc `param` (sélecteur) ou un bloc `search`
 * posé ensuite sur la page. `fan_out` marque le paramètre pour la génération
 * d'une page indexable par valeur (`/slug/{valeur}`).
 */
class AddPageTool implements StudioAgentTool
{
    use DecodesJsonArg;

    public function __construct(private readonly StudioSourceReader $reader) {}

    public function name(): string
    {
        return 'add_page';
    }

    public function description(): string
    {
        return 'Ajoute une page. Pour une page pilotée par une valeur (carburant, commune…), passe '
            .'params_json = [{"name":"<nom simple>","dataset_id":<id>,"column":"<colonne>","default_value":"<optionnel>","fan_out":true}] '
            .'puis pose un bloc `param` ou `search` sur la page et fais filtrer les blocs sur {{<name>}}.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'ref' => ['type' => 'string', 'description' => 'Référence locale unique de la nouvelle page (ex. "p1").'],
                'title' => ['type' => 'string'],
                'icon' => ['type' => 'string', 'description' => 'Emoji optionnel.'],
                'params_json' => [
                    'type' => 'string',
                    'description' => 'Tableau JSON de paramètres déclarés : '
                        .'[{ name, dataset_id?, column?, default_value?, fan_out? }]. '
                        .'`name` = nom simple (lettres/chiffres/underscore). Si dataset_id + column sont fournis, '
                        .'column doit exister dans ce dataset.',
                ],
            ],
            'required' => ['ref', 'title'],
        ];
    }

    public function execute(array $input, StudioAgentContext $context): array
    {
        $ref = trim((string) ($input['ref'] ?? ''));
        $title = trim((string) ($input['title'] ?? ''));

        if ($ref === '' || $title === '') {
            return ['error' => 'ref et title sont obligatoires.'];
        }
        if ($context->hasPage($ref)) {
            return ['error' => "La ref de page « {$ref} » est déjà utilisée."];
        }

        $op = ['op' => 'addPage', 'ref' => $ref, 'title' => $title];
        if (isset($input['icon']) && is_string($input['icon'])) {
            $op['icon'] = $input['icon'];
        }

        $params = [];
        foreach ($this->jsonArg($input, 'params_json') as $raw) {
            if (! is_array($raw)) {
                continue;
            }
            $name = trim((string) ($raw['name'] ?? ''));
            if ($name === '' || ! preg_match('/^\w+$/', $name)) {
                return ['error' => "Paramètre « {$name} » : le nom doit être simple (lettres, chiffres, underscore)."];
            }

            $entry = ['name' => $name];
            $datasetId = isset($raw['dataset_id']) ? (int) $raw['dataset_id'] : 0;
            $column = isset($raw['column']) ? (string) $raw['column'] : '';

            if ($datasetId > 0) {
                $schema = $this->reader->datasetSchema($context->user, $datasetId);
                if ($schema === null) {
                    return ['error' => "Dataset {$datasetId} introuvable ou non accessible."];
                }
                if ($column !== '' && ! in_array($column, array_column($schema['columns'], 'name'), true)) {
                    return ['error' => "Colonne « {$column} » absente du dataset {$datasetId}."];
                }
                $entry['datasetId'] = (string) $datasetId;
            }
            if ($column !== '') {
                $entry['column'] = $column;
                $entry['slugColumn'] = $column;
            }
            if (isset($raw['default_value']) && $raw['default_value'] !== '') {
                $entry['defaultValue'] = (string) $raw['default_value'];
            }
            if (! empty($raw['fan_out'])) {
                $entry['fanOut'] = true;
            }
            $params[] = $entry;
        }

        if ($params !== []) {
            if ($context->contentType() !== 'statsdata') {
                return ['error' => 'Les paramètres de page ne sont disponibles que pour le type statsdata.'];
            }
            $op['params'] = $params;
        }

        $context->registerPage($ref);
        $context->pushOp($op);

        return ['ok' => true, 'ref' => $ref];
    }
}
