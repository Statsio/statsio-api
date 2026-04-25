<?php

namespace App\Domain\StatsData\Services;

use App\Models\StatsData\StatsDataSource;

class StatsDataNormalizationMappingSuggestionService
{
    public function __construct(
        private StatsDataSourceParsedRootService $parsedRootService
    ) {}

    /**
     * @return array{
     *   suggestedMapping: array<string, mixed>,
     *   detected: array{
     *     rowPath: string|null,
     *     rowCountSampled: int,
     *     fieldCount: int
     *   },
     *   fields: list<array{
     *     path: string,
     *     kind: 'number'|'string'|'boolean'|'null'|'mixed',
     *     examples: list<string>
     *   }>,
     *   rowPathOptions: list<string>
     * }
     */
    public function suggest(StatsDataSource $source): array
    {
        $parsedRoot = $this->parsedRootService->build($source);

        [$rowPath, $rows, $rowPathOptions] = $this->detectRows($parsedRoot);

        $sample = array_slice($rows, 0, 50);
        $fieldMeta = $this->inferFields($sample);

        $fieldPaths = array_keys($fieldMeta);
        sort($fieldPaths);

        $suggested = $this->buildSuggestedMapping($rowPath, $fieldMeta);

        $fields = [];
        foreach ($fieldPaths as $p) {
            $fields[] = [
                'path' => $p,
                'kind' => $fieldMeta[$p]['kind'],
                'examples' => array_slice($fieldMeta[$p]['examples'], 0, 5),
            ];
        }

        return [
            'suggestedMapping' => $suggested,
            'detected' => [
                'rowPath' => $rowPath,
                'rowCountSampled' => count($sample),
                'fieldCount' => count($fields),
            ],
            'fields' => $fields,
            'rowPathOptions' => $rowPathOptions,
        ];
    }

