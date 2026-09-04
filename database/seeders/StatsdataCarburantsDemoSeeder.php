<?php

namespace Database\Seeders;

use App\Domain\Content\Actions\PublishStudioContentAction;
use App\Models\DataIngestion\Dataset;
use App\Models\DataIngestion\DatasetColumn;
use App\Models\DataIngestion\DatasetVersion;
use App\Models\DataIngestion\DataSource;
use App\Models\StudioContent;
use App\Models\User\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Reconstruit le StatsData de démonstration « Prix de l'essence en France »
 * (maquette v2) : 3 jeux de données mock + une page principale + une page
 * « commune » fan-out, avec TOUS les blocs configurés.
 *
 *   php artisan db:seed --class=Database\\Seeders\\StatsdataCarburantsDemoSeeder
 *
 * Idempotent : réécrit les datasets `[démo carburants] …` et le contenu
 * `prix-de-lessence-en-france` à chaque exécution.
 */
class StatsdataCarburantsDemoSeeder extends Seeder
{
    private const SLUG = 'prix-de-lessence-en-france';

    private const FUELS = ['Gazole', 'SP95', 'SP98', 'SP95-E10', 'E85', 'GPLc'];

    /** Prix de base €/L par carburant. */
    private const BASE = [
        'Gazole' => 1.72, 'SP95' => 1.85, 'SP98' => 1.94,
        'SP95-E10' => 1.79, 'E85' => 0.89, 'GPLc' => 1.02,
    ];

    /** region => [code, facteur prix, nb stations approx]. */
    private const REGIONS = [
        'Île-de-France' => ['11', 1.06, 1180],
        'Auvergne-Rhône-Alpes' => ['84', 1.01, 1460],
        'Nouvelle-Aquitaine' => ['75', 0.99, 1290],
        'Occitanie' => ['76', 1.00, 1120],
        'Hauts-de-France' => ['32', 0.98, 940],
        'Grand Est' => ['44', 0.99, 900],
        "Provence-Alpes-Côte d'Azur" => ['93', 1.05, 780],
        'Pays de la Loire' => ['52', 0.97, 640],
        'Normandie' => ['28', 0.98, 560],
        'Bretagne' => ['53', 0.96, 610],
        'Bourgogne-Franche-Comté' => ['27', 0.99, 470],
        'Centre-Val de Loire' => ['24', 1.00, 430],
        'Corse' => ['94', 1.11, 90],
    ];

    /** commune => [cp, dept, code_dept, region, lat, lon]. Grigny en double = démo homonyme. */
    private const COMMUNES = [
        ['Lyon', '69001', 'Rhône', '69', 'Auvergne-Rhône-Alpes', 45.7674, 4.8340],
        ['Villeurbanne', '69100', 'Rhône', '69', 'Auvergne-Rhône-Alpes', 45.7719, 4.8902],
        ['Grigny', '69520', 'Rhône', '69', 'Auvergne-Rhône-Alpes', 45.6100, 4.7900],
        ['Grigny', '91350', 'Essonne', '91', 'Île-de-France', 48.6540, 2.3860],
        ['Paris 15e', '75015', 'Paris', '75', 'Île-de-France', 48.8417, 2.2930],
        ['Bordeaux', '33000', 'Gironde', '33', 'Nouvelle-Aquitaine', 44.8378, -0.5792],
        ['Toulouse', '31000', 'Haute-Garonne', '31', 'Occitanie', 43.6045, 1.4442],
        ['Lille', '59000', 'Nord', '59', 'Hauts-de-France', 50.6292, 3.0573],
        ['Rennes', '35000', 'Ille-et-Vilaine', '35', 'Bretagne', 48.1173, -1.6778],
        ['Marseille 8e', '13008', 'Bouches-du-Rhône', '13', "Provence-Alpes-Côte d'Azur", 43.2670, 5.3830],
        ['Nantes', '44000', 'Loire-Atlantique', '44', 'Pays de la Loire', 47.2184, -1.5536],
        ['Strasbourg', '67000', 'Bas-Rhin', '67', 'Grand Est', 48.5734, 7.7521],
    ];

    private const ENSEIGNES = ['TotalEnergies', 'Carrefour', 'E.Leclerc', 'Intermarché', 'Esso Express', 'BP', 'Avia', 'Système U'];

