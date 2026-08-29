<?php

namespace App\Domain\Ai;

use App\Domain\Ai\BlockCatalog\StudioBlockCatalog;
use App\Models\StudioContent;

/**
 * Construit le prompt système de l'assistant Studio : modèle de données, palette
 * de blocs autorisée pour le type de contenu, arbre courant, contrat des outils.
 */
class StudioAgentPromptBuilder
{
    public function __construct(private readonly StudioBlockCatalog $catalog) {}

    public function build(StudioContent $content): string
    {
        $type = $content->type ?? 'statsdata';

        return implode("\n\n", array_filter([
            $this->intro($content, $type),
            $this->dataModel(),
            $this->palette($type),
            $this->currentTree($content),
            $this->pendingIssues($content),
            $this->guardrails(),
        ]));
    }

    private function intro(StudioContent $content, string $type): string
    {
        return <<<TXT
        Tu es l'assistant du Studio Statsio, un éditeur no-code de contenus data-journalisme.
        Tu aides à construire et modifier le contenu en cours : type « {$type} », titre « {$content->title} ».
        Réponds toujours en français, de façon concise et concrète.

        Tu peux appliquer directement des changements via les outils d'écriture (add_page,
        add_section, add_block, update_block, remove_block, move_block). Chaque changement est
        réversible par l'utilisateur (annulation). Quand tu as fini, réponds par un court résumé
        en texte de ce que tu as fait.

