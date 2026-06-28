<?php

namespace App\Http\Controllers\Api\DataIngestion;

use App\Http\Controllers\Controller;
use App\Models\DataIngestion\Dataset;
use App\Models\StudioContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DatasetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $datasets = Dataset::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $datasets->map(fn ($dataset) => $this->formatDataset($dataset)),
            'meta' => [
                'total' => $datasets->total(),
                'per_page' => $datasets->perPage(),
                'current_page' => $datasets->currentPage(),
                'last_page' => $datasets->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, Dataset $dataset): JsonResponse
    {
        if ($dataset->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Non autorisé.'], 403);
        }

        $dataset->load(['columns', 'versions']);

        return response()->json([
            'success' => true,
            'data' => $this->formatDatasetFull($dataset),
        ]);
    }

    public function preview(Request $request, Dataset $dataset): JsonResponse
    {
        if ($dataset->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Non autorisé.'], 403);
        }

        $limit = min((int) $request->query('limit', 5), 100);
        [$columns, $rows, $total] = $this->resolveRows($dataset, null, [], $limit);

        return response()->json([
            'success' => true,
            'data' => ['columns' => $columns, 'rows' => $rows, 'total' => $total],
        ]);
    }

    public function query(Request $request, Dataset $dataset): JsonResponse
    {
        $userId = $request->user()->id;

        if ($dataset->user_id !== $userId) {
            return response()->json(['success' => false, 'message' => 'Non autorisé.'], 403);
        }

        $limit          = min((int) $request->query('limit', 500), 5000);
        $columns        = $request->query('columns', []);
        $filters        = $request->query('filters', []);
        $joins          = $request->query('joins', []);
        $searchQ        = (string) $request->query('search_q', '');
        $searchCols     = $request->query('search_columns', []);
        $distinct       = filter_var($request->query('distinct', false), FILTER_VALIDATE_BOOLEAN);
        $distinctColumn = (string) $request->query('distinct_column', '');
        $sortColumn     = (string) $request->query('sort_column', '');
        $sortDirection  = in_array($request->query('sort_direction'), ['asc', 'desc']) ? $request->query('sort_direction') : 'asc';
        if (! is_array($columns))    $columns    = [];
        if (! is_array($filters))    $filters    = [];
        if (! is_array($joins))      $joins      = [];
        if (! is_array($searchCols)) $searchCols = [];

        if ($distinct && count($columns) === 1) {
            $col    = $columns[0];
            $search = (string) $request->query('search', '');
            $rows   = $this->resolveDistinctValues($dataset, $col, $limit, $search);
            return response()->json([
                'success' => true,
                'data' => [
                    'columns'    => [$col],
                    'rows'       => array_map(fn($v) => [$col => $v], $rows),
                    'total_rows' => count($rows),
                ],
            ]);
        }

        [$allColumns, $rows, $total] = $this->resolveRows(
            $dataset,
            count($columns) ? $columns : null,
            $filters,
            $limit,
            $joins,
            $userId,
            $searchQ,
            $searchCols,
            $distinctColumn ?: null,
            $sortColumn ?: null,
            $sortDirection,
        );

        $finalColumns = count($columns) ? array_values(array_intersect($allColumns, $columns)) : $allColumns;

        return response()->json([
            'success' => true,
            'data' => [
                'columns'    => $finalColumns,
                'rows'       => $rows,
                'total_rows' => $total,
            ],
        ]);
    }

    public function queryPublic(Request $request, string $slug, Dataset $dataset): JsonResponse
    {
        $content = StudioContent::where('status', 'published')
            ->where(function ($q) use ($slug) {
                $q->where('slug', $slug);
                if (is_numeric($slug)) $q->orWhere('id', (int) $slug);
            })
            ->firstOrFail();

        $docDatasetIds = collect($content->blocks ?? [])
            ->flatMap(function ($block) {
                $ids = [$block['datasetId'] ?? null];
                foreach ($block['joins'] ?? [] as $join) {
                    $ids[] = $join['datasetId'] ?? null;
                }
                foreach ($block['fieldMapping']['searchSources'] ?? [] as $source) {
                    $ids[] = $source['datasetId'] ?? null;
                }
                foreach ($block['fieldMapping']['searchJoins'] ?? [] as $j) {
                    $ids[] = $j['datasetId'] ?? null;
                }
                return $ids;
            })
            ->filter()
            ->unique()
            ->values()
            ->map(fn($id) => (string) $id)
            ->toArray();

        if (! in_array((string) $dataset->id, $docDatasetIds, true)) {
            return response()->json(['success' => false, 'message' => 'Dataset non autorisé.'], 403);
        }

        $limit          = min((int) $request->query('limit', 500), 5000);
        $columns        = $request->query('columns', []);
        $filters        = $request->query('filters', []);
        $joins          = $request->query('joins', []);
        $searchQ        = (string) $request->query('search_q', '');
        $searchCols     = $request->query('search_columns', []);
        $distinct       = filter_var($request->query('distinct', false), FILTER_VALIDATE_BOOLEAN);
        $distinctColumn = (string) $request->query('distinct_column', '');
        $sortColumn     = (string) $request->query('sort_column', '');
        $sortDirection  = in_array($request->query('sort_direction'), ['asc', 'desc']) ? $request->query('sort_direction') : 'asc';
        if (! is_array($columns))    $columns    = [];
        if (! is_array($filters))    $filters    = [];
        if (! is_array($joins))      $joins      = [];
        if (! is_array($searchCols)) $searchCols = [];

        if ($distinct && count($columns) === 1) {
            $col    = $columns[0];
            $search = (string) $request->query('search', '');
            $rows   = $this->resolveDistinctValues($dataset, $col, $limit, $search);
            return response()->json([
                'success' => true,
                'data' => [
                    'columns'    => [$col],
                    'rows'       => array_map(fn($v) => [$col => $v], $rows),
                    'total_rows' => count($rows),
                ],
            ]);
        }

        [$allColumns, $rows, $total] = $this->resolveRows(
            $dataset,
            count($columns) ? $columns : null,
            $filters,
            $limit,
            $joins,
            $content->user_id,
            $searchQ,
            $searchCols,
            $distinctColumn ?: null,
            $sortColumn ?: null,
            $sortDirection,
        );

        $finalColumns = count($columns) ? array_values(array_intersect($allColumns, $columns)) : $allColumns;

        return response()->json([
            'success' => true,
            'data' => [
                'columns'    => $finalColumns,
                'rows'       => $rows,
                'total_rows' => $total,
            ],
        ]);
    }

    /**
     * Returns [columns, rows (as assoc arrays), total_row_count].
     *
     * @param  array<int, array{column: string, operator: string, value: string}>  $filters
     * @param  array<int, array{dataset_id: string, left_column: string, right_column: string, columns: array<string>, type: string}>  $joins
     */
    private function resolveRows(Dataset $dataset, ?array $selectColumns, array $filters, int $limit, array $joins = [], int $userId = 0, string $searchQ = '', array $searchCols = [], ?string $distinctColumn = null, ?string $sortColumn = null, string $sortDirection = 'asc'): array
    {
        $version = $dataset->latestVersion;

        if (! $version?->parquet_storage_path) {
            return [$dataset->columns->pluck('name')->toArray(), [], $dataset->row_count ?? 0];
        }

        $datasetsDisk = config('statsio.data_ingestion.datasets_disk', 'local');
        $storagePath = $version->parquet_storage_path;

        $raw = Storage::disk($datasetsDisk)->get($storagePath);
        if ($raw === null) {
            return [$dataset->columns->pluck('name')->toArray(), [], $dataset->row_count ?? 0];
        }

        $decoded = json_decode($raw, true);

        // Mock parquet: { "__mock__": true, "schema": [...], "data": [[...], ...] }
        if (is_array($decoded) && isset($decoded['__mock__'])) {
            $allColumns = $decoded['schema'] ?? [];
            $allRows    = $decoded['data'] ?? [];

            // 1. Filter
            $rows = [];
            foreach ($allRows as $row) {
                $assoc = array_combine($allColumns, $row);
                if (! $this->matchesFilters($assoc, $filters)) continue;
                if (! $this->matchesSearchQ($assoc, $searchQ, $searchCols)) continue;
                $rows[] = $assoc;
            }

            // 2. Sort (before distinct so we pick the "best" row per group)
            if ($sortColumn) {
                usort($rows, function ($a, $b) use ($sortColumn, $sortDirection) {
                    $av = $a[$sortColumn] ?? null;
                    $bv = $b[$sortColumn] ?? null;
                    $cmp = is_numeric($av) && is_numeric($bv)
                        ? ($av <=> $bv)
                        : strcmp((string) $av, (string) $bv);
                    return $sortDirection === 'desc' ? -$cmp : $cmp;
                });
            }

            // 3. Distinct (keep first occurrence per unique column value)
            if ($distinctColumn) {
                $seen = [];
                $rows = array_values(array_filter($rows, function ($r) use ($distinctColumn, &$seen) {
                    $val = (string) ($r[$distinctColumn] ?? '');
                    if (isset($seen[$val])) return false;
                    $seen[$val] = true;
                    return true;
                }));
            }

            $totalAfterFilter = count($rows);

            // 4. Limit
            $rows = array_slice($rows, 0, $limit);

            // Apply each join using a hash join
            foreach ($joins as $join) {
                $rows       = $this->applyMockJoin($rows, $allColumns, $join, $userId);
                $joinCols   = (array) ($join['columns'] ?? []);
                foreach ($joinCols as $jc) {
                    if (! in_array($jc, $allColumns)) $allColumns[] = $jc;
                }
            }

            // Select columns after join so joined columns are included
            if ($selectColumns) {
                $rows = array_map(
                    fn($r) => array_intersect_key($r, array_flip($selectColumns)),
                    $rows,
                );
            }

            return [$allColumns, $rows, $totalAfterFilter];
        }

        // Real Parquet via DuckDB CLI — ensure local file
        $localParquet = tempnam(sys_get_temp_dir(), 'statsio_');
        file_put_contents($localParquet, $raw);
        $escapedPath = escapeshellarg($localParquet);

        $orderClause = $sortColumn
            ? ' ORDER BY "' . str_replace('"', '""', $sortColumn) . '" ' . strtoupper($sortDirection)
            : '';

        if (! empty($joins)) {
            // Build JOIN SQL with DuckDB
            $colClause = 't0.*';

            $joinSql = '';
            foreach ($joins as $idx => $join) {
                $joinDataset = Dataset::where('id', (int) ($join['dataset_id'] ?? 0))
                    ->where('user_id', $userId)
                    ->first();
                if (! $joinDataset) continue;

                $joinVersion = $joinDataset->latestVersion;
                if (! $joinVersion?->parquet_storage_path) continue;

                $joinRaw = Storage::disk($datasetsDisk)->get($joinVersion->parquet_storage_path);
                if ($joinRaw === null) continue;
                $joinPath = tempnam(sys_get_temp_dir(), 'statsio_');
                file_put_contents($joinPath, $joinRaw);

                $alias     = "t" . ($idx + 1);
                $jType     = strtoupper(in_array($join['type'] ?? '', ['inner', 'left']) ? $join['type'] : 'left');
                $leftCol   = '"' . str_replace('"', '""', $join['left_column'] ?? '') . '"';
                $rightCol  = '"' . str_replace('"', '""', $join['right_column'] ?? '') . '"';
                $joinCols  = (array) ($join['columns'] ?? []);

                foreach ($joinCols as $jc) {
                    $colClause .= ', ' . $alias . '."' . str_replace('"', '""', $jc) . '"';
                }

                $joinSql .= " {$jType} JOIN read_parquet(" . escapeshellarg($joinPath) . ") {$alias}";
                $joinSql .= " ON t0.{$leftCol} = {$alias}.{$rightCol}";
            }

            $where = $this->buildDuckDbWhere($filters, 't0');
            $where = $this->appendSearchClause($where, $searchQ, $searchCols, 't0');

            if ($distinctColumn) {
                $escapedDistinct = '"' . str_replace('"', '""', $distinctColumn) . '"';
                $innerOrder = $sortColumn
                    ? ' ORDER BY ' . $escapedDistinct . ', "' . str_replace('"', '""', $sortColumn) . '" ' . strtoupper($sortDirection)
                    : ' ORDER BY ' . $escapedDistinct;
                // Use t0.* in inner query so all columns (sort, distinct) are available in outer SELECT
                $inner  = "SELECT DISTINCT ON ({$escapedDistinct}) {$colClause} FROM read_parquet({$escapedPath}) t0{$joinSql}{$where}{$innerOrder}";
                $outerCols = $selectColumns
                    ? implode(', ', array_map(fn($c) => '"' . str_replace('"', '""', $c) . '"', $selectColumns))
                    : '*';
                $sql = "SELECT {$outerCols} FROM ({$inner}) sub{$orderClause} LIMIT {$limit}";
            } else {
                $sql = "SELECT {$colClause} FROM read_parquet({$escapedPath}) t0{$joinSql}{$where}{$orderClause} LIMIT {$limit}";
            }
        } else {
            $colClause = $selectColumns
                ? implode(', ', array_map(fn($c) => '"' . str_replace('"', '""', $c) . '"', $selectColumns))
                : '*';

            $where = $this->buildDuckDbWhere($filters);
            $where = $this->appendSearchClause($where, $searchQ, $searchCols);

            if ($distinctColumn) {
                $escapedDistinct = '"' . str_replace('"', '""', $distinctColumn) . '"';
                $innerOrder = $sortColumn
                    ? ' ORDER BY ' . $escapedDistinct . ', "' . str_replace('"', '""', $sortColumn) . '" ' . strtoupper($sortDirection)
                    : ' ORDER BY ' . $escapedDistinct;
                // Use * in inner query so sort/distinct columns are available to the outer SELECT
                $inner     = "SELECT DISTINCT ON ({$escapedDistinct}) * FROM read_parquet({$escapedPath}){$where}{$innerOrder}";
                $outerCols = $selectColumns
                    ? implode(', ', array_map(fn($c) => '"' . str_replace('"', '""', $c) . '"', $selectColumns))
                    : '*';
                $sql = "SELECT {$outerCols} FROM ({$inner}) sub{$orderClause} LIMIT {$limit}";
            } else {
                $sql = "SELECT {$colClause} FROM read_parquet({$escapedPath}){$where}{$orderClause} LIMIT {$limit}";
            }
        }

        $output = shell_exec('duckdb -json -c ' . escapeshellarg($sql) . ' 2>/dev/null');

        if ($output) {
            $jsonRows = json_decode($output, true);
            if (is_array($jsonRows) && count($jsonRows) > 0) {
                $allColumns = array_keys($jsonRows[0]);
                if (! empty($joins) && $selectColumns) {
                    $jsonRows = array_map(
                        fn($r) => array_intersect_key($r, array_flip($selectColumns)),
                        $jsonRows,
                    );
                }
                return [$allColumns, $jsonRows, $dataset->row_count ?? count($jsonRows)];
            }
        }

        return [$dataset->columns->pluck('name')->toArray(), [], $dataset->row_count ?? 0];
    }

    /**
     * Hash join for mock parquet datasets.
     *
     * @param  array<int, array<string, mixed>>  $primaryRows
     * @param  array<string>  $primaryColumns
     * @param  array{dataset_id: string, left_column: string, right_column: string, columns: array<string>, type: string}  $join
     */
    private function applyMockJoin(array $primaryRows, array $primaryColumns, array $join, int $userId): array
    {
        $joinDataset = Dataset::where('id', (int) ($join['dataset_id'] ?? 0))
            ->where('user_id', $userId)
            ->first();

        if (! $joinDataset) return $primaryRows;

        $joinVersion = $joinDataset->latestVersion;
        if (! $joinVersion?->parquet_storage_path) return $primaryRows;

        $datasetsDisk = config('statsio.data_ingestion.datasets_disk', 'local');
        $joinRaw = Storage::disk($datasetsDisk)->get($joinVersion->parquet_storage_path);
        if ($joinRaw === null) return $primaryRows;
        $joinDecoded = json_decode($joinRaw, true);

        if (! is_array($joinDecoded) || ! isset($joinDecoded['__mock__'])) return $primaryRows;

        $jSchema   = $joinDecoded['schema'] ?? [];
        $leftCol   = (string) ($join['left_column'] ?? '');
        $rightCol  = (string) ($join['right_column'] ?? '');
        $joinCols  = (array) ($join['columns'] ?? []);
        $isInner   = ($join['type'] ?? 'left') === 'inner';

        // Build hash index: rightColumn value → first matching row (1:1 join)
        $index = [];
        foreach ($joinDecoded['data'] ?? [] as $jRow) {
            $jAssoc = array_combine($jSchema, $jRow);
            $key    = (string) ($jAssoc[$rightCol] ?? '');
            if ($key !== '' && ! isset($index[$key])) {
                $index[$key] = $jAssoc;
            }
        }

        $nullRow = array_fill_keys($joinCols, null);
        $result  = [];

        foreach ($primaryRows as $row) {
            $key   = (string) ($row[$leftCol] ?? '');
            $match = $index[$key] ?? null;

            if ($match === null) {
                if (! $isInner) {
                    $result[] = array_merge($row, $nullRow);
                }
            } else {
                $merged = $row;
                foreach ($joinCols as $jc) {
                    $merged[$jc] = $match[$jc] ?? null;
                }
                $result[] = $merged;
            }
        }

        return $result;
    }

    private function resolveDistinctValues(Dataset $dataset, string $column, int $limit, string $search = ''): array
    {
        $version = $dataset->latestVersion;

        if (! $version?->parquet_storage_path) return [];

        $datasetsDisk = config('statsio.data_ingestion.datasets_disk', 'local');

        $raw = Storage::disk($datasetsDisk)->get($version->parquet_storage_path);
        if ($raw === null) return [];

        $decoded = json_decode($raw, true);

        // Mock parquet path — scan all rows, no hard limit before filtering
        if (is_array($decoded) && isset($decoded['__mock__'])) {
            $allColumns = $decoded['schema'] ?? [];
            $allRows    = $decoded['data'] ?? [];
            $needle     = mb_strtolower($search);

            $seen = [];
            foreach ($allRows as $row) {
                $assoc = array_is_list($row) ? array_combine($allColumns, $row) : $row;
                $val   = $assoc[$column] ?? null;
                if ($val === null || $val === '') continue;
                $str = (string) $val;
                if ($needle !== '' && mb_stripos($str, $needle) === false) continue;
                $seen[$str] = true;
            }
            $values = array_keys($seen);
            sort($values);
            return array_slice($values, 0, $limit);
        }

        // Real Parquet via DuckDB — write to local temp
        $localParquet = tempnam(sys_get_temp_dir(), 'statsio_');
        file_put_contents($localParquet, $raw);
        $escapedPath = escapeshellarg($localParquet);
        $escapedCol  = '"' . str_replace('"', '""', $column) . '"';
        $whereSearch = $search !== ''
            ? " AND lower({$escapedCol}::VARCHAR) LIKE lower(" . escapeshellarg('%' . $search . '%') . ")"
            : '';
        $sql    = "SELECT DISTINCT {$escapedCol} FROM read_parquet({$escapedPath}) WHERE {$escapedCol} IS NOT NULL{$whereSearch} ORDER BY {$escapedCol} LIMIT {$limit}";
        $output = shell_exec('duckdb -json -c ' . escapeshellarg($sql) . ' 2>/dev/null');

        if ($output) {
            $jsonRows = json_decode($output, true);
            if (is_array($jsonRows)) {
                return array_values(array_filter(array_map(fn($r) => (string) ($r[$column] ?? ''), $jsonRows), fn($v) => $v !== ''));
            }
        }

        return [];
    }

    private function matchesFilters(array $row, array $filters): bool
    {
        foreach ($filters as $filter) {
            $col      = $filter['column'] ?? '';
            $operator = $filter['operator'] ?? '=';
            $value    = (string) ($filter['value'] ?? '');

            if (! isset($row[$col])) continue;

            $cell = (string) $row[$col];

            $match = match ($operator) {
                '='           => strtolower($cell) === strtolower($value),
                '!='          => strtolower($cell) !== strtolower($value),
                '>'           => is_numeric($cell) && is_numeric($value) && (float) $cell > (float) $value,
                '>='          => is_numeric($cell) && is_numeric($value) && (float) $cell >= (float) $value,
                '<'           => is_numeric($cell) && is_numeric($value) && (float) $cell < (float) $value,
                '<='          => is_numeric($cell) && is_numeric($value) && (float) $cell <= (float) $value,
                'contains'    => str_contains(strtolower($cell), strtolower($value)),
                'not_contains'=> ! str_contains(strtolower($cell), strtolower($value)),
                default       => true,
            };

            if (! $match) return false;
        }
        return true;
    }

    private function matchesSearchQ(array $row, string $searchQ, array $searchCols): bool
    {
        if ($searchQ === '' || empty($searchCols)) return true;
        $needle = mb_strtolower($searchQ);
        foreach ($searchCols as $col) {
            if (mb_stripos((string) ($row[$col] ?? ''), $needle) !== false) return true;
        }
        return false;
    }

    private function appendSearchClause(string $where, string $searchQ, array $searchCols, string $tableAlias = ''): string
    {
        if ($searchQ === '' || empty($searchCols)) return $where;
        $prefix   = $tableAlias ? "{$tableAlias}." : '';
        $val      = "'" . str_replace("'", "''", $searchQ) . "'";
        $clauses  = array_map(function ($col) use ($prefix, $val) {
            $c = $prefix . '"' . str_replace('"', '""', $col) . '"';
            return "LOWER({$c}::VARCHAR) LIKE LOWER(CONCAT('%', {$val}, '%'))";
        }, $searchCols);
        $clause = '(' . implode(' OR ', $clauses) . ')';
        return $where === '' ? " WHERE {$clause}" : "{$where} AND {$clause}";
    }

    private function buildDuckDbWhere(array $filters, string $tableAlias = ''): string
    {
        if (empty($filters)) return '';

        $prefix  = $tableAlias ? "{$tableAlias}." : '';
        $clauses = [];
        foreach ($filters as $filter) {
            $col   = $prefix . '"' . str_replace('"', '""', $filter['column'] ?? '') . '"';
            $val   = "'" . str_replace("'", "''", $filter['value'] ?? '') . "'";
            $op    = $filter['operator'] ?? '=';

            $clauses[] = match ($op) {
                '='           => "{$col} = {$val}",
                '!='          => "{$col} != {$val}",
                '>'           => "TRY_CAST({$col} AS DOUBLE) > TRY_CAST({$val} AS DOUBLE)",
                '>='          => "TRY_CAST({$col} AS DOUBLE) >= TRY_CAST({$val} AS DOUBLE)",
                '<'           => "TRY_CAST({$col} AS DOUBLE) < TRY_CAST({$val} AS DOUBLE)",
                '<='          => "TRY_CAST({$col} AS DOUBLE) <= TRY_CAST({$val} AS DOUBLE)",
                'contains'    => "LOWER({$col}) LIKE LOWER(CONCAT('%', {$val}, '%'))",
                'not_contains'=> "LOWER({$col}) NOT LIKE LOWER(CONCAT('%', {$val}, '%'))",
                default       => '1=1',
            };
        }

        return ' WHERE ' . implode(' AND ', $clauses);
    }

    public function update(Request $request, Dataset $dataset): JsonResponse
    {
        if ($dataset->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Non autorisé.'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string|max:1000',
        ]);

        $dataset->update($validated);

        return response()->json([
            'success' => true,
            'data' => $this->formatDataset($dataset->fresh()),
        ]);
    }

    public function destroy(Request $request, Dataset $dataset): JsonResponse
    {
        if ($dataset->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Non autorisé.'], 403);
        }

        $dataSource = $dataset->dataSource;
        $datasetsDisk = config('statsio.data_ingestion.datasets_disk', 'local');

        // Delete parquet files from the datasets disk (local or R2)
        foreach ($dataset->versions as $version) {
            if ($version->parquet_storage_path) {
                Storage::disk($datasetsDisk)->delete($version->parquet_storage_path);
            }
        }
        // Raw file may already be null (deleted after conversion)
        if ($dataSource?->raw_storage_path) {
            Storage::delete($dataSource->raw_storage_path);
        }

        // Deleting the data_source cascades to dataset, columns, versions
        $dataSource?->delete() ?? $dataset->delete();

        return response()->json(['success' => true, 'message' => 'Source supprimée.'], 200);
    }

    private function formatDataset(Dataset $dataset): array
    {
        return [
            'id' => $dataset->id,
            'name' => $dataset->name,
            'description' => $dataset->description,
            'row_count' => $dataset->row_count,
            'status' => $dataset->status->value,
            'created_at' => $dataset->created_at->toIso8601String(),
        ];
    }

    private function formatDatasetFull(Dataset $dataset): array
    {
        return [
            ...$this->formatDataset($dataset),
            'data_source_id' => $dataset->data_source_id,
            'columns' => $dataset->columns->map(fn ($col) => [
                'name' => $col->name,
                'type' => $col->type->value,
                'nullable' => $col->nullable,
                'sample_values' => $col->sample_values,
                'order' => $col->column_order,
            ])->values(),
            'versions' => $dataset->versions->map(fn ($v) => [
                'version_number' => $v->version_number,
                'row_count' => $v->row_count,
                'file_size_bytes' => $v->file_size_bytes,
                'created_at' => $v->created_at->toIso8601String(),
            ])->values(),
        ];
    }
}
