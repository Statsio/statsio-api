<?php

namespace App\Domain\StatsData\Services;

use App\Domain\StatsData\Enums\StatsDataSnapshotStatus;
use App\Domain\StatsData\Support\TupleAccessor;
use App\Domain\StatsData\Support\TupleJoiner;
use App\Models\StatsData\StatsDataDocument;
use App\Models\StatsData\StatsDataNormalizedSnapshot;
use App\Models\StatsData\StatsDataSource;
use InvalidArgumentException;

class StatsDataQueryEngineV2
{
    public function __construct(
        private StatsDataFormulaEngine $formula,
        private StatsDataAggregationEngine $aggregation
    ) {}

    /**
     * @param  array<string, mixed>  $spec
     * @return list<array<string, mixed>>
     */
    public function execute(StatsDataDocument $document, array $spec): array
    {
        $max = (int) config('stats_data.max_query_rows', 10_000);
        $limit = isset($spec['limit']) ? min((int) $spec['limit'], $max) : $max;
        $offset = isset($spec['offset']) ? max(0, (int) $spec['offset']) : 0;

        $sources = $spec['sources'] ?? [];
        if (! is_array($sources) || $sources === []) {
            throw new InvalidArgumentException(__('stats_data.query_sources_required'));
        }

        /** @var array<string, string> $aliasToSourceId */
        $aliasToSourceId = [];
        $sourceIds = [];
        foreach ($sources as $row) {
            if (! is_array($row)) {
                continue;
            }
            $alias = $row['alias'] ?? null;
            $sourceId = $row['sourceId'] ?? null;
            if (! is_string($alias) || $alias === '' || ! is_string($sourceId) || $sourceId === '') {
                throw new InvalidArgumentException(__('stats_data.query_invalid_source_entry'));
            }
            $aliasToSourceId[$alias] = $sourceId;
            $sourceIds[] = $sourceId;
        }
        if ($aliasToSourceId === []) {
            throw new InvalidArgumentException(__('stats_data.query_sources_required'));
        }

        $this->assertSourcesBelongToDocument($document->id, $sourceIds);

        $select = $spec['select'] ?? [];
        if (! is_array($select) || $select === []) {
            throw new InvalidArgumentException(__('stats_data.query_columns_required'));
        }

        $rowsByAlias = $this->loadFlattenedRowsByAlias($aliasToSourceId);
        $tuples = $this->joinIfNeeded($spec, $rowsByAlias);

        // Appliquer les conditions WHERE sur les tuples (AVANT le SELECT)
        $where = $spec['where'] ?? null;
        if (is_array($where) && $where !== []) {
            \Log::info('WHERE filter on tuples', ['where' => $where, 'tuples_before' => count($tuples)]);
            $tuples = $this->filterTuplesByWhere($tuples, $where);
            \Log::info('After WHERE filter', ['tuples_after' => count($tuples)]);
        }

        $rows = $this->selectRows($tuples, $select);

        $searchQ = data_get($spec, 'search.q');
        if (is_string($searchQ)) {
            $q = trim($searchQ);
            if ($q !== '') {
                $rows = $this->filterRowsBySearch($rows, $q);
            }
        }

        $groupBy = $spec['groupBy'] ?? null;
        $aggregations = $spec['aggregations'] ?? null;
        if (is_array($groupBy) && $groupBy !== [] && is_array($aggregations) && $aggregations !== []) {
            /** @var list<string> $gb */
            $gb = array_values(array_filter($groupBy, fn ($x) => is_string($x) && trim($x) !== ''));
            /** @var list<array<string,mixed>> $aggs */
            $aggs = array_values(array_filter($aggregations, fn ($x) => is_array($x)));
            $rows = $this->aggregation->groupAndAggregate($rows, $gb, $aggs);
        }

        $orderBy = $spec['orderBy'] ?? null;
        if (is_array($orderBy) && $orderBy !== []) {
            $rows = $this->orderRows($rows, $orderBy);
        }

        if ($offset > 0 || $limit < count($rows)) {
            $rows = array_slice($rows, $offset, $limit);
        }

        return array_values($rows);
    }

    /**
     * @param  list<string>  $sourceIds
     */
    private function assertSourcesBelongToDocument(string $documentId, array $sourceIds): void
    {
        $sourceIds = array_values(array_unique($sourceIds));
        $count = StatsDataSource::query()
            ->where('stats_data_document_id', $documentId)
            ->whereIn('id', $sourceIds)
            ->count();
        if ($count !== count($sourceIds)) {
            throw new InvalidArgumentException(__('stats_data.query_source_not_in_document'));
        }
    }