        IMPORTANT :
        - Ne prétends jamais qu'un élément est « déjà bien configuré » sans l'avoir vérifié dans la
          structure JSON ci-dessous (une barre de recherche est configurée uniquement si son
          `fieldMapping.searchSources` est non vide).
        - Si tu as besoin des colonnes d'un dataset, appelle `list_sources` / `get_dataset_schema`.
        TXT;
    }

    private function dataModel(): string
    {
        return <<<'TXT'
        MODÈLE DE DONNÉES DU STUDIO
        - Un contenu a une ou plusieurs *pages* (un seul type de page). Chaque page a un `slug`
          et peut déclarer des *paramètres* (`page.params` : [{ name, column?, datasetId?,
          defaultValue?, fanOut? }]).
        - Un *paramètre* est une variable `{{name}}` que les blocs réutilisent dans leurs filtres,
          titres et textes. Il est piloté à l'exécution par :
          * un bloc `param` (sélecteur pastilles/liste) posé sur la page, ou
          * un bloc `search` : au choix d'un résultat, TOUTES les colonnes de la ligne deviennent
            des paramètres `{{colonne}}`.
          Les blocs filtrent dessus avec `{"column":"<name>","operator":"=","value":"{{<name>}}"}`.
          Un paramètre `fanOut:true` sera publié comme une page indexable par valeur (/slug/{valeur}).
          Un `name` doit être un nom simple (lettres/chiffres/underscore).
        - Pour une page « une vue par valeur » : `add_page` avec params_json=[{name, dataset_id,
          column, fan_out:true}], puis `add_block` type `search` (ou `param`) sur la page, puis les
          blocs de données filtrant sur `{{name}}`.
        - VALEURS CALCULÉES : un jeton `{{ … }}` qui contient un appel de fonction est une expression.
          `{{ AVG(prix@7) }}`, `{{ MAX(prix@7) - MIN(prix@7) }}`, `{{ (MAX(x@7)-MIN(x@7)) * 100 : 0 }}`,
          `{{ AVG(prix@7 | carburant = $carburant) }}`. `@N` = id du dataset ; `$nom` = valeur d'un
          paramètre ; `: N` = décimales. Utilisables dans les titres, textes, items « à retenir »,
          `kpi.config.valueExpression`, `bar/line.config.referenceExpression` (ligne de référence) et
          `line.config.trendExpression` (pastille de tendance). Dans une colonne calculée de tableau,
          `{col}` = valeur de la ligne courante.
        - GRAPHIQUES : `bar/pie.config.markRules` = [{ when: positive|negative|gt|lt|top|bottom|above-ref|below-ref,
          value?, color (hex) }] colore chaque marque conditionnellement (série unique).
        - Chaque page contient des *sections* ordonnées. Une section a un `layout`
          (1-col | 2-cols | 3-cols | 2-1-cols | 1-2-cols) et, optionnellement, un en-tête
          (kicker + title + description), un `theme` de fond (default | dark | accent) et une
          `anchor` (→ sommaire de la page). Renseignés à la création via `add_section`.
        - Une section a des *zones* (colonnes), identifiées `"{sectionId}-{colIndex}"` (colIndex commence à 0).
        - Un *bloc* vit dans une zone. Un bloc de données porte `datasetId`, `fieldMapping`, `config`,
          et optionnellement `filters` ([{column, operator, value}], operators = = != > >= < <= contains not_contains).
        - Blocs CONTENEURS (catégorie script), enfants ajoutés via `add_block` avec `loop_ref` = leur ref :
          * `loop` : répète ses enfants pour chaque valeur distincte de `fieldMapping.loopColumn`.
            Les enfants insèrent la valeur courante via `{{item}}` (ou `{{<loopVar>}}`).
          * `if` : n'affiche ses enfants que si `config.ifParam <ifOperator> ifValue` est vrai.
          Scripts imbriqués autorisés ; seuls search, param et les blocs de formulaire y sont interdits.
        TXT;
    }

    private function palette(string $type): string
    {
        $lines = ["PALETTE DE BLOCS AUTORISÉE POUR LE TYPE « {$type} »"];

        foreach ($this->catalog->forContentType($type) as $blockType => $meta) {
            $fields = array_keys($meta['fieldMapping']);
            $fieldStr = $fields === [] ? '—' : implode(', ', $fields);
            $configStr = $meta['config'] === [] ? '—' : implode(', ', $meta['config']);
            $data = $meta['requiresDataset'] ? ' [nécessite un dataset]' : '';
            $lines[] = "- {$blockType} ({$meta['category']}){$data} : {$meta['description']} "
                ."fieldMapping: {$fieldStr} | config: {$configStr}.";
        }

        $lines[] = 'Blocs de texte : le contenu va dans config.content (HTML), le niveau dans '
            .'config.headingLevel (1|2|3). Ne pas inventer d\'autres clés.';

        return implode("\n", $lines);
    }

    private function currentTree(StudioContent $content): string
    {
        $sections = $content->sections ?? [];
        $pageBySection = [];
        foreach ($sections as $s) {
            $pageBySection[(string) ($s['id'] ?? '')] = (string) ($s['pageId'] ?? 'default');
        }

        $tree = [
            'pages' => $content->pages ?? [['id' => 'default', 'title' => 'Page 1']],
            'sections' => $sections,
            'blocks' => array_map(
                function (array $b) use ($pageBySection) {
                    $zone = (string) ($b['zoneId'] ?? '');
                    $sectionId = str_contains($zone, '-') ? substr($zone, 0, strrpos($zone, '-')) : $zone;

                    return [
                        'id' => $b['id'] ?? null,
                        'type' => $b['type'] ?? null,
                        'zoneId' => $zone,
                        'pageId' => $pageBySection[$sectionId] ?? 'default',
                        'datasetId' => $b['datasetId'] ?? null,
                        'locked' => $b['locked'] ?? false,
                        'title' => $b['config']['title'] ?? null,
                    ];
                },
                $content->blocks ?? [],
            ),
        ];

        return "STRUCTURE ACTUELLE DU CONTENU (JSON)\n".json_encode($tree, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Problèmes déterministes détectés dans la structure — l'agent doit les corriger
     * sans hésiter dès qu'on lui parle de l'élément concerné.
     */
    private function pendingIssues(StudioContent $content): ?string
    {
        $lines = [];
        foreach ($content->blocks ?? [] as $b) {
            $type = $b['type'] ?? '';
            $fm = $b['fieldMapping'] ?? [];

            if ($type === 'search' && empty($fm['searchSources'])) {
                $lines[] = "- Barre de recherche (bloc id \"{$b['id']}\") non configurée — "
                    .'complète-la avec un SEUL update_block : field_mapping_json = '
                    .'{"searchSources":[{"datasetId":"<id>","columns":["<colonnes cherchables>"]}],'
                    .'"resultTitleColumn":"<col titre>","resultDescColumns":["<1-3 cols>"]} '
                    .'(colonnes via list_sources / get_dataset_schema).';
            }
            if ($type === 'param' && empty($fm['paramColumn'])) {
                $lines[] = "- Bloc Paramètre (id \"{$b['id']}\") non configuré — update_block avec "
                    .'field_mapping_json = {"paramColumn":"<colonne>","paramName":"<nom simple>"} et dataset_id.';
            }
        }

        return $lines === [] ? null : "À CORRIGER (fais-le dès qu'on te parle de cette page)\n".implode("\n", $lines);
    }

    private function guardrails(): string
    {
        return <<<'TXT'
        RÈGLES
        - Avant un bloc de données : `list_sources`, puis `get_dataset_schema` pour les colonnes
          exactes. Si aucune source ne convient : `search_public_catalog` puis `attach_public_source`
          (renvoie un dataset_id).
        - Nouveaux éléments : invente une *ref* courte unique (ex. "p1", "s1", "b1"). Les éléments
          existants se désignent par leur id (visible dans la structure ci-dessus). L'ordre des
          appels compte : crée la page puis la section puis les blocs.
        - `field_mapping_json` / `config_json` / `filters_json` sont des objets JSON encodés en
          chaîne, conformes à la palette (ex. field_mapping_json="{\"xAxis\":\"region\",\"yAxes\":[\"population\"],\"aggregate\":\"sum\"}").
        - Ne crée jamais un bloc absent de la palette autorisée.
        - `update_block` fonctionne aussi sur un bloc `locked` (configuration seulement, pas
          move_block/remove_block).
        - Reste minimal : le plus petit changement qui répond à la demande.
        TXT;
    }
}
