<?php

namespace App\Domain\StatsData\Services;

use App\Domain\StatsData\Enums\StatsDataSnapshotStatus;
use App\Domain\StatsData\Support\TupleJoiner;
use App\Domain\StatsData\Support\TupleAccessor;
use App\Models\StatsData\StatsDataDocument;
use App\Models\StatsData\StatsDataNormalizedSnapshot;
use App\Models\StatsData\StatsDataSource;
use InvalidArgumentException;

class StatsDataQueryEngine
{
    public function __construct(private StatsDataQueryEngineV2 $v2) {}

    /**
     * @param  array<string, mixed>  $spec
     * @return list<array<string, mixed>>
     */
    public function execute(StatsDataDocument $document, array $spec): array
    {
        $specVersion = $spec['specVersion'] ?? null;
        if ($specVersion === 2 || $specVersion === '2') {
            return $this->v2->execute($document, $spec);
        }

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

        $columns = $spec['columns'] ?? [];
        if (! is_array($columns) || $columns === []) {
            throw new InvalidArgumentException(__('stats_data.query_columns_required'));
        }

        $rowsByAlias = $this->loadFlattenedRowsByAlias($aliasToSourceId);

        if (count($rowsByAlias) === 1) {
            $alias = array_key_first($rowsByAlias);
            $tuples = array_map(fn (array $flat) => [$alias => $flat], $rowsByAlias[$alias]);
        } else {
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
            $tuples = TupleJoiner::hashJoin($rowsByAlias, $onList, $type === 'left');
        }

        $searchQ = data_get($spec, 'search.q');
        if (is_string($searchQ)) {
            $searchQ = trim($searchQ);
            if ($searchQ !== '') {
                $tuples = $this->filterTuplesBySearch($tuples, $columns, $searchQ);
            }
        }

        return $this->project($tuples, $columns, $offset, $limit);
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
     * @param  list<string>  $on
     * @return list<array<string, array<string, mixed>>>
     */
    private function joinTuples(array $rowsByAlias, array $on, bool $left): array
    {
        return TupleJoiner::hashJoin($rowsByAlias, $on, $left);
    }

    /**
     * @param  array<string, array<string, mixed>>  $tuple
     * @param  list<string>  $on
     * @return array<string, mixed>
     */
    private function extractJoinKeysFromTuple(array $tuple, array $on): array
    {
        $out = [];
        foreach ($on as $field) {
            $out[$field] = null;
            foreach ($tuple as $flat) {
                if (array_key_exists($field, $flat)) {
                    $out[$field] = $flat[$field];
                    break;
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $keyVals
     */
    private function rowMatchesJoinKeys(array $row, array $keyVals): bool
    {
        foreach ($keyVals as $f => $expected) {
            if (! array_key_exists($f, $row)) {
                return false;
            }
            if ($row[$f] != $expected) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array<string, array<string, mixed>>>  $tuples
     * @param  list<mixed>  $columns
     * @return list<array<string, mixed>>
     */
    private function project(array $tuples, array $columns, int $offset, int $limit): array
    {
        $out = [];
        $n = 0;
        $skipped = 0;
        foreach ($tuples as $tuple) {
            if ($skipped < $offset) {
                $skipped++;
                continue;
            }
            if ($n >= $limit) {
                break;
            }
            $row = [];
            foreach ($columns as $col) {
                if (! is_array($col)) {
                    continue;
                }
                $label = $col['label'] ?? null;
                $from = $col['from'] ?? null;
                if (! is_string($label) || $label === '' || ! is_string($from) || $from === '') {
                    continue;
                }
                $row[$label] = $this->resolveFrom($tuple, $from);
            }
            $out[] = $row;
            $n++;
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
     * Filtre "contient" appliqué avant pagination, sur les valeurs projetées.
     *
     * @param  list<array<string, array<string, mixed>>>  $tuples
     * @param  list<mixed>  $columns
     * @return list<array<string, array<string, mixed>>>
     */
    private function filterTuplesBySearch(array $tuples, array $columns, string $q): array
    {
        $needle = mb_strtolower($q);
        $out = [];
        foreach ($tuples as $tuple) {
            $hit = false;
            foreach ($columns as $col) {
                if (! is_array($col)) {
                    continue;
                }
                $from = $col['from'] ?? null;
                if (! is_string($from) || $from === '') {
                    continue;
                }
                $v = $this->resolveFrom($tuple, $from);
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
                $out[] = $tuple;
            }
        }

        return $out;
    }
}
