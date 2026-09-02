<?php

namespace App\Domain\Ai\BlockCatalog;

/**
 * Manifeste machine-readable des blocs du Studio — source de vérité côté serveur pour
 * (a) construire le prompt système de l'assistant, (b) valider les ops `add_block` /
 * `update_block` renvoyées par le modèle.
 *
 * MIROIR de statsio-front/app/types/studio.ts (`BlockType`, `BLOCK_CATEGORIES`,
 * `FieldMapping`, `BlockConfig`). Toute évolution de la palette de blocs doit être
 * répercutée ici — `StudioBlockCatalogTest` garde la couverture des types.
 *
 * Gating par type de contenu (identique à SidebarBlocks.vue) :
 *  - `form`  → `survey` uniquement
 *  - `search` (catégorie « special ») → `statsdata` uniquement
 *  - `text` / `charts` / `data` / `editorial` → les 3 types
 */
class StudioBlockCatalog
{
    private const ALL_TYPES = ['statsdata', 'article', 'survey'];

    private const AGGREGATES = ['sum', 'avg', 'count', 'min', 'max'];

    /**
     * Champ commun aux blocs de données : colonnes calculées (combinaisons
     * arithmétiques par ligne, avant agrégation), référencées `"calc:<id>"`.
     *
     * @return array<string,mixed>
     */
    private function calcColumnsField(): array
    {
        return [
            'calcColumns' => [
                'role' => 'any',
                'list' => true,
                'required' => false,
                'description' => 'Colonnes calculées : [{ id, label, operands: [{ column | value, op?: +|-|*|/ }] }]. '
                    .'Réf `"calc:<id>"` utilisable comme xAxis / yAxes / series / part de camembert / colonne de filtre. '
                    .'Ex. calc `t` = Admis + Présents, puis yAxes:["calc:t"] + aggregate:"avg".',
            ],
        ];
    }

