<?php

namespace App\Domain\Content\Support;

use App\Domain\DataIngestion\Query\QueryResult;
use App\Models\StudioContent;
use App\Services\DataIngestion\NumericValueParser;

/**
 * Aperçu du mini-graphe d'une carte Statsdata : résout le bloc graphique cible
 * (choix du créateur `card_block_id`, sinon premier graphique dans l'ordre de
 * lecture), traduit son `fieldMapping` en paramètres de requête réutilisables par
 * `DatasetController::parseQueryParams()` / `resolveRows()`, puis met en forme le
 * résultat en séries compactes (≤ 24 points, ≤ 3 séries).
 *
 * Portage réduit de `resolveAggregationParams` / `resolveColumns` /
 * `blockSourceParams` / `resolveBlockFilters` (statsio-front `useBlockData.ts`) et
 * de la logique `chartData` de `LineChartBlock.vue` / `PieChartBlock.vue`.
 */
class CardPreviewSpec
{
    /** @var list<string> */
    public const CHART_TYPES = ['bar', 'line', 'pie'];

    private const MAX_POINTS = 24;

    private const MAX_SERIES = 3;

    /**
     * Bloc graphique cible pour l'aperçu.
     * `$blockIdOverride` : id explicite (`?block_id=` de l'aperçu live des réglages) ;
     * sinon `card_block_id` du contenu ; sinon premier graphique dans l'ordre de lecture.
     *
     * @return array<string, mixed>|null
     */
    public static function resolveBlock(StudioContent $content, ?string $blockIdOverride = null): ?array
    {
        $ordered = StudioContentBlocks::ordered($content);

        $eligible = static function (array $b): bool {
            $type = $b['type'] ?? '';
            if (! in_array($type, self::CHART_TYPES, true)) {
                return false;
            }

            // Camembert « segments » : les parts viennent d'expressions, pas d'une requête de lignes.
            return ! ($type === 'pie' && (($b['config']['pieMode'] ?? null) === 'segments'));
        };

        $wanted = $blockIdOverride ?: $content->card_block_id;
        if ($wanted) {
            foreach ($ordered as $b) {
                if (is_array($b) && ($b['id'] ?? null) === $wanted && $eligible($b)) {
                    return $b;
                }
            }
        }

        foreach ($ordered as $b) {
            if (is_array($b) && $eligible($b)) {
                return $b;
            }
        }

        return null;
    }

    /**
     * Dataset primaire d'un bloc (source primaire, sinon 1re source, sinon `datasetId`).
     *
     * @param  array<string, mixed>  $block
     */
    public static function primaryDatasetId(array $block): ?string
    {
        $sources = is_array($block['sources'] ?? null)
            ? array_values(array_filter($block['sources'], 'is_array'))
            : [];

        if ($sources !== []) {
            $primary = (string) ($block['primarySourceId'] ?? '');
            foreach ($sources as $s) {
                if ((string) ($s['id'] ?? '') === $primary && ! empty($s['datasetId'])) {
                    return (string) $s['datasetId'];
                }
            }
            if (! empty($sources[0]['datasetId'])) {
                return (string) $sources[0]['datasetId'];
            }
        }

        return ! empty($block['datasetId']) ? (string) $block['datasetId'] : null;
    }

