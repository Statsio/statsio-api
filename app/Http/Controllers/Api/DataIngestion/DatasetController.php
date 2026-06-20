<?php

namespace App\Http\Controllers\Api\DataIngestion;

use App\Http\Controllers\Controller;
use App\Models\DataIngestion\Dataset;
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
        if ($dataset->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Non autorisé.'], 403);
        }

        $limit    = min((int) $request->query('limit', 500), 5000);
        $columns  = $request->query('columns', []);
        $filters  = $request->query('filters', []);
        $distinct = filter_var($request->query('distinct', false), FILTER_VALIDATE_BOOLEAN);
        if (! is_array($columns)) $columns = [];
        if (! is_array($filters)) $filters = [];

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
     */
    private function resolveRows(Dataset $dataset, ?array $selectColumns, array $filters, int $limit): array
    {
        $version = $dataset->latestVersion;

        if (! $version?->parquet_storage_path) {
            return [$dataset->columns->pluck('name')->toArray(), [], $dataset->row_count ?? 0];
        }

        $absolutePath = Storage::path($version->parquet_storage_path);

        if (! file_exists($absolutePath)) {
            return [$dataset->columns->pluck('name')->toArray(), [], $dataset->row_count ?? 0];
        }

        $raw = file_get_contents($absolutePath);
        $decoded = json_decode($raw, true);

        // Mock parquet: { "__mock__": true, "schema": [...], "data": [[...], ...] }
        if (is_array($decoded) && isset($decoded['__mock__'])) {
            $allColumns = $decoded['schema'] ?? [];
            $allRows    = $decoded['data'] ?? [];

            $rows = [];
            foreach ($allRows as $row) {
                $assoc = array_combine($allColumns, $row);
                if (! $this->matchesFilters($assoc, $filters)) continue;
                $rows[] = $selectColumns ? array_intersect_key($assoc, array_flip($selectColumns)) : $assoc;
                if (count($rows) >= $limit) break;
            }

            $totalAfterFilter = count(array_filter(
                $allRows,
                fn($r) => $this->matchesFilters(array_combine($allColumns, $r), $filters),
            ));

            return [$allColumns, $rows, $totalAfterFilter];
        }

        // Real Parquet via DuckDB CLI — build SQL WHERE clause
        $escapedPath = escapeshellarg($absolutePath);
        $colClause   = $selectColumns
            ? implode(', ', array_map(fn($c) => '"' . str_replace('"', '""', $c) . '"', $selectColumns))
            : '*';

        $where = $this->buildDuckDbWhere($filters);
        $sql   = "SELECT {$colClause} FROM read_parquet({$escapedPath}){$where} LIMIT {$limit}";
        $output = shell_exec('duckdb -json -c ' . escapeshellarg($sql) . ' 2>/dev/null');

        if ($output) {
            $jsonRows = json_decode($output, true);
            if (is_array($jsonRows) && count($jsonRows) > 0) {
                $allColumns = array_keys($jsonRows[0]);
                return [$allColumns, $jsonRows, $dataset->row_count ?? count($jsonRows)];
            }
        }

        return [$dataset->columns->pluck('name')->toArray(), [], $dataset->row_count ?? 0];
    }

    private function resolveDistinctValues(Dataset $dataset, string $column, int $limit, string $search = ''): array
    {
        $version = $dataset->latestVersion;

        if (! $version?->parquet_storage_path) return [];

        $absolutePath = Storage::path($version->parquet_storage_path);
        if (! file_exists($absolutePath)) return [];

        $raw     = file_get_contents($absolutePath);
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

        // Real Parquet via DuckDB
        $escapedPath = escapeshellarg($absolutePath);
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

    private function buildDuckDbWhere(array $filters): string
    {
        if (empty($filters)) return '';

        $clauses = [];
        foreach ($filters as $filter) {
            $col   = '"' . str_replace('"', '""', $filter['column'] ?? '') . '"';
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

        // Delete parquet files
        foreach ($dataset->versions as $version) {
            if ($version->parquet_storage_path) {
                Storage::delete($version->parquet_storage_path);
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