    /**
     * @param  array<string, mixed>  $parsedRoot
     * @return array{0: string|null, 1: list<array<string, mixed>>, 2: list<string>}
     */
    private function detectRows(array $parsedRoot): array
    {
        // Case 1: root is already a list of rows
        if (array_is_list($parsedRoot)) {
            $rows = array_values(array_filter($parsedRoot, fn ($x) => is_array($x)));
            return [null, $rows, []];
        }

        // Case 2: common containers
        $options = [];
        foreach (['data', 'results', 'items', 'records'] as $k) {
            if (array_key_exists($k, $parsedRoot) && is_array($parsedRoot[$k]) && array_is_list($parsedRoot[$k])) {
                $options[] = $k;
            }
        }

        // Case 3: any first-level list
        foreach ($parsedRoot as $k => $v) {
            if (! is_string($k) || $k === '') {
                continue;
            }
            if (is_array($v) && array_is_list($v)) {
                $options[] = $k;
            }
        }

        $options = array_values(array_unique($options));

        foreach ($options as $path) {
            $v = data_get($parsedRoot, $path);
            if (is_array($v) && array_is_list($v)) {
                $rows = array_values(array_filter($v, fn ($x) => is_array($x)));
                if (count($rows) > 0) {
                    return [$path, $rows, $options];
                }
            }
        }

        // Fallback: treat root as a single row
        return [null, [$parsedRoot], $options];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, array{kind: 'number'|'string'|'boolean'|'null'|'mixed', examples: list<string>}>
     */
    private function inferFields(array $rows): array
    {
        $meta = [];
        foreach ($rows as $row) {
            $flat = $this->flattenRow($row, 3);
            foreach ($flat as $path => $value) {
                $kind = $this->valueKind($value);
                if (! isset($meta[$path])) {
                    $meta[$path] = ['kinds' => [], 'examples' => []];
                }
                $meta[$path]['kinds'][$kind] = true;
                $ex = $this->stringifyExample($value);
                if ($ex !== null && count($meta[$path]['examples']) < 8 && ! in_array($ex, $meta[$path]['examples'], true)) {
                    $meta[$path]['examples'][] = $ex;
                }
            }
        }

        $out = [];
        foreach ($meta as $path => $m) {
            $kinds = array_keys($m['kinds']);
            $kind = count($kinds) === 1 ? $kinds[0] : 'mixed';
            /** @var 'number'|'string'|'boolean'|'null'|'mixed' $kind */
            $out[$path] = ['kind' => $kind, 'examples' => $m['examples']];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function flattenRow(array $row, int $maxDepth, string $prefix = ''): array
    {
        if ($maxDepth <= 0) {
            return [];
        }
        $out = [];
        foreach ($row as $k => $v) {
            if (! is_string($k) || $k === '') {
                continue;
            }
            $path = $prefix === '' ? $k : $prefix.'.'.$k;
            if (is_array($v)) {
                // Only flatten objects; arrays-of-scalars are not safe to map via data_get paths.
                // Exception: list with a single scalar → we can map it via `${path}.0`.
                if (array_is_list($v)) {
                    if (count($v) === 1 && ! is_array($v[0])) {
                        $out[$path.'.0'] = $v[0];
                    }
                    continue;
                }
                if (! array_is_list($v)) {
                    $out += $this->flattenRow($v, $maxDepth - 1, $path);
                }
                continue;
            }
            $out[$path] = $v;
        }

        return $out;
    }

    private function valueKind(mixed $v): string
    {
        if ($v === null) return 'null';
        if (is_bool($v)) return 'boolean';
        if (is_int($v) || is_float($v)) return 'number';
        if (is_string($v)) return 'string';
        return 'mixed';
    }

    private function stringifyExample(mixed $v): ?string
    {
        if ($v === null) return 'null';
        if (is_bool($v)) return $v ? 'true' : 'false';
        if (is_int($v) || is_float($v)) return (string) $v;
        if (is_string($v)) {
            $t = trim($v);
            return $t === '' ? null : mb_substr($t, 0, 80);
        }
        return null;
    }

    /**
     * @param  array<string, array{kind: string, examples: list<string>}>  $fieldMeta
     * @return array<string, mixed>
     */
    private function buildSuggestedMapping(?string $rowPath, array $fieldMeta): array
    {
        $paths = array_keys($fieldMeta);

        $scoreKey = function (string $path, array $examples): int {
            $p = strtolower($path);
            $score = 0;
            foreach (['id', 'code', 'ref', 'name', 'date', 'time', 'period', 'year', 'month'] as $needle) {
                if (str_contains($p, $needle)) $score += 2;
            }
            if (str_ends_with($p, 'code') || str_contains($p, '_code') || str_contains($p, 'code_')) {
                $score += 3;
            }
            foreach ($examples as $ex) {
                if (preg_match('/^\d{4}-\d{2}-\d{2}/', $ex)) $score += 3;
                if (preg_match('/^\d{4}\-\d{2}$/', $ex)) $score += 2;
            }
            return $score;
        };

        $keyCandidates = [];
        $valueCandidates = [];

        foreach ($paths as $p) {
            $m = $fieldMeta[$p];
            $kind = $m['kind'];
            $pl = strtolower($p);
            // Always consider explicit `value` fields as candidates, even if sometimes null.
            if ($pl === 'value' || str_ends_with($pl, '.value')) {
                $valueCandidates[] = $p;
                continue;
            }
            if ($kind === 'number') {
                $valueCandidates[] = $p;
                continue;
            }
            $s = $scoreKey($p, $m['examples']);
            if ($s > 0) {
                $keyCandidates[] = ['path' => $p, 'score' => $s];
            }
        }

        usort($keyCandidates, fn ($a, $b) => $b['score'] <=> $a['score']);
        $keyPaths = array_slice(array_map(fn ($x) => $x['path'], $keyCandidates), 0, 4);

        // Build stable, readable names:
        // - if path ends with ".0" (mono-value arrays) → name is base key, from is full path
        // - otherwise, name is the last segment (or full path if single segment), from omitted when identical
        $toField = function (string $path): array {
            $p = trim($path);
            if ($p === '') return ['name' => ''];
            if (str_ends_with($p, '.0')) {
                $base = substr($p, 0, -2);
                $parts = explode('.', $base);
                $name = end($parts) ?: $base;
                return ['name' => $name, 'from' => $p];
            }
            $parts = explode('.', $p);
            $name = end($parts) ?: $p;
            return $p === $name ? ['name' => $name] : ['name' => $name, 'from' => $p];
        };

        // Default behavior requested: include *all* detected fields in valueFields.
        // Key fields are "recommended join keys", but we keep them out of valueFields to avoid duplicates.
        $keySet = array_fill_keys($keyPaths, true);
        $valuePaths = array_values(array_filter($paths, fn (string $p) => ! isset($keySet[$p])));

        $mapping = [
            'keyFields' => array_values(array_filter(array_map($toField, $keyPaths), fn ($f) => is_array($f) && ($f['name'] ?? '') !== '')),
            'valueFields' => array_values(array_filter(array_map($toField, $valuePaths), fn ($f) => is_array($f) && ($f['name'] ?? '') !== '')),
        ];
        if ($rowPath !== null && $rowPath !== '') {
            $mapping['rowPath'] = $rowPath;
        }

        return $mapping;
    }
}