    /**
     * @param  array<string, string>  $aliasToSourceId
     * @return array<string, list<array<string, mixed>>>
     */
    private function loadFlattenedRowsByAlias(array $aliasToSourceId): array
    {
        $out = [];
        foreach ($aliasToSourceId as $alias => $sourceId) {
            $snap = StatsDataNormalizedSnapshot::query()
                ->where('stats_data_source_id', $sourceId)
                ->where('status', StatsDataSnapshotStatus::Ok)
                ->orderByDesc('refreshed_at')
                ->first();

            if ($snap === null || ! is_array($snap->rows)) {
                throw new InvalidArgumentException(__('stats_data.query_no_snapshot', ['alias' => $alias]));
            }

            $flattened = [];
            foreach ($snap->rows as $r) {
                if (! is_array($r)) {
                    continue;
                }
                $keys = is_array($r['keys'] ?? null) ? $r['keys'] : [];
                $values = is_array($r['values'] ?? null) ? $r['values'] : [];
                $flattened[] = array_merge($keys, $values);
            }
            $out[$alias] = $flattened;
        }

        return $out;
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $rowsByAlias
     * @return list<array<string, array<string, mixed>>>
     */
    private function joinIfNeeded(array $spec, array $rowsByAlias): array
    {
        if (count($rowsByAlias) === 1) {
            $alias = array_key_first($rowsByAlias);
            return array_map(fn (array $flat) => [$alias => $flat], $rowsByAlias[$alias]);
        }

        $join = $spec['join'] ?? [];
        $on = $join['on'] ?? null;
        if (! is_array($on) || $on === []) {
            throw new InvalidArgumentException(__('stats_data.query_join_on_required'));
        }
        /** @var list<string> $onList */
        $onList = array_values(array_filter($on, fn ($k) => is_string($k) && $k !== ''));
        if ($onList === []) {
            throw new InvalidArgumentException(__('stats_data.query_join_on_required'));
        }
        $type = $join['type'] ?? 'inner';
        if ($type !== 'inner' && $type !== 'left') {
            throw new InvalidArgumentException(__('stats_data.query_join_type_invalid'));
        }

        return TupleJoiner::hashJoin($rowsByAlias, $onList, $type === 'left');
    }

    /**
     * @param  list<array<string, array<string, mixed>>>  $tuples
     * @param  list<mixed>  $select
     * @return list<array<string, mixed>>
     */
    private function selectRows(array $tuples, array $select): array
    {
        $out = [];
        foreach ($tuples as $tuple) {
            $row = [];
            foreach ($select as $sel) {
                if (! is_array($sel)) {
                    continue;
                }
                $kind = $sel['kind'] ?? null;
                $label = $sel['label'] ?? null;
                if (! is_string($kind) || ! is_string($label) || $label === '') {
                    continue;
                }

                if ($kind === 'from') {
                    $from = $sel['from'] ?? null;
                    $row[$label] = is_string($from) ? $this->resolveFrom($tuple, $from) : null;
                    continue;
                }
                if ($kind === 'formula') {
                    $expr = $sel['expr'] ?? null;
                    if (! is_array($expr)) {
                        $row[$label] = null;
                        continue;
                    }
                    $row[$label] = $this->formula->eval($expr, $row, $tuple);
                    continue;
                }
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param  array<string, array<string, mixed>>  $tuple
     */
    private function resolveFrom(array $tuple, string $from): mixed
    {
        return TupleAccessor::get($tuple, $from);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function filterRowsBySearch(array $rows, string $q): array
    {
        $needle = mb_strtolower($q);
        $out = [];
        foreach ($rows as $r) {
            $hit = false;
            foreach ($r as $v) {
                if ($v === null) {
                    continue;
                }
                $s = is_string($v) ? $v : (is_scalar($v) ? (string) $v : null);
                if ($s === null) {
                    continue;
                }
                if (str_contains(mb_strtolower($s), $needle)) {
                    $hit = true;
                    break;
                }
            }
            if ($hit) {
                $out[] = $r;
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<mixed>  $orderBy
     * @return list<array<string, mixed>>
     */
    private function orderRows(array $rows, array $orderBy): array
    {
        $rules = [];
        foreach ($orderBy as $r) {
            if (! is_array($r)) {
                continue;
            }
            $by = $r['by'] ?? null;
            $dir = $r['dir'] ?? 'asc';
            if (! is_string($by) || $by === '') {
                continue;
            }
            $rules[] = ['by' => $by, 'dir' => $dir === 'desc' ? 'desc' : 'asc'];
        }
        if ($rules === []) {
            return $rows;
        }

        usort($rows, function ($a, $b) use ($rules) {
            foreach ($rules as $r) {
                $by = $r['by'];
                $dir = $r['dir'];
                $va = $a[$by] ?? null;
                $vb = $b[$by] ?? null;
                if ($va == $vb) {
                    continue;
                }
                $cmp = ($va < $vb) ? -1 : 1;
                return $dir === 'desc' ? -$cmp : $cmp;
            }

            return 0;
        });

        return $rows;
    }

    /**
     * @param  list<array<string, array<string, mixed>>>  $tuples
     * @param  list<mixed>  $where
     * @return list<array<string, array<string, mixed>>>
     */
    private function filterTuplesByWhere(array $tuples, array $where): array
    {
        $out = [];
        foreach ($tuples as $tuple) {
            $match = true;
            foreach ($where as $condition) {
                if (!is_array($condition)) {
                    continue;
                }

                $kind = $condition['kind'] ?? null;
                $left = $condition['left'] ?? null;
                $right = $condition['right'] ?? null;

                if (!is_array($left) || !is_array($right)) {
                    continue;
                }

                $leftKind = $left['kind'] ?? null;
                $column = $left['column'] ?? null;
                if ($leftKind !== 'column' || !is_string($column)) {
                    continue;
                }

                $rightKind = $right['kind'] ?? null;
                $value = $right['value'] ?? null;
                if ($rightKind !== 'literal') {
                    continue;
                }

                // Utiliser TupleAccessor pour résoudre "alias.field"
                $columnValue = TupleAccessor::get($tuple, $column);

                // Comparer selon l'opérateur
                $conditionMatch = match($kind) {
                    'eq' => $columnValue == $value,
                    'ne' => $columnValue != $value,
                    'gt' => $columnValue > $value,
                    'gte' => $columnValue >= $value,
                    'lt' => $columnValue < $value,
                    'lte' => $columnValue <= $value,
                    default => true,
                };

                if (!$conditionMatch) {
                    $match = false;
                    break;
                }
            }

            if ($match) {
                $out[] = $tuple;
            }
        }

        return $out;
    }
}