    /**
     * Libellé d'affichage par colonne (`{ ref: libellé }`) — en-têtes de tableau,
     * légendes bar/line, titres d'axes.
     *
     * @return array<string,mixed>
     */
    private function columnLabelsField(): array
    {
        return [
            'columnLabels' => [
                'role' => 'labelMap',
                'required' => false,
                'description' => 'Libellé d\'affichage par réf de colonne : { "<ref>": "<libellé>" }. '
                    .'Remplace le nom brut dans la légende / le titre d\'axe.',
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function perColumnAggregatesField(): array
    {
        return [
            'aggregates' => [
                'role' => 'any',
                'list' => true,
                'required' => false,
                'description' => 'Fonction d\'agrégat PAR colonne de valeur : [{ column, fn }] (fn parmi sum|avg|count|min|max). '
                    .'Prioritaire sur `aggregate` (fonction unique).',
            ],
        ];
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function all(): array
    {
        return [
            // ─── Texte ──────────────────────────────────────────────────────
            'heading' => $this->text('Titre', 'Titre de section (H1/H2/H3).', ['content', 'headingLevel', 'textAlign']),
            'paragraph' => $this->text('Paragraphe', 'Bloc de texte libre (HTML riche).', ['content', 'textAlign', 'fontSize', 'lineHeight']),
            'quote' => $this->text('Citation', 'Citation mise en forme.', ['content', 'textAlign']),
            'callout' => $this->text('Encadré', 'Note ou information mise en avant.', ['content', 'calloutColor']),

            // ─── Graphiques ─────────────────────────────────────────────────
            'bar' => [
                'category' => 'charts',
                'label' => 'Barres',
                'description' => 'Comparaison de valeurs entre catégories.',
                'contentTypes' => self::ALL_TYPES,
                'requiresDataset' => true,
                'fieldMapping' => [
                    'xAxis' => ['role' => 'dimension', 'required' => true, 'description' => 'Colonne catégorielle (abscisse).'],
                    'yAxes' => ['role' => 'measure', 'list' => true, 'required' => true, 'description' => 'Une ou plusieurs colonnes numériques (séries).'],
                    'series' => ['role' => 'dimension', 'required' => false, 'description' => 'Colonne de regroupement en séries.'],
                    ...$this->columnLabelsField(),
                    'aggregate' => ['enum' => self::AGGREGATES, 'required' => false, 'default' => 'sum'],
                    ...$this->perColumnAggregatesField(),
                    ...$this->calcColumnsField(),
                ],
                'config' => ['stacked', 'showLegend', 'colors', 'seriesLimit', 'logScale', 'orientation', 'barStyle', 'showValueLabels', 'sortColumn', 'sortDirection', 'trendLabel', 'trendDirection', 'format', 'prefix', 'suffix', 'markRules', 'referenceExpression', 'referenceLabel'],
            ],
            'line' => [
                'category' => 'charts',
                'label' => 'Lignes',
                'description' => 'Évolution d\'une ou plusieurs séries, typiquement dans le temps.',
                'contentTypes' => self::ALL_TYPES,
                'requiresDataset' => true,
                'fieldMapping' => [
                    'xAxis' => ['role' => 'dimension', 'required' => true, 'description' => 'Colonne d\'axe (souvent temporelle).'],
                    'yAxes' => ['role' => 'measure', 'list' => true, 'required' => true, 'description' => 'Une ou plusieurs colonnes numériques (séries).'],
                    'series' => ['role' => 'dimension', 'required' => false, 'description' => 'Colonne de regroupement en séries.'],
                    ...$this->columnLabelsField(),
                    'aggregate' => ['enum' => self::AGGREGATES, 'required' => false, 'default' => 'sum'],
                    ...$this->perColumnAggregatesField(),
                    ...$this->calcColumnsField(),
                ],
                'config' => ['smooth', 'showLegend', 'colors', 'seriesLimit', 'logScale', 'showValueLabels', 'sortColumn', 'sortDirection', 'trendLabel', 'trendDirection', 'trendExpression', 'lineFill', 'referenceExpression', 'referenceLabel', 'format', 'prefix', 'suffix'],
            ],
            'pie' => [
                'category' => 'charts',
                'label' => 'Camembert',
                'description' => 'Répartition proportionnelle. DEUX modes (config.pieMode) : '
                    .'"column" (défaut) = une part par valeur distincte de fieldMapping.label, mesurée par fieldMapping.value ; '
                    .'"segments" = parts calculées explicites via fieldMapping.pieSegments.',
                'contentTypes' => self::ALL_TYPES,
                'requiresDataset' => true,
                'fieldMapping' => [
                    'label' => ['role' => 'dimension', 'required' => false, 'description' => 'Mode "column" : colonne des parts.'],
                    'value' => ['role' => 'measure', 'required' => false, 'description' => 'Mode "column" : colonne numérique agrégée.'],
                    'aggregate' => ['enum' => self::AGGREGATES, 'required' => false, 'default' => 'sum'],
                    'pieSegments' => [
                        'role' => 'any',
                        'list' => true,
                        'required' => false,
                        'description' => 'Mode "segments" : [{ fn, column, label? }]. fn parmi sum|avg|count|min|max|remainder '
                            .'("remainder" = SUM(column) − somme des autres parts, ex. « Non admis »). column accepte une réf "calc:<id>".',
                    ],
                    ...$this->calcColumnsField(),
                ],
                'config' => ['pieMode', 'showLegend', 'colors', 'format', 'prefix', 'suffix'],
            ],

            // ─── Données ────────────────────────────────────────────────────
            'table' => [
                'category' => 'data',
                'label' => 'Tableau',
                'description' => 'Données tabulaires paginées et triables.',
                'contentTypes' => self::ALL_TYPES,
                'requiresDataset' => true,
                'fieldMapping' => [
                    'columns' => ['role' => 'any', 'list' => true, 'required' => true, 'description' => 'Colonnes affichées, dans l\'ordre.'],
                    'columnLabels' => ['role' => 'labelMap', 'required' => false, 'description' => 'Libellé custom par colonne { colonne: libellé }.'],
                    'columnFormats' => ['role' => 'any', 'required' => false, 'description' => 'Format + alignement par colonne : { colonne: { format: text|number|percent|currency|mono, align: left|center|right } }.'],
                    'computedColumns' => ['role' => 'any', 'list' => true, 'required' => false, 'description' => 'Colonnes calculées : [{ name, expression }] — expression = {col} (valeur de ligne) + agrégats FN(col@id).'],
                    'cellRules' => ['role' => 'any', 'list' => true, 'required' => false, 'description' => 'Mise en forme conditionnelle : [{ column, when: positive|negative|gt|lt|top|bottom, value?, color (hex), bold? }].'],
                ],
                'config' => ['sortable', 'showPagination', 'pageSize', 'rowLimit', 'sortColumn', 'sortDirection', 'distinctColumn'],
            ],
            'kpi' => [
                'category' => 'data',
                'label' => 'KPI',
                'description' => 'Indicateur clé unique, avec comparaison optionnelle.',
                'contentTypes' => self::ALL_TYPES,
                'requiresDataset' => true,
                'fieldMapping' => [
                    'kpiValue' => [
                        'role' => 'any',
                        'list' => true,
                        'required' => false,
                        'description' => 'Valeur = combinaison d\'agrégats : [{ fn, column, op? }] (fn parmi '
                            .'sum|avg|count|min|max, op parmi +|-|*|/ relie au terme précédent). '
                            .'Ex. [{fn:"max",column:"prix"},{op:"-",fn:"min",column:"prix"}] = MAX(prix) − MIN(prix). '
                            .'column accepte "calc:<id>". Prioritaire sur valueColumn / config.valueExpression.',
                    ],
                    'valueColumn' => ['role' => 'measure', 'required' => false, 'description' => 'Legacy : colonne unique (préférer kpiValue).'],
                    'aggregate' => ['enum' => self::AGGREGATES, 'required' => false, 'default' => 'sum'],
                    'comparisonColumn' => ['role' => 'measure', 'required' => false, 'description' => 'Colonne de la valeur de comparaison.'],
                    ...$this->calcColumnsField(),
                ],
                'config' => ['format', 'prefix', 'suffix', 'comparisonFormat', 'comparisonLabel', 'trendLabel', 'trendDirection', 'valueExpression'],
                'supportsComparisonFilters' => true,
            ],
            'record' => [
                'category' => 'data',
                'label' => 'Fiche',
                'description' => 'Fiche détaillée d\'un SEUL enregistrement : applique les filtres + le tri du bloc et prend la première ligne '
                    .'(tri croissant = min, décroissant = max). recordTitleColumn = titre de la fiche, les autres colonnes = paires libellé / valeur.',
                'contentTypes' => self::ALL_TYPES,
                'requiresDataset' => true,
                'fieldMapping' => [
                    'columns' => ['role' => 'any', 'list' => true, 'required' => true, 'description' => 'Colonnes affichées dans la fiche.'],
                    'recordTitleColumn' => ['role' => 'any', 'required' => false, 'description' => 'Colonne utilisée comme titre (défaut = 1re colonne).'],
                    'columnLabels' => ['role' => 'labelMap', 'required' => false, 'description' => 'Libellé custom par colonne { colonne: libellé }.'],
                ],
                'config' => ['title', 'sortColumn', 'sortDirection'],
            ],
            'related' => [
                'category' => 'data',
                'label' => 'Entités liées',
                'description' => 'Puces vers des enregistrements liés (communes voisines, contenus similaires…). Applique les filtres du bloc, '
                    .'limite à config.rowLimit. Sur une page « fan-out », chaque puce lie automatiquement vers la page de sa valeur.',
                'contentTypes' => self::ALL_TYPES,
                'requiresDataset' => true,
                'fieldMapping' => [
                    'columns' => ['role' => 'any', 'list' => true, 'required' => true, 'description' => 'Colonnes : la 1re = libellé de la puce, la 2e (optionnelle) = valeur affichée à côté.'],
                ],
                'config' => ['title', 'rowLimit', 'sortColumn', 'sortDirection'],
            ],

            // ─── Éditorial ──────────────────────────────────────────────────
            'image' => $this->editorial('Image', 'Image avec légende.', ['imageUrl', 'imageAlt', 'imageCaption', 'imageAlign', 'imageWidth']),
            'video' => $this->editorial('Vidéo', 'Vidéo embarquée (YouTube, Vimeo, Dailymotion).', ['videoUrl', 'videoCaption']),
            'button' => $this->editorial('Bouton', 'Bouton d\'appel à l\'action.', ['buttonLabel', 'buttonUrl', 'buttonVariant', 'buttonAlign', 'buttonSize']),
            'link-card' => $this->editorial('Carte de lien', 'Carte de prévisualisation d\'un lien externe.', ['linkUrl', 'linkTitle', 'linkDescription', 'linkImage', 'linkDomain']),
            'retenir' => $this->editorial('À retenir', 'Liste de points clés mis en avant.', ['retenirTitle', 'retenirItems', 'retenirColor']),
            'map' => $this->editorial('Carte', 'Point GPS unique (vignette + coordonnées + lien OpenStreetMap). mapLat / mapLng acceptent des jetons {{colonne}}.', ['mapLat', 'mapLng', 'mapLabel']),
            'field-grid' => $this->editorial('Grille de champs', 'Grille compacte de paires libellé / valeur (bandeau méta du héro, encadré méthodologie). Les valeurs acceptent les jetons {{colonne}} et expressions.', ['fieldGridItems', 'fieldGridColumns']),

            // ─── Formulaire (survey uniquement) ─────────────────────────────
            'choice' => $this->form('Choix unique', 'Question à réponse unique (boutons radio).', ['formOptions', 'formRequired']),
            'checkboxes' => $this->form('Cases à cocher', 'Question à réponses multiples.', ['formOptions', 'formRequired']),
            'dropdown' => $this->form('Liste déroulante', 'Sélection d\'une option dans une liste.', ['formOptions', 'formRequired']),
            'scale' => $this->form('Échelle linéaire', 'Note sur une échelle numérique.', ['scaleMin', 'scaleMax', 'scaleMinLabel', 'scaleMaxLabel', 'formRequired']),
            'rating' => $this->form('Avis', 'Notation en étoiles.', ['ratingMax', 'formRequired']),

            // ─── Script (logique) ──────────────────────────────────────────
            'loop' => [
                'category' => 'script',
                'label' => 'Boucle',
                'description' => 'Conteneur qui répète les blocs qu\'il contient pour chaque valeur distincte d\'une colonne. '
                    .'La valeur courante est exposée aux blocs enfants via la variable {{item}} (utilisable dans leurs filtres, titres et textes). '
                    .'Ajoute les enfants avec add_block en passant loop_ref (la ref du bloc loop) au lieu de section_ref/col. '
                    .'Les scripts imbriqués sont autorisés ; seuls search, param et les blocs de formulaire sont interdits dans un script.',
                'contentTypes' => self::ALL_TYPES,
                'requiresDataset' => true,
                'fieldMapping' => [
                    'loopColumn' => ['role' => 'dimension', 'required' => true, 'description' => 'Colonne dont on parcourt les valeurs distinctes.'],
                    'loopVar' => ['role' => 'varName', 'required' => false, 'default' => 'item', 'description' => 'Nom de la variable exposée aux enfants.'],
                ],
                'config' => ['title', 'loopLimit', 'loopLayout'],
                'isContainer' => true,
            ],
            'if' => [
                'category' => 'script',
                'label' => 'Condition',
                'description' => 'Conteneur qui n\'affiche ses blocs enfants QUE si un paramètre de page remplit une condition '
                    .'(config.ifParam <ifOperator> ifValue, ifOperator parmi = != > >= < <= contains not_contains ; '
                    .'ifValue accepte des jetons {{autre_param}}). Ajoute les enfants avec add_block en passant '
                    .'loop_ref = la ref du bloc if.',
                'contentTypes' => self::ALL_TYPES,
                'requiresDataset' => false,
                'fieldMapping' => [],
                'config' => ['ifParam', 'ifOperator', 'ifValue'],
                'isContainer' => true,
            ],

            // ─── Spécial (statsdata uniquement) ─────────────────────────────
            'search' => [
                'category' => 'special',
                'label' => 'Recherche',
                'description' => 'Barre de recherche sur un ou plusieurs datasets. Au choix d\'un résultat, TOUTES les colonnes '
                    .'de la ligne deviennent des paramètres de page {{colonne}} et les blocs qui filtrent dessus se rechargent. '
                    .'`targetPageId` optionnel : ouvre une AUTRE page au clic (sinon filtre la page courante).',
                'contentTypes' => ['statsdata'],
                'requiresDataset' => false,
                'fieldMapping' => [
                    'searchSources' => ['role' => 'searchSources', 'required' => true, 'description' => 'Sources interrogées : [{ datasetId, columns: [...] }].'],
                    'targetPageId' => ['role' => 'pageRef', 'required' => false, 'description' => 'Page ouverte au clic (optionnel).'],
                    'resultTitleColumn' => ['role' => 'any', 'required' => false],
                    'resultDescColumns' => ['role' => 'any', 'list' => true, 'required' => false],
                ],
                'config' => ['searchPlaceholder', 'title'],
            ],
            // ─── Statsio (article uniquement) ──────────────────────────────
            'sd-embed' => [
                'category' => 'statsio',
                'label' => 'Bloc Statsdata',
                'description' => 'Réutilise un bloc (graphique, KPI, tableau ou recherche) d\'un Statsdata PUBLIÉ, '
                    .'même s\'il appartient à une autre chaîne / un autre utilisateur. Affiche ses données en direct '
                    .'plus un lien « Ouvrir le Statsdata complet ». config.sourceSlug = slug du Statsdata source, '
                    .'config.sourceBlockId = id du bloc réutilisé (voir GET /studio/content/public/{slug}/blocks).',
                'contentTypes' => ['article'],
                'requiresDataset' => false,
                'fieldMapping' => [],
                'config' => ['sourceSlug', 'sourceBlockId', 'sourceBlockType', 'sourceDocTitle', 'showSourceLink'],
            ],

            'param' => [
                'category' => 'special',
                'label' => 'Paramètre',
                'description' => 'Sélecteur (pastilles ou liste déroulante) alimenté par les valeurs distinctes d\'une colonne. '
                    .'À chaque choix il écrit pageParams[<paramName>] ; les blocs de la page qui filtrent sur {{<paramName>}} '
                    .'se rechargent. paramName doit être un nom simple (lettres/chiffres/underscore).',
                'contentTypes' => ['statsdata'],
                'requiresDataset' => true,
                'fieldMapping' => [
                    'paramColumn' => ['role' => 'dimension', 'required' => true, 'description' => 'Colonne dont les valeurs distinctes peuplent le contrôle.'],
                    'paramName' => ['role' => 'varName', 'required' => false, 'description' => 'Nom du paramètre écrit dans la page (défaut = paramColumn).'],
                ],
                'config' => ['title', 'paramControl', 'paramDefault', 'paramAllowAll', 'paramAllLabel'],
            ],
        ];
    }

    /** @return string[] */
    public function types(): array
    {
        return array_keys($this->all());
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function forContentType(string $contentType): array
    {
        return array_filter(
            $this->all(),
            fn (array $block) => in_array($contentType, $block['contentTypes'], true),
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    public function get(string $blockType): ?array
    {
        return $this->all()[$blockType] ?? null;
    }

    public function isAllowed(string $blockType, string $contentType): bool
    {
        $block = $this->get($blockType);

        return $block !== null && in_array($contentType, $block['contentTypes'], true);
    }

    /**
     * @param  string[]  $configKeys
     * @return array<string,mixed>
     */
    private function text(string $label, string $description, array $configKeys): array
    {
        return [
            'category' => 'text',
            'label' => $label,
            'description' => $description,
            'contentTypes' => self::ALL_TYPES,
            'requiresDataset' => false,
            'fieldMapping' => [],
            'config' => $configKeys,
        ];
    }

    /**
     * @param  string[]  $configKeys
     * @return array<string,mixed>
     */
    private function editorial(string $label, string $description, array $configKeys): array
    {
        return [
            'category' => 'editorial',
            'label' => $label,
            'description' => $description,
            'contentTypes' => self::ALL_TYPES,
            'requiresDataset' => false,
            'fieldMapping' => [],
            'config' => $configKeys,
        ];
    }

    /**
     * @param  string[]  $configKeys
     * @return array<string,mixed>
     */
    private function form(string $label, string $description, array $configKeys): array
    {
        return [
            'category' => 'form',
            'label' => $label,
            'description' => $description,
            'contentTypes' => ['survey'],
            'requiresDataset' => false,
            'fieldMapping' => [],
            'config' => $configKeys,
        ];
    }
}
