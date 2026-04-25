<?php

namespace App\Domain\StatsData\Services;

use App\Domain\StatsData\Support\StableKey;
use InvalidArgumentException;

class StatsDataAggregationEngine
{
    public function __construct(private StatsDataFormulaEngine $formula) {}

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $groupBy
     * @param  list<array<string, mixed>>  $aggregations
     * @return list<array<string, mixed>>
     */
    public function groupAndAggregate(array $rows, array $groupBy, array $aggregations): array
    {
        /** @var array<string, array{keys: array<string,mixed>, rows: list<array<string,mixed>>}> $groups */
        $groups = [];

        foreach ($rows as $r) {
            $keyParts = [];
            $keyAssoc = [];
            foreach ($groupBy as $k) {
                $v = $r[$k] ?? null;
                $keyAssoc[$k] = $v;
                $keyParts[] = StableKey::part($v);
            }
            $gk = implode('|', $keyParts);
            if (! isset($groups[$gk])) {
                $groups[$gk] = ['keys' => $keyAssoc, 'rows' => []];
            }
            $groups[$gk]['rows'][] = $r;
        }

        $out = [];
        foreach ($groups as $g) {
            $base = $g['keys'];
            foreach ($aggregations as $agg) {
                $label = $agg['label'] ?? null;
                $fn = $agg['fn'] ?? null;
                if (! is_string($label) || $label === '' || ! is_string($fn) || $fn === '') {
                    continue;
                }
                $base[$label] = $this->computeAgg($fn, $agg['expr'] ?? null, $g['rows']);
            }
            $out[] = $base;
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function computeAgg(string $fn, mixed $expr, array $rows): mixed
    {
        if ($fn === 'count') {
            return count($rows);
        }

        $vals = [];
        foreach ($rows as $r) {
            if ($expr === null) {
                continue;
            }
            if (! is_array($expr)) {
                throw new InvalidArgumentException(__('stats_data.query_invalid_formula'));
            }
            $v = $this->formula->eval($expr, $r, []);
            if (is_int($v) || is_float($v)) {
                $vals[] = (float) $v;
            } elseif (is_string($v)) {
                $n = is_numeric(str_replace(',', '.', trim($v))) ? (float) str_replace(',', '.', trim($v)) : null;
                if ($n !== null) {
                    $vals[] = $n;
                }
            }
        }

        if ($vals === []) {
            return null;
        }

        return match ($fn) {
            'sum' => array_sum($vals),
            'avg' => array_sum($vals) / count($vals),
            'min' => min($vals),
            'max' => max($vals),
            default => throw new InvalidArgumentException(__('stats_data.query_invalid_aggregation')),
        };
    }

    
}

