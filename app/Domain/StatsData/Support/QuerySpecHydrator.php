<?php

namespace App\Domain\StatsData\Support;

final class QuerySpecHydrator
{
    /**
     * Re-inject formula AST payloads that Laravel validation strips from nested arrays.
     *
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public static function hydrate(array $validated, array $raw): array
    {
        // select.*.expr (only for kind=formula)
        $rawSelect = $raw['select'] ?? null;
        if (is_array($rawSelect) && is_array($validated['select'] ?? null)) {
            foreach ($validated['select'] as $i => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $kind = $row['kind'] ?? null;
                if ($kind !== 'formula') {
                    continue;
                }
                $rawExpr = data_get($rawSelect, $i.'.expr');
                if (is_array($rawExpr)) {
                    $validated['select'][$i]['expr'] = $rawExpr;
                }
            }
        }

        // aggregations.*.expr
        $rawAggs = $raw['aggregations'] ?? null;
        if (is_array($rawAggs) && is_array($validated['aggregations'] ?? null)) {
            foreach ($validated['aggregations'] as $i => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $rawExpr = data_get($rawAggs, $i.'.expr');
                if (is_array($rawExpr)) {
                    $validated['aggregations'][$i]['expr'] = $rawExpr;
                }
            }
        }

        return $validated;
    }
}