    /**
     * Paramètres de requête HTTP (format `GET .../datasets/{dataset}/query`) dérivés
     * du bloc.
     *
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    public static function queryParams(array $block): array
    {
        $type = (string) ($block['type'] ?? '');
        $m = is_array($block['fieldMapping'] ?? null) ? $block['fieldMapping'] : [];
        $config = is_array($block['config'] ?? null) ? $block['config'] : [];

        $aggregatesByCol = [];
        foreach ($m['aggregates'] ?? [] as $a) {
            if (is_array($a) && ! empty($a['column']) && ! empty($a['fn'])) {
                $aggregatesByCol[(string) $a['column']] = (string) $a['fn'];
            }
        }
        $legacyFn = is_string($m['aggregate'] ?? null) && $m['aggregate'] !== '' ? $m['aggregate'] : null;
        $fnFor = static fn (string $col): ?string => $aggregatesByCol[$col] ?? $legacyFn;

        $columns = [];
        $aggregates = [];
        $groupBy = [];

        if ($type === 'pie') {
            $valueCol = (string) ($m['value'] ?? '');
            $labelCol = (string) ($m['label'] ?? '');
            if ($valueCol !== '') {
                $columns[] = $valueCol;
                if ($fnFor($valueCol)) {
                    $aggregates[] = ['column' => $valueCol, 'fn' => $fnFor($valueCol)];
                    if ($labelCol !== '') {
                        $groupBy[] = $labelCol;
                    }
                }
            }
            if ($labelCol !== '') {
                $columns[] = $labelCol;
            }
        } else { // bar | line
            $xAxis = (string) ($m['xAxis'] ?? '');
            $series = (string) ($m['series'] ?? '');
            $yCols = ! empty($m['yAxes']) && is_array($m['yAxes'])
                ? array_values(array_filter(array_map('strval', $m['yAxes'])))
                : (! empty($m['yAxis']) ? [(string) $m['yAxis']] : []);

            foreach ($yCols as $c) {
                $columns[] = $c;
                if ($fnFor($c)) {
                    $aggregates[] = ['column' => $c, 'fn' => $fnFor($c)];
                }
            }
            if ($xAxis !== '') {
                $columns[] = $xAxis;
            }
            if ($series !== '') {
                $columns[] = $series;
            }
            if ($aggregates !== []) {
                foreach ([$xAxis, $series] as $g) {
                    if ($g !== '') {
                        $groupBy[] = $g;
                    }
                }
            }
        }

        // Filtres : `{column, operator, value}` complets ; on écarte tout jeton `{{…}}` non résolu
        // (pas de paramètre de page dans le contexte carte).
        $filters = [];
        foreach ($block['filters'] ?? [] as $f) {
            if (! is_array($f)) {
                continue;
            }
            $col = (string) ($f['column'] ?? '');
            $val = (string) ($f['value'] ?? '');
            if ($col === '' || $val === '' || preg_match('/\{\{.+\}\}/', $val)) {
                continue;
            }
            $filters[] = [
                'column' => $col,
                'operator' => (string) ($f['operator'] ?? '='),
                'value' => $val,
            ];
        }

        $groupLimit = max(1, min((int) ($config['rowLimit'] ?? 300), 300));
        $limit = ($m['series'] ?? '') !== '' ? min($groupLimit * 20, 1500) : $groupLimit;

        $params = [
            'columns' => array_values(array_unique(array_filter($columns))),
            'limit' => $limit,
        ];
        if ($aggregates !== []) {
            $params['aggregates'] = $aggregates;
            $params['group_by'] = array_values(array_unique($groupBy));
        }
        if ($filters !== []) {
            $params['filters'] = $filters;
        }
        if (! empty($config['sortColumn'])) {
            $params['sort_column'] = (string) $config['sortColumn'];
            $params['sort_direction'] = in_array($config['sortDirection'] ?? null, ['asc', 'desc'], true)
                ? $config['sortDirection']
                : 'asc';
        }

        // Multi-sources : n'émettre `sources[]` que s'il y a > 1 source (comme le front).
        $sources = is_array($block['sources'] ?? null)
            ? array_values(array_filter($block['sources'], 'is_array'))
            : [];
        if (count($sources) > 1) {
            $primary = (string) ($block['primarySourceId'] ?? '');
            $params['sources'] = [];
            foreach ($sources as $i => $s) {
                $entry = [
                    'id' => (string) ($s['id'] ?? ''),
                    'dataset_id' => (string) ($s['datasetId'] ?? ''),
                ];
                if ($entry['id'] !== '' && $entry['id'] === $primary) {
                    $entry['primary'] = '1';
                }
                $params['sources'][$i] = $entry;
            }
            $joins = is_array($block['joins'] ?? null)
                ? array_values(array_filter($block['joins'], 'is_array'))
                : [];
            foreach ($joins as $i => $j) {
                $params['joins'][$i] = [
                    'left_source' => (string) ($j['leftSourceId'] ?? ''),
                    'left_column' => (string) ($j['leftColumn'] ?? ''),
                    'right_source' => (string) ($j['rightSourceId'] ?? ''),
                    'right_column' => (string) ($j['rightColumn'] ?? ''),
                    'type' => in_array($j['type'] ?? '', ['inner', 'left'], true) ? (string) $j['type'] : 'left',
                ];
            }
        }

        return $params;
    }

    /**
     * Met en forme le résultat d'une requête de bloc en aperçu compact.
     *
     * @param  array<string, mixed>  $block
     * @return array{block_id: string, kind: string, title: string, labels: list<string>, series: list<array{name: string, values: list<float>}>, unit?: string, orientation?: string, empty: bool}
     */
    public static function shape(array $block, QueryResult $result): array
    {
        $type = (string) ($block['type'] ?? '');
        $kind = $type === 'pie' ? 'pie' : ($type === 'bar' ? 'bar' : 'line');
        $m = is_array($block['fieldMapping'] ?? null) ? $block['fieldMapping'] : [];
        $config = is_array($block['config'] ?? null) ? $block['config'] : [];
        $blockId = (string) ($block['id'] ?? '');
        $title = trim((string) ($config['title'] ?? ''));

        $empty = [
            'block_id' => $blockId,
            'kind' => $kind,
            'title' => $title,
            'labels' => [],
            'series' => [],
            'empty' => true,
        ];

        $rows = $result->rows;
        if ($rows === []) {
            return $empty;
        }

        $map = $result->columnMap;
        $key = static fn (string $ref): string => $map[$ref] ?? $ref;
        $num = static fn ($v): float => (float) (NumericValueParser::parse($v) ?? 0.0);
        $labelFmt = static fn ($v): string => $v === null
            ? ''
            : (is_bool($v) ? ($v ? 'true' : 'false') : (string) $v);

        if ($kind === 'pie') {
            $labelKey = $key((string) ($m['label'] ?? ''));
            $valueKey = $key((string) ($m['value'] ?? ''));
            $pairs = [];
            foreach ($rows as $r) {
                $label = $labelFmt($r[$labelKey] ?? null);
                if ($label === '') {
                    continue;
                }
                $pairs[] = ['label' => $label, 'value' => abs($num($r[$valueKey] ?? null))];
            }
            usort($pairs, static fn ($a, $b) => $b['value'] <=> $a['value']);
            $top = array_slice($pairs, 0, 5);
            $rest = array_slice($pairs, 5);
            if ($rest !== []) {
                $top[] = ['label' => 'Autres', 'value' => array_sum(array_column($rest, 'value'))];
            }
            $values = array_map(static fn ($v) => round((float) $v, 4), array_column($top, 'value'));
            if ($top === [] || array_sum($values) <= 0.0) {
                return $empty;
            }

            $out = [
                'block_id' => $blockId,
                'kind' => 'pie',
                'title' => $title,
                'labels' => array_values(array_column($top, 'label')),
                'series' => [['name' => self::blockLabel($block), 'values' => array_values($values)]],
                'empty' => false,
            ];
            if ($unit = self::unit($config)) {
                $out['unit'] = $unit;
            }

            return $out;
        }

        // bar | line
        $xKey = $key((string) ($m['xAxis'] ?? ''));
        $seriesCol = (string) ($m['series'] ?? '');
        $yCols = ! empty($m['yAxes']) && is_array($m['yAxes'])
            ? array_values(array_filter(array_map('strval', $m['yAxes'])))
            : (! empty($m['yAxis']) ? [(string) $m['yAxis']] : []);
        if ($yCols === []) {
            return $empty;
        }

        $labels = [];
        $series = [];

        if ($seriesCol !== '') {
            // Format long : pivot sur la colonne de série.
            $sKey = $key($seriesCol);
            $yKey = $key($yCols[0]);
            $seriesNames = [];
            $valueByKey = [];
            foreach ($rows as $r) {
                $lab = $labelFmt($r[$xKey] ?? null);
                $sname = $labelFmt($r[$sKey] ?? null);
                if (! in_array($lab, $labels, true)) {
                    $labels[] = $lab;
                }
                if (! in_array($sname, $seriesNames, true)) {
                    $seriesNames[] = $sname;
                }
                $valueByKey[$lab."\0".$sname] ??= $num($r[$yKey] ?? null);
            }
            foreach (array_slice($seriesNames, 0, self::MAX_SERIES) as $sname) {
                $series[] = [
                    'name' => $sname,
                    'values' => array_map(
                        static fn ($lab) => $valueByKey[$lab."\0".$sname] ?? 0.0,
                        $labels,
                    ),
                ];
            }
        } elseif (count($yCols) >= 2) {
            // Format large : une série par colonne Y.
            $columnLabels = is_array($m['columnLabels'] ?? null) ? $m['columnLabels'] : [];
            foreach ($rows as $r) {
                $labels[] = $labelFmt($r[$xKey] ?? null);
            }
            foreach (array_slice($yCols, 0, self::MAX_SERIES) as $col) {
                $ck = $key($col);
                $series[] = [
                    'name' => (string) ($columnLabels[$col] ?? $col),
                    'values' => array_map(static fn ($r) => $num($r[$ck] ?? null), $rows),
                ];
            }
        } else {
            // Série Y unique.
            $yKey = $key($yCols[0]);
            $values = [];
            foreach ($rows as $r) {
                $labels[] = $labelFmt($r[$xKey] ?? null);
                $values[] = $num($r[$yKey] ?? null);
            }
            $series[] = ['name' => self::blockLabel($block), 'values' => $values];
        }

        // Sous-échantillonnage à ≤ 24 points, extrémités préservées, mêmes indices pour toutes les séries.
        $keep = self::keepIndices(count($labels), self::MAX_POINTS);
        $labels = array_map(static fn ($i) => $labels[$i] ?? '', $keep);
        foreach ($series as &$s) {
            $s['values'] = array_values(array_map(
                static fn ($i) => round((float) ($s['values'][$i] ?? 0.0), 4),
                $keep,
            ));
        }
        unset($s);

        $allValues = array_merge(...array_map(static fn ($s) => $s['values'], $series));
        if (count($labels) < 2 || count(array_filter($allValues, static fn ($v) => $v != 0.0)) === 0) {
            return $empty;
        }

        $out = [
            'block_id' => $blockId,
            'kind' => $kind,
            'title' => $title,
            'labels' => array_values($labels),
            'series' => $series,
            'empty' => false,
        ];
        if ($unit = self::unit($config)) {
            $out['unit'] = $unit;
        }
        if ($kind === 'bar') {
            $out['orientation'] = ($config['orientation'] ?? 'vertical') === 'horizontal' ? 'horizontal' : 'vertical';
        }

        return $out;
    }

    /**
     * Indices à conserver pour ramener `$count` points à `$max`, extrémités incluses.
     *
     * @return list<int>
     */
    private static function keepIndices(int $count, int $max): array
    {
        if ($count <= $max) {
            return range(0, max(0, $count - 1));
        }
        $idx = [];
        for ($i = 0; $i < $max; $i++) {
            $idx[] = (int) round($i * ($count - 1) / ($max - 1));
        }

        return array_values(array_unique($idx));
    }

    /** @param  array<string, mixed>  $block */
    private static function blockLabel(array $block): string
    {
        $title = trim((string) ($block['config']['title'] ?? ''));
        if ($title !== '') {
            return $title;
        }

        return ($block['type'] ?? '') === 'pie' ? 'Répartition' : 'Valeur';
    }

    /** @param  array<string, mixed>  $config */
    private static function unit(array $config): ?string
    {
        // Suffixe verbatim (le front l'utilise tel quel, ex. « €/L », espace comprise).
        $suffix = (string) ($config['suffix'] ?? '');
        if (trim($suffix) !== '') {
            return $suffix;
        }

        return match ($config['format'] ?? null) {
            'percent' => '%',
            'currency' => '€',
            default => null,
        };
    }
}