    public function run(): void
    {
        $content = StudioContent::where('slug', self::SLUG)->first();

        if (! $content) {
            $user = User::query()->orderBy('id')->first();
            if (! $user) {
                $this->command?->error('Aucun utilisateur — créez un compte puis relancez.');

                return;
            }
            $content = new StudioContent(['user_id' => $user->id, 'slug' => self::SLUG]);
        }

        $userId = $content->user_id;

        // ── Datasets : purge des jeux de démo précédents ───────────────────────
        $disk = config('statsio.data_ingestion.datasets_disk', 'local');
        $oldSourceIds = DataSource::where('user_id', $userId)
            ->where('name', 'like', '[démo carburants]%')
            ->pluck('id');

        if ($oldSourceIds->isNotEmpty()) {
            $oldDatasetIds = Dataset::whereIn('data_source_id', $oldSourceIds)->pluck('id');
            DatasetVersion::whereIn('dataset_id', $oldDatasetIds)
                ->whereNotNull('parquet_storage_path')
                ->pluck('parquet_storage_path')
                ->each(fn ($path) => Storage::disk($disk)->delete($path));
            DataSource::whereIn('id', $oldSourceIds)->get()->each->delete();
        }

        [$schemaA, $rowsA] = $this->stationRows();
        [$schemaB, $rowsB] = $this->regionRows();
        [$schemaC, $rowsC] = $this->historyRows();

        $dsA = $this->mockDataset($userId, '[démo carburants] relevés stations', $schemaA, $rowsA);
        $dsB = $this->mockDataset($userId, '[démo carburants] prix moyens par région', $schemaB, $rowsB);
        $dsC = $this->mockDataset($userId, '[démo carburants] historique annuel', $schemaC, $rowsC);

        $A = (string) $dsA->id;
        $B = (string) $dsB->id;
        $C = (string) $dsC->id;

        // ── Contenu ────────────────────────────────────────────────────────────
        $content->fill([
            'user_id' => $userId,
            'title' => 'Le prix des carburants, station par station',
            'type' => 'statsdata',
            'status' => 'draft',
            'description' => "11 132 stations relevées en France métropolitaine et outre-mer. Comparez les six carburants, mesurez l'écart entre régions, puis ouvrez la fiche détaillée de votre commune.",
            'categories' => ['energie', 'mobilite'],
            'coverage' => 'nationale',
        ]);

        [$pages, $sections, $blocks] = $this->buildDocument($A, $B, $C);
        $content->pages = $pages;
        $content->sections = $sections;
        $content->blocks = $blocks;
        $content->save();

        // Publie (v1, ou nouvelle version si le contenu de démo existait déjà).
        app(PublishStudioContentAction::class)
            ->execute($content->fresh(), User::findOrFail($userId), 'user');

        Cache::forget('studio.public.index');
        Cache::forget('studio.public.index.statsdata');
        Cache::forget("studio.public.show.{$content->slug}");
        Cache::forget("studio.public.show.{$content->id}");

        $this->command?->info("StatsData « {$content->title} » reconstruit : ".count($blocks)." blocs, datasets {$A}/{$B}/{$C}.");
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Données mock
    // ─────────────────────────────────────────────────────────────────────────

    /** @return array{0: array<string>, 1: array<int, array<int, mixed>>} */
    private function stationRows(): array
    {
        $schema = ['id_station', 'enseigne', 'adresse', 'commune', 'code_postal', 'departement', 'code_departement', 'region', 'latitude', 'longitude', 'carburant', 'prix', 'prix_j7', 'date_maj'];
        $rows = [];
        $sid = 1000;

        foreach (self::COMMUNES as $c) {
            [$commune, $cp, $dept, $codeDept, $region, $lat, $lon] = $c;
            $regionFactor = self::REGIONS[$region][1] ?? 1.0;
            $nbStations = 3 + ($sid % 3); // 3..5

            for ($s = 0; $s < $nbStations; $s++) {
                $sid++;
                $enseigne = self::ENSEIGNES[($sid + $s) % count(self::ENSEIGNES)];
                $jitterLat = $lat + (($sid % 7) - 3) * 0.004;
                $jitterLon = $lon + (($s % 5) - 2) * 0.004;
                $adresse = (10 + ($sid % 180)).' '.['avenue', 'rue', 'boulevard', 'route'][$sid % 4].' '.['de la République', 'Jean Jaurès', 'du Général de Gaulle', 'de Paris', 'Gambetta'][$s % 5];
                // GD (grande distribution) casse les prix ; stations autoroutières les montent.
                $enseigneFactor = in_array($enseigne, ['Carrefour', 'E.Leclerc', 'Intermarché', 'Système U'], true) ? 0.965 : ($enseigne === 'TotalEnergies' ? 1.02 : 1.0);

                foreach (self::FUELS as $fi => $fuel) {
                    // E85 / GPLc pas distribués partout.
                    if ($fuel === 'E85' && ($sid % 3 === 0)) {
                        continue;
                    }
                    if ($fuel === 'GPLc' && ($sid % 2 === 0)) {
                        continue;
                    }
                    $noise = ((($sid * 13 + $fi * 7 + $s * 5) % 21) - 10) / 1000; // ±0.010
                    $prix = round(self::BASE[$fuel] * $regionFactor * $enseigneFactor + $noise, 3);
                    // Prix il y a 7 jours : légèrement plus haut (tendance baissière), ±1,5 %.
                    $prixJ7 = round($prix * (1 + (12 - (($sid * 5 + $fi * 3) % 20)) / 1000), 3);
                    $rows[] = [
                        'ST'.$sid, $enseigne, $adresse, $commune, $cp, $dept, $codeDept, $region,
                        round($jitterLat, 5), round($jitterLon, 5), $fuel, $prix, $prixJ7, '2026-08-'.str_pad((string) (20 + ($sid % 8)), 2, '0', STR_PAD_LEFT),
                    ];
                }
            }
        }

        return [$schema, $rows];
    }

    /** @return array{0: array<string>, 1: array<int, array<int, mixed>>} */
    private function regionRows(): array
    {
        $schema = ['region', 'code_region', 'carburant', 'prix_moyen', 'prix_mois_prec', 'nb_stations', 'ecart_national'];
        $rows = [];

        foreach (self::FUELS as $fuel) {
            // Moyenne nationale = base * moyenne des facteurs régionaux.
            $factors = array_map(fn ($r) => $r[1], array_values(self::REGIONS));
            $national = round(self::BASE[$fuel] * (array_sum($factors) / count($factors)), 3);
            $i = 0;

            foreach (self::REGIONS as $region => [$code, $factor, $nb]) {
                $prix = round(self::BASE[$fuel] * $factor, 3);
                // Mois précédent : +/- 1 à 3 % selon la région (tendance baissière globale).
                $prec = round($prix * (1 + ((($i++ * 7 + strlen($fuel)) % 5) - 1) / 100), 3);
                $rows[] = [$region, $code, $fuel, $prix, $prec, $nb, round($prix - $national, 3)];
            }
        }

        return [$schema, $rows];
    }

    /** @return array{0: array<string>, 1: array<int, array<int, mixed>>} */
    private function historyRows(): array
    {
        $schema = ['annee', 'carburant', 'prix_moyen'];
        // Indice multiplicatif par année (creux 2020, pic 2022).
        $index = ['2018' => 0.83, '2019' => 0.86, '2020' => 0.74, '2021' => 0.92, '2022' => 1.12, '2023' => 1.03, '2024' => 0.99, '2025' => 1.0];
        $rows = [];
        foreach ($index as $year => $mult) {
            foreach (self::FUELS as $fuel) {
                $rows[] = [(int) $year, $fuel, round(self::BASE[$fuel] * $mult, 3)];
            }
        }

        return [$schema, $rows];
    }

    /**
     * @param  array<string>  $schema
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function mockDataset(int $userId, string $name, array $schema, array $rows): Dataset
    {
        $source = DataSource::create([
            'user_id' => $userId,
            'name' => $name,
            'type' => 'csv',
            'source_kind' => 'upload',
            'materialization' => 'snapshot',
            'original_filename' => Str::slug($name).'.csv',
            'raw_storage_path' => 'data-sources/'.Str::uuid().'.csv',
            'file_size_bytes' => 1024,
            'status' => 'ready',
            'refresh_frequency' => 'daily',
            'last_refreshed_at' => Carbon::now()->subHours(2),
            'next_refresh_at' => Carbon::now()->addHours(22),
        ]);

        $dataset = Dataset::create([
            'data_source_id' => $source->id,
            'user_id' => $userId,
            'name' => $name,
            'row_count' => count($rows),
            'status' => 'ready',
        ]);

        foreach ($schema as $i => $col) {
            DatasetColumn::create([
                'dataset_id' => $dataset->id,
                'name' => $col,
                'type' => $this->columnType($col),
                'nullable' => false,
                'column_order' => $i,
            ]);
        }

        $disk = config('statsio.data_ingestion.datasets_disk', 'local');
        $path = "datasets/{$dataset->id}/v1.parquet";
        Storage::disk($disk)->put($path, json_encode(['__mock__' => true, 'schema' => $schema, 'data' => $rows]));

        DatasetVersion::create([
            'dataset_id' => $dataset->id,
            'version_number' => 1,
            'parquet_storage_path' => $path,
            'file_size_bytes' => 1024,
            'row_count' => count($rows),
            'checksum' => md5($name.count($rows)),
        ]);

        return $dataset->fresh(['columns', 'versions', 'latestVersion', 'dataSource']);
    }

    /** Vignette rayée façon maquette (placeholder « photo »), en data-URI SVG. */
    private function stripePlaceholder(string $label): string
    {
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='1200' height='360'>"
            ."<defs><pattern id='s' width='28' height='28' patternTransform='rotate(45)' patternUnits='userSpaceOnUse'>"
            ."<rect width='28' height='28' fill='#f4f3f8'/><rect width='14' height='28' fill='#eceaf4'/></pattern></defs>"
            ."<rect width='100%' height='100%' fill='url(#s)'/>"
            ."<text x='50%' y='50%' dominant-baseline='middle' text-anchor='middle' "
            ."font-family='JetBrains Mono, monospace' font-size='15' fill='#7a7890'>".htmlspecialchars($label, ENT_QUOTES).'</text>'
            .'</svg>';

        return 'data:image/svg+xml,'.rawurlencode($svg);
    }

    private function columnType(string $col): string
    {
        return match (true) {
            in_array($col, ['prix', 'prix_j7', 'prix_moyen', 'prix_mois_prec', 'ecart_national', 'latitude', 'longitude'], true) => 'float',
            in_array($col, ['annee', 'nb_stations'], true) => 'integer',
            $col === 'date_maj' => 'date',
            default => 'string',
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Structure du document (pages / sections / blocs)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>, 2: array<int, array<string, mixed>>}
     */
    private function buildDocument(string $A, string $B, string $C): array
    {
        $sections = [];
        $blocks = [];

        // Helpers ------------------------------------------------------------
        $section = function (string $id, string $pageId, array $extra = []) use (&$sections) {
            $sections[] = array_merge(['id' => $id, 'pageId' => $pageId, 'layout' => '1-col'], $extra);
        };
        $block = function (string $id, string $type, string $zoneId, array $extra = []) use (&$blocks) {
            $blocks[] = array_merge([
                'id' => $id, 'type' => $type, 'zoneId' => $zoneId,
                'fieldMapping' => [], 'config' => [],
            ], $extra);
        };
        $fuelFilter = [['column' => 'carburant', 'operator' => '=', 'value' => '{{carburant}}']];

        // ══ PAGE PRINCIPALE ══════════════════════════════════════════════════
        $main = 'page-main';

        $section('s-param', $main);
        $block('b-param', 'param', 's-param-0', [
            'datasetId' => $A,
            'fieldMapping' => ['paramColumn' => 'carburant', 'paramName' => 'carburant'],
            'config' => [
                'title' => 'Carburant',
                'paramControl' => 'segmented',
                'paramDefault' => 'Gazole',
                'heroButton' => true,
                'heroButtonLabel' => 'Choisir un carburant',
            ],
        ]);

        // KPI ---------------------------------------------------------------
        $section('s-kpi', $main, ['kicker' => 'KPI · Chiffres clés', 'anchorId' => 'chiffres']);
        $block('b-kpi-moy', 'kpi', 's-kpi-0', [
            'datasetId' => $B, 'filters' => $fuelFilter,
            'fieldMapping' => ['valueColumn' => 'prix_moyen', 'aggregate' => 'avg', 'comparisonColumn' => 'prix_mois_prec'],
            'config' => ['title' => 'Prix moyen national', 'format' => 'number', 'suffix' => ' €/L', 'comparisonFormat' => 'percent', 'description' => 'vs mois dernier'],
        ]);
        $block('b-kpi-min', 'kpi', 's-kpi-0', [
            'datasetId' => $A, 'filters' => $fuelFilter,
            'fieldMapping' => ['valueColumn' => 'prix', 'aggregate' => 'min', 'comparisonColumn' => 'prix_j7'],
            'config' => ['title' => 'Station la moins chère', 'format' => 'number', 'suffix' => ' €/L', 'comparisonFormat' => 'percent', 'description' => 'vs 7 jours'],
        ]);
        $block('b-kpi-max', 'kpi', 's-kpi-0', [
            'datasetId' => $A, 'filters' => $fuelFilter,
            'fieldMapping' => ['valueColumn' => 'prix', 'aggregate' => 'max', 'comparisonColumn' => 'prix_j7'],
            'config' => ['title' => 'Station la plus chère', 'format' => 'number', 'suffix' => ' €/L', 'comparisonFormat' => 'percent', 'description' => 'vs 7 jours'],
        ]);
        $block('b-kpi-ecart', 'kpi', 's-kpi-0', [
            'datasetId' => $A,
            'fieldMapping' => [],
            'config' => [
                'title' => 'Écart max entre stations',
                'valueExpression' => "(MAX(prix@{$A} | carburant=\$carburant) - MIN(prix@{$A} | carburant=\$carburant)) : 2",
                'suffix' => ' €/L',
            ],
        ]);

        // Barres régions --------------------------------------------------
        $section('s-regions', $main, [
            'kicker' => 'Graphique · Barres',
            'title' => "{{carburant}} : l'écart régional se creuse",
            'description' => 'Prix moyen pondéré par le volume distribué. La Corse et la région PACA restent structurellement au-dessus de la moyenne nationale.',
            'anchorId' => 'regions',
        ]);
        $block('b-bar-region', 'bar', 's-regions-0', [
            'datasetId' => $B, 'filters' => $fuelFilter,
            'fieldMapping' => ['xAxis' => 'region', 'yAxis' => 'prix_moyen', 'aggregate' => 'avg'],
            'config' => [
                'rowLimit' => 13,
                'referenceExpression' => "AVG(prix_moyen@{$B} | carburant=\$carburant)",
                'referenceLabel' => 'Moyenne nationale',
                'markRules' => [
                    ['when' => 'above-ref', 'color' => '#dc2626'],
                    ['when' => 'below-ref', 'color' => '#16a34a'],
                ],
            ],
        ]);

        // Ligne historique ---------------------------------------------------
        $section('s-evolution', $main, [
            'kicker' => 'Graphique · Lignes',
            'title' => 'Huit ans de prix à la pompe',
            'description' => 'Moyenne annuelle nationale du {{carburant}}, hors remise. Le pic de 2022 correspond à la crise énergétique.',
            'anchorId' => 'evolution',
        ]);
        $block('b-line-hist', 'line', 's-evolution-0', [
            'datasetId' => $C, 'filters' => $fuelFilter,
            'fieldMapping' => ['xAxis' => 'annee', 'yAxis' => 'prix_moyen', 'aggregate' => 'avg'],
            'config' => [
                'lineFill' => true,
                'referenceExpression' => "AVG(prix_moyen@{$C} | carburant=\$carburant)",
                'referenceLabel' => 'Moyenne 2018-2025',
                'trendExpression' => "(MAX(prix_moyen@{$C} | carburant=\$carburant) - MIN(prix_moyen@{$C} | carburant=\$carburant)) : 2",
            ],
        ]);

        // Recherche (section sombre) --------------------------------------
        $section('s-recherche', $main, [
            'theme' => 'dark',
            'kicker' => 'Bloc de recherche',
            'title' => 'Le prix près de chez vous',
            'description' => 'Tapez une commune ou un code postal : Statsio ouvre la fiche détaillée correspondante, générée automatiquement.',
            'anchorId' => 'recherche',
        ]);
        $block('b-search', 'search', 's-recherche-0', [
            'fieldMapping' => [
                'searchSources' => [['datasetId' => $A, 'columns' => ['commune', 'code_postal']]],
                'targetPageId' => 'page-commune',
                'resultTitleColumn' => 'commune',
                'resultDescColumns' => ['code_postal', 'departement'],
            ],
            'config' => [
                'searchPlaceholder' => 'Lyon, 33000, Rennes…',
                'title' => 'Rechercher une commune',
                'heroButton' => true,
                'heroButtonLabel' => 'Chercher ma commune',
            ],
        ]);

        // Tableau régions --------------------------------------------------
        $section('s-tableau', $main, [
            'kicker' => 'Tableau · Données',
            'title' => 'Prix moyen par région',
            'anchorId' => 'tableau',
        ]);
        $block('b-table-region', 'table', 's-tableau-0', [
            'datasetId' => $B, 'filters' => $fuelFilter,
            'fieldMapping' => [
                'columns' => ['region', 'prix_moyen', 'nb_stations', 'ecart_national'],
                'columnLabels' => ['region' => 'Région', 'prix_moyen' => 'Prix moyen', 'nb_stations' => 'Stations', 'ecart_national' => 'vs national'],
                'columnFormats' => [
                    'prix_moyen' => ['format' => 'number', 'align' => 'right'],
                    'nb_stations' => ['format' => 'number', 'align' => 'right'],
                    'ecart_national' => ['format' => 'number', 'align' => 'right'],
                ],
                'cellRules' => [
                    ['column' => 'ecart_national', 'when' => 'positive', 'color' => '#dc2626'],
                    ['column' => 'ecart_national', 'when' => 'negative', 'color' => '#16a34a'],
                ],
            ],
            'config' => ['sortable' => true, 'sortColumn' => 'prix_moyen', 'sortDirection' => 'desc', 'showPagination' => true, 'pageSize' => 8],
        ]);

        // Camembert + disponibilité (2 colonnes, sans entête) -------------
        $section('s-split', $main, ['layout' => '2-cols']);
        $block('b-pie-enseignes', 'pie', 's-split-0', [
            'datasetId' => $A, 'filters' => $fuelFilter,
            'fieldMapping' => ['label' => 'enseigne', 'value' => 'id_station', 'aggregate' => 'count'],
            'config' => ['title' => 'Qui vend le carburant ?', 'showLegend' => true, 'rowLimit' => 6],
        ]);
        $block('b-bar-dispo', 'bar', 's-split-1', [
            'datasetId' => $A,
            'fieldMapping' => ['xAxis' => 'carburant', 'yAxis' => 'id_station', 'aggregate' => 'count'],
            'config' => ['title' => 'Disponibilité des carburants', 'barStyle' => 'progress', 'orientation' => 'horizontal', 'rowLimit' => 6],
        ]);

        // À retenir (section accent) ------------------------------------
        $section('s-retenir', $main, ['theme' => 'accent', 'kicker' => 'À retenir', 'anchorId' => 'retenir']);
        $block('b-retenir', 'retenir', 's-retenir-0', [
            'config' => [
                'retenirTitle' => 'À retenir',
                'retenirItems' => [
                    "Sur un plein de 50 L, l'écart entre la station la plus chère et la moins chère d'une même agglomération dépasse souvent 7 €.",
                    'La grande distribution (Carrefour, Leclerc, Intermarché, Système U) affiche des prix 3 à 4 % sous la moyenne.',
                    'La Corse et PACA restent les régions les plus chères ; la Bretagne et les Pays de la Loire les moins chères.',
                ],
            ],
        ]);

        // Analyse (titre / paragraphe / citation / image) ----------------
        $section('s-analyse', $main, ['kicker' => 'Titre · Paragraphe · Citation', 'anchorId' => 'analyse']);
        $block('b-h2', 'heading', 's-analyse-0', [
            'config' => ['content' => '<h2>Pourquoi votre voisin paie 12 centimes de moins</h2>', 'headingLevel' => 2],
        ]);
        $block('b-p', 'paragraph', 's-analyse-0', [
            'config' => ['content' => "<p>Trois facteurs expliquent l'essentiel de l'écart : la densité de la grande distribution, qui casse les prix pour attirer en magasin ; le coût logistique d'acheminement depuis le dépôt le plus proche ; et la pression concurrentielle locale, mesurable au nombre de stations dans un rayon de dix kilomètres.</p>"],
        ]);
        $block('b-quote', 'quote', 's-analyse-0', [
            'config' => ['content' => "<p>« Sur un plein de 50 litres, l'écart entre la station la plus chère et la moins chère de la même agglomération dépasse souvent 7 euros. »</p>"],
        ]);
        $block('b-img', 'image', 's-analyse-0', [
            'config' => [
                'imageUrl' => $this->stripePlaceholder('photo — station-service en périphérie'),
                'imageAlt' => 'Station-service en périphérie urbaine',
                'imageCaption' => 'Relevé du 27 août 2026, périphérie de Rennes.',
                'imageWidth' => 'full',
            ],
        ]);

        // Méthodologie + carte de lien ----------------------------------
        $section('s-methodo', $main, ['kicker' => 'Encadré · Méthodologie & source', 'anchorId' => 'methodo']);
        $block('b-fieldgrid', 'field-grid', 's-methodo-0', [
            'config' => [
                'fieldGridColumns' => 3,
                'fieldGridItems' => [
                    ['label' => 'Source', 'value' => 'Flux officiel des prix des carburants (données ouvertes)'],
                    ['label' => 'Fréquence', 'value' => 'Rafraîchi chaque jour, relevés stations toutes les 10 min'],
                    ['label' => 'Périmètre', 'value' => 'France métropolitaine + DROM, 6 carburants routiers'],
                ],
            ],
        ]);
        $block('b-linkcard', 'link-card', 's-methodo-0', [
            'config' => [
                'linkUrl' => '/statsdata',
                'linkTitle' => 'Dossier : la fiscalité des carburants en France',
                'linkDescription' => 'TICPE, TVA, marges de raffinage et comparaisons européennes.',
                'linkDomain' => 'statsio.fr',
            ],
        ]);

        // ══ PAGE COMMUNE (fan-out) ═══════════════════════════════════════════
        $com = 'page-commune';
        $cpFilter = [['column' => 'code_postal', 'operator' => '=', 'value' => '{{code_postal}}']];
        $cpFuelFilter = [
            ['column' => 'code_postal', 'operator' => '=', 'value' => '{{code_postal}}'],
            ['column' => 'carburant', 'operator' => '=', 'value' => '{{carburant}}'],
        ];

        $section('sc-param', $com);
        $block('bc-param', 'param', 'sc-param-0', [
            'datasetId' => $A,
            'fieldMapping' => ['paramColumn' => 'carburant', 'paramName' => 'carburant'],
            'config' => ['title' => 'Carburant', 'paramControl' => 'segmented', 'paramDefault' => 'Gazole'],
        ]);
        $block('bc-search', 'search', 'sc-param-0', [
            'fieldMapping' => [
                'searchSources' => [['datasetId' => $A, 'columns' => ['commune', 'code_postal']]],
                'targetPageId' => 'page-commune',
                'resultTitleColumn' => 'commune',
                'resultDescColumns' => ['code_postal', 'departement'],
            ],
            'config' => ['searchPlaceholder' => 'Changer de commune…', 'title' => 'Autre commune'],
        ]);

        $section('sc-kpi', $com, ['kicker' => 'KPI · {{carburant}} à {{commune}}', 'anchorId' => 'commune-kpi']);
        $block('bc-kpi-moy', 'kpi', 'sc-kpi-0', [
            'datasetId' => $A, 'filters' => $cpFuelFilter,
            'fieldMapping' => ['valueColumn' => 'prix', 'aggregate' => 'avg'],
            'config' => ['title' => 'Prix moyen local', 'format' => 'number', 'suffix' => ' €/L'],
        ]);
        $block('bc-kpi-min', 'kpi', 'sc-kpi-0', [
            'datasetId' => $A, 'filters' => $cpFuelFilter,
            'fieldMapping' => ['valueColumn' => 'prix', 'aggregate' => 'min'],
            'config' => ['title' => 'La moins chère', 'format' => 'number', 'suffix' => ' €/L'],
        ]);
        $block('bc-kpi-max', 'kpi', 'sc-kpi-0', [
            'datasetId' => $A, 'filters' => $cpFuelFilter,
            'fieldMapping' => ['valueColumn' => 'prix', 'aggregate' => 'max'],
            'config' => ['title' => 'La plus chère', 'format' => 'number', 'suffix' => ' €/L'],
        ]);
        $block('bc-kpi-nb', 'kpi', 'sc-kpi-0', [
            'datasetId' => $A, 'filters' => $cpFilter,
            'fieldMapping' => ['valueColumn' => 'id_station', 'aggregate' => 'count'],
            'config' => ['title' => 'Stations relevées'],
        ]);

        $section('sc-table', $com, ['kicker' => 'Tableau · Stations de {{commune}}', 'title' => 'Prix relevés station par station', 'anchorId' => 'commune-stations']);
        $block('bc-table', 'table', 'sc-table-0', [
            'datasetId' => $A, 'filters' => $cpFilter,
            'fieldMapping' => [
                'columns' => ['enseigne', 'adresse', 'carburant', 'prix', 'date_maj'],
                'columnLabels' => ['enseigne' => 'Enseigne', 'adresse' => 'Adresse', 'carburant' => 'Carburant', 'prix' => 'Prix', 'date_maj' => 'Relevé'],
                'columnFormats' => ['prix' => ['format' => 'number', 'align' => 'right']],
            ],
            'config' => ['sortable' => true, 'sortColumn' => 'prix', 'sortDirection' => 'asc', 'showPagination' => true, 'pageSize' => 12],
        ]);

        $section('sc-split', $com, ['layout' => '2-1-cols']);
        $block('bc-rank', 'bar', 'sc-split-0', [
            'datasetId' => $A, 'filters' => $cpFuelFilter,
            'fieldMapping' => ['xAxis' => 'enseigne', 'yAxis' => 'prix', 'aggregate' => 'min'],
            'config' => ['title' => '{{carburant}} : du moins cher au plus cher', 'barStyle' => 'progress', 'orientation' => 'horizontal', 'rowLimit' => 8],
        ]);
        $block('bc-record', 'record', 'sc-split-1', [
            'datasetId' => $A, 'filters' => $cpFuelFilter,
            'fieldMapping' => [
                'columns' => ['enseigne', 'adresse', 'prix', 'date_maj', 'id_station'],
                'recordTitleColumn' => 'enseigne',
                'columnLabels' => ['adresse' => 'Adresse', 'prix' => 'Prix', 'date_maj' => 'Dernier relevé', 'id_station' => 'Identifiant'],
            ],
            'config' => ['title' => 'Station la moins chère', 'sortColumn' => 'prix', 'sortDirection' => 'asc'],
        ]);

        $section('sc-map', $com);
        $block('bc-map', 'map', 'sc-map-0', [
            'config' => ['title' => 'Localisation', 'mapLat' => '{{latitude}}', 'mapLng' => '{{longitude}}', 'mapLabel' => '{{enseigne}} · {{commune}}'],
        ]);

        $section('sc-retenir', $com, ['theme' => 'accent', 'kicker' => 'À retenir · {{commune}}']);
        $block('bc-retenir', 'retenir', 'sc-retenir-0', [
            'config' => [
                'retenirTitle' => 'À retenir',
                'retenirItems' => [
                    "À {{commune}}, l'écart entre stations pour le {{carburant}} se lit directement dans le classement ci-dessus.",
                    'Les prix affichés proviennent du même jeu de données que la page nationale, filtré sur le code postal {{code_postal}}.',
                    'Comparez avec les communes voisines du département {{departement}} ci-dessous.',
                ],
            ],
        ]);

        $section('sc-nearby', $com, ['kicker' => 'Communes voisines']);
        $block('bc-nearby', 'related', 'sc-nearby-0', [
            'datasetId' => $A,
            'filters' => [
                ['column' => 'departement', 'operator' => '=', 'value' => '{{departement}}'],
                ['column' => 'commune', 'operator' => '!=', 'value' => '{{commune}}'],
                ['column' => 'carburant', 'operator' => '=', 'value' => '{{carburant}}'],
            ],
            'fieldMapping' => ['columns' => ['commune', 'prix', 'code_postal']],
            'config' => ['title' => 'Communes voisines', 'rowLimit' => 8, 'distinctColumn' => 'commune', 'sortColumn' => 'prix', 'sortDirection' => 'asc'],
        ]);

        // ── Pages ─────────────────────────────────────────────────────────────
        $pages = [
            [
                'id' => $main,
                'title' => 'Page principale',
                'slug' => 'principale',
                'icon' => '🇫🇷',
                'params' => [
                    ['name' => 'carburant', 'label' => 'Carburant', 'datasetId' => $A, 'column' => 'carburant', 'defaultValue' => 'Gazole'],
                ],
            ],
            [
                'id' => $com,
                'title' => 'Fiche commune',
                'slug' => 'commune',
                'icon' => '📍',
                'params' => [
                    ['name' => 'commune', 'label' => 'Commune', 'datasetId' => $A, 'column' => 'commune', 'slugColumn' => 'code_postal', 'fanOut' => true],
                    ['name' => 'carburant', 'label' => 'Carburant', 'datasetId' => $A, 'column' => 'carburant', 'defaultValue' => 'Gazole'],
                ],
            ],
        ];

        return [$pages, $sections, $blocks];
    }
}
