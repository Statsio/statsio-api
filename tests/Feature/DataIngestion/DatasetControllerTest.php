<?php

namespace Tests\Feature\DataIngestion;

use App\Models\DataIngestion\Dataset;
use App\Models\DataIngestion\DatasetColumn;
use App\Models\DataIngestion\DatasetVersion;
use App\Models\DataIngestion\DataSource;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatasetControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    /**
     * Builds an uploaded (non-live) dataset backed by the "mock parquet" JSON format
     * that fetchParquetRows()/fetchDistinctValues() understand when the real DuckDB
     * CLI isn't available (which it isn't on this machine — see `which duckdb`).
     *
     * @param  array<string>  $schema
     * @param  array<int, array>  $rows
     */
    private function createMockDataset(User $user, array $schema, array $rows, string $name = 'Dataset test'): Dataset
    {
        $dataSource = DataSource::create([
            'user_id' => $user->id,
            'name' => $name,
            'type' => 'csv',
            'source_kind' => 'upload',
            'materialization' => 'snapshot',
            'original_filename' => 'test.csv',
            'raw_storage_path' => 'data-sources/'.uniqid().'.csv',
            'file_size_bytes' => 100,
            'status' => 'ready',
        ]);

        $dataset = Dataset::create([
            'data_source_id' => $dataSource->id,
            'user_id' => $user->id,
            'name' => $name,
            'row_count' => count($rows),
            'status' => 'ready',
        ]);

        foreach ($schema as $i => $col) {
            DatasetColumn::create([
                'dataset_id' => $dataset->id,
                'name' => $col,
                'type' => 'string',
                'column_order' => $i,
            ]);
        }

        $path = "datasets/{$dataset->id}/v1.parquet";
        Storage::disk('local')->put($path, json_encode(['__mock__' => true, 'schema' => $schema, 'data' => $rows]));

        DatasetVersion::create([
            'dataset_id' => $dataset->id,
            'version_number' => 1,
            'parquet_storage_path' => $path,
            'file_size_bytes' => 100,
            'row_count' => count($rows),
        ]);

        return $dataset->fresh(['columns', 'versions', 'latestVersion', 'dataSource']);
    }

    private const SCHEMA = ['id', 'city', 'country', 'population'];

    private const ROWS = [
        [1, 'Paris', 'France', 2148000],
        [2, 'Lyon', 'France', 513000],
        [3, 'Berlin', 'Germany', 3645000],
        [4, 'Madrid', 'Spain', 3223000],
    ];

    // ---- index / show ----

    public function test_index_lists_own_and_attached_datasets(): void
    {
        $user = User::factory()->create();
        $owned = $this->createMockDataset($user, self::SCHEMA, self::ROWS, 'Owned');

        $other = User::factory()->create();
        $attached = $this->createMockDataset($other, self::SCHEMA, self::ROWS, 'Attached');
        $attached->dataSource->users()->attach($user->id);

        $unrelated = $this->createMockDataset($other, self::SCHEMA, self::ROWS, 'Unrelated');

        $response = $this->withToken($user->createToken('t')->plainTextToken)->getJson('/api/datasets');

        $response->assertStatus(200)->assertJsonPath('success', true);
        $names = collect($response->json('data'))->pluck('name')->all();
        sort($names);
        $this->assertSame(['Attached', 'Owned'], $names);
    }

    public function test_show_returns_403_when_not_accessible(): void
    {
        $owner = User::factory()->create();
        $dataset = $this->createMockDataset($owner, self::SCHEMA, self::ROWS);

        $intruder = User::factory()->create();

        $this->withToken($intruder->createToken('t')->plainTextToken)
            ->getJson("/api/datasets/{$dataset->id}")
            ->assertStatus(403);
    }

    public function test_show_returns_full_detail_with_columns_and_versions(): void
    {
        $user = User::factory()->create();
        $dataset = $this->createMockDataset($user, self::SCHEMA, self::ROWS);

        $response = $this->withToken($user->createToken('t')->plainTextToken)
            ->getJson("/api/datasets/{$dataset->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.row_count', 4)
            ->assertJsonCount(4, 'data.columns')
            ->assertJsonCount(1, 'data.versions');
    }

    // ---- preview ----

    public function test_preview_returns_limited_rows(): void
    {
        $user = User::factory()->create();
        $dataset = $this->createMockDataset($user, self::SCHEMA, self::ROWS);

        $response = $this->withToken($user->createToken('t')->plainTextToken)
            ->getJson("/api/datasets/{$dataset->id}/preview?limit=2");

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertCount(2, $response->json('data.rows'));
    }

    // ---- query: filters / search / sort / distinct / aggregate / joins ----

    public function test_query_filters_by_equality(): void
    {
        $user = User::factory()->create();
        $dataset = $this->createMockDataset($user, self::SCHEMA, self::ROWS);

        $response = $this->withToken($user->createToken('t')->plainTextToken)->getJson(
            "/api/datasets/{$dataset->id}/query?filters[0][column]=country&filters[0][operator]==&filters[0][value]=France"
        );

        $response->assertStatus(200)->assertJsonPath('data.total_rows', 2);
    }

    public function test_query_filters_by_numeric_comparison(): void
    {
        $user = User::factory()->create();
        $dataset = $this->createMockDataset($user, self::SCHEMA, self::ROWS);

        $response = $this->withToken($user->createToken('t')->plainTextToken)->getJson(
            "/api/datasets/{$dataset->id}/query?filters[0][column]=population&filters[0][operator]=>&filters[0][value]=2000000"
        );

        $response->assertStatus(200)->assertJsonPath('data.total_rows', 3);
    }

    public function test_query_searches_across_columns(): void
    {
        $user = User::factory()->create();
        $dataset = $this->createMockDataset($user, self::SCHEMA, self::ROWS);

        $response = $this->withToken($user->createToken('t')->plainTextToken)->getJson(
            "/api/datasets/{$dataset->id}/query?search_q=par&search_columns[0]=city"
        );

        $response->assertStatus(200)->assertJsonPath('data.total_rows', 1);
        $this->assertSame('Paris', $response->json('data.rows.0.city'));
    }

    public function test_query_sorts_descending(): void
    {
        $user = User::factory()->create();
        $dataset = $this->createMockDataset($user, self::SCHEMA, self::ROWS);

        $response = $this->withToken($user->createToken('t')->plainTextToken)->getJson(
            "/api/datasets/{$dataset->id}/query?sort_column=population&sort_direction=desc"
        );

        $response->assertStatus(200);
        $this->assertSame('Berlin', $response->json('data.rows.0.city'));
    }

    public function test_query_selects_specific_columns(): void
    {
        $user = User::factory()->create();
        $dataset = $this->createMockDataset($user, self::SCHEMA, self::ROWS);

        $response = $this->withToken($user->createToken('t')->plainTextToken)->getJson(
            "/api/datasets/{$dataset->id}/query?columns[0]=city&columns[1]=country"
        );

        $response->assertStatus(200);
        $this->assertSame(['city', 'country'], $response->json('data.columns'));
        $this->assertSame(['city', 'country'], array_keys($response->json('data.rows.0')));
    }

    public function test_query_distinct_returns_unique_sorted_values(): void
    {
        $user = User::factory()->create();
        $dataset = $this->createMockDataset($user, self::SCHEMA, self::ROWS);

        $response = $this->withToken($user->createToken('t')->plainTextToken)->getJson(
            "/api/datasets/{$dataset->id}/query?distinct=1&columns[0]=country&distinct_column=country"
        );

        $response->assertStatus(200);
        $values = collect($response->json('data.rows'))->pluck('country')->all();
        $this->assertSame(['France', 'Germany', 'Spain'], $values);
    }

    public function test_query_distinct_from_a_joined_source(): void
    {
        $user = User::factory()->create();
        $cities = $this->createMockDataset($user, ['id', 'city', 'region_code'], [
            [1, 'Paris', 'IDF'],
            [2, 'Lyon', 'ARA'],
            [3, 'Nice', 'PACA'],
        ], 'Cities');
        $regions = $this->createMockDataset($user, ['code', 'zone'], [
            ['IDF', 'Nord'],
            ['ARA', 'Sud'],
            ['PACA', 'Sud'],
        ], 'Regions');

        $response = $this->withToken($user->createToken('t')->plainTextToken)->getJson(
            "/api/datasets/{$cities->id}/query?".http_build_query([
                'distinct' => '1',
                'distinct_column' => 'zone@r',
                'columns' => ['zone@r'],
                'sources' => [
                    ['id' => 'c', 'dataset_id' => (string) $cities->id],
                    ['id' => 'r', 'dataset_id' => (string) $regions->id],
                ],
                'joins' => [['left_source' => 'c', 'left_column' => 'region_code', 'right_source' => 'r', 'right_column' => 'code', 'type' => 'inner']],
            ])
        );

        $response->assertStatus(200);
        $this->assertSame(['Nord', 'Sud'], collect($response->json('data.rows'))->pluck('zone@r')->all());
    }

    public function test_query_aggregates_with_group_by(): void
    {
        $user = User::factory()->create();
        $dataset = $this->createMockDataset($user, self::SCHEMA, self::ROWS);

        $response = $this->withToken($user->createToken('t')->plainTextToken)->getJson(
            "/api/datasets/{$dataset->id}/query?aggregate=sum&aggregate_columns[0]=population&group_by[0]=country"
        );

        $response->assertStatus(200);
        $rows = collect($response->json('data.rows'))->keyBy('country');
        $this->assertSame(2661000, (int) $rows['France']['population']);
        $this->assertSame(3645000, (int) $rows['Germany']['population']);
    }

    private const YEARLY = [
        ['id', 'annee', 'valeur'],
        [
            [1, '2021', 10],
            [2, '2019', 5],
            [3, '2022', 40],
            [4, '2020', 20],
            [5, '2021', 30],
            [6, '2019', 15],
        ],
    ];

    public function test_query_aggregates_are_ordered_by_the_group_key_by_default(): void
    {
        $user = User::factory()->create();
        $dataset = $this->createMockDataset($user, self::YEARLY[0], self::YEARLY[1]);

        $response = $this->withToken($user->createToken('t')->plainTextToken)->getJson(
            "/api/datasets/{$dataset->id}/query?aggregate=sum&aggregate_columns[0]=valeur&group_by[0]=annee"
        );

        $response->assertStatus(200);
        $this->assertSame(['2019', '2020', '2021', '2022'], collect($response->json('data.rows'))->pluck('annee')->all());
    }

    public function test_query_aggregates_respect_an_explicit_sort_on_the_group_column(): void
    {
        $user = User::factory()->create();
        $dataset = $this->createMockDataset($user, self::YEARLY[0], self::YEARLY[1]);

        $response = $this->withToken($user->createToken('t')->plainTextToken)->getJson(
            "/api/datasets/{$dataset->id}/query?aggregate=sum&aggregate_columns[0]=valeur&group_by[0]=annee&sort_column=annee&sort_direction=desc"
        );

        $response->assertStatus(200);
        $this->assertSame(['2022', '2021', '2020', '2019'], collect($response->json('data.rows'))->pluck('annee')->all());
    }

    public function test_query_aggregates_can_be_sorted_by_the_aggregated_value(): void
    {
        $user = User::factory()->create();
        $dataset = $this->createMockDataset($user, self::YEARLY[0], self::YEARLY[1]);

        $response = $this->withToken($user->createToken('t')->plainTextToken)->getJson(
            "/api/datasets/{$dataset->id}/query?aggregate=sum&aggregate_columns[0]=valeur&group_by[0]=annee&sort_column=valeur&sort_direction=desc"
        );

        $response->assertStatus(200);
        // sommes : 2019=20, 2020=20, 2021=40, 2022=40 → décroissant sur la valeur
        $this->assertSame([40, 40, 20, 20], collect($response->json('data.rows'))->pluck('valeur')->map(fn ($v) => (int) $v)->all());
    }

    private const CALC = [
        ['id', 'annee', 'a', 'b'],
        [
            [1, '2019', 10, 40],
            [2, '2019', 20, 60],
            [3, '2020', 30, 30],
            [4, '2020', 40, 10],
        ],
    ];

    /** `calc[0]` = `a + b`, injectée puis agrégée `SUM(calc:t)` par année. */
    public function test_query_calc_column_aggregate(): void
    {
        $user = User::factory()->create();
        $dataset = $this->createMockDataset($user, self::CALC[0], self::CALC[1]);

        $response = $this->withToken($user->createToken('t')->plainTextToken)->getJson(
            "/api/datasets/{$dataset->id}/query?".http_build_query([
                'columns' => ['annee', 'calc:t'],
                'calc' => [['id' => 't', 'operands' => [['column' => 'a'], ['op' => '+', 'column' => 'b']]]],
                'aggregates' => [['column' => 'calc:t', 'fn' => 'sum']],
                'group_by' => ['annee'],
                'sort_column' => 'annee',
            ])
        );

        $response->assertStatus(200);
        // 2019 : (10+40)+(20+60)=130 ; 2020 : (30+30)+(40+10)=110
        $rows = collect($response->json('data.rows'))->keyBy('annee');
        $this->assertSame(130, (int) $rows['2019']['calc:t']);
        $this->assertSame(110, (int) $rows['2020']['calc:t']);
    }

    public function test_query_calc_column_division_and_constant_with_zero_guard(): void
    {
        $user = User::factory()->create();
        $dataset = $this->createMockDataset($user, ['id', 'a', 'b'], [
            [1, 30, 60],   // 30/60*100 = 50
            [2, 5, 0],     // /0 → null
        ]);

        $response = $this->withToken($user->createToken('t')->plainTextToken)->getJson(
            "/api/datasets/{$dataset->id}/query?".http_build_query([
                'columns' => ['id', 'calc:r'],
                'calc' => [['id' => 'r', 'operands' => [
                    ['column' => 'a'],
                    ['op' => '/', 'column' => 'b'],
                    ['op' => '*', 'value' => 100],
                ]]],
                'sort_column' => 'id',
            ])
        );

        $response->assertStatus(200);
        $rows = collect($response->json('data.rows'))->all();
        $this->assertSame(50.0, (float) $rows[0]['calc:r']);
        $this->assertNull($rows[1]['calc:r']);
    }

    public function test_query_calc_column_can_be_the_sort_key(): void
    {
        $user = User::factory()->create();
        $dataset = $this->createMockDataset($user, self::CALC[0], self::CALC[1]);

        $response = $this->withToken($user->createToken('t')->plainTextToken)->getJson(
            "/api/datasets/{$dataset->id}/query?".http_build_query([
                'columns' => ['annee', 'calc:s'],
                'calc' => [['id' => 's', 'operands' => [['column' => 'a'], ['op' => '-', 'column' => 'b']]]],
                'aggregates' => [['column' => 'calc:s', 'fn' => 'sum']],
                'group_by' => ['annee'],
                'sort_column' => 'calc:s',
                'sort_direction' => 'desc',
            ])
        );

        $response->assertStatus(200);
        // 2019 : (10-40)+(20-60) = -70 ; 2020 : (30-30)+(40-10) = 30 → desc
        $this->assertSame(['2020', '2019'], collect($response->json('data.rows'))->pluck('annee')->all());
    }

    public function test_query_calc_column_across_joined_sources(): void
    {
        $user = User::factory()->create();
        $cities = $this->createMockDataset($user, ['id', 'city', 'region_code', 'sales'], [
            [1, 'Paris', 'IDF', 100],
            [2, 'Lyon', 'ARA', 40],
        ], 'Cities');
        $regions = $this->createMockDataset($user, ['code', 'bonus'], [
            ['IDF', 5],
            ['ARA', 3],
        ], 'Regions');

        $response = $this->withToken($user->createToken('t')->plainTextToken)->getJson(
            "/api/datasets/{$cities->id}/query?".http_build_query([
                'columns' => ['city', 'calc:tot'],
                'sources' => [
                    ['id' => 'c', 'dataset_id' => (string) $cities->id],
                    ['id' => 'r', 'dataset_id' => (string) $regions->id],
                ],
                'joins' => [['left_source' => 'c', 'left_column' => 'region_code', 'right_source' => 'r', 'right_column' => 'code', 'type' => 'inner']],
                'calc' => [['id' => 'tot', 'operands' => [['column' => 'sales'], ['op' => '+', 'column' => 'bonus@r']]]],
                'sort_column' => 'city',
            ])
        );

        $response->assertStatus(200);
        $rows = collect($response->json('data.rows'))->keyBy('city');
        $this->assertSame(105, (int) $rows['Paris']['calc:tot']);
        $this->assertSame(43, (int) $rows['Lyon']['calc:tot']);
    }

    private const FACET_SCHEMA = ['id', 'annee', 'region'];

    private const FACET_ROWS = [
        [1, '2025', 'Bretagne'],
        [2, '2025', 'Normandie'],
        [3, '2025', 'Bretagne'],
        [4, '2024', 'Bretagne'],
        [5, '2024', 'Occitanie'],
        [6, '2023', 'Bretagne'],
    ];

    public function test_query_facet_returns_values_with_counts_sorted_desc(): void
    {
        $user = User::factory()->create();
        $dataset = $this->createMockDataset($user, self::FACET_SCHEMA, self::FACET_ROWS);

        $response = $this->withToken($user->createToken('t')->plainTextToken)->getJson(
            "/api/datasets/{$dataset->id}/query?facet=1&columns[0]=annee"
        );

        $response->assertStatus(200)
            ->assertJsonPath('meta.has_counts', true)
            ->assertJsonPath('meta.partial', false)
            ->assertJsonPath('data.column', 'annee')
            ->assertJsonPath('data.total', 3);

        $this->assertSame(
            [['value' => '2025', 'count' => 3], ['value' => '2024', 'count' => 2], ['value' => '2023', 'count' => 1]],
            $response->json('data.values'),
        );
    }

    public function test_query_facet_respects_search(): void
    {
        $user = User::factory()->create();
        $dataset = $this->createMockDataset($user, self::FACET_SCHEMA, self::FACET_ROWS);

        $response = $this->withToken($user->createToken('t')->plainTextToken)->getJson(
            "/api/datasets/{$dataset->id}/query?facet=1&columns[0]=region&search=bret"
        );

        $response->assertStatus(200);
        $this->assertSame([['value' => 'Bretagne', 'count' => 4]], $response->json('data.values'));
    }

    public function test_query_facet_applies_filters(): void
    {
        $user = User::factory()->create();
        $dataset = $this->createMockDataset($user, self::FACET_SCHEMA, self::FACET_ROWS);

        $response = $this->withToken($user->createToken('t')->plainTextToken)->getJson(
            "/api/datasets/{$dataset->id}/query?facet=1&columns[0]=region&filters[0][column]=annee&filters[0][operator]==&filters[0][value]=2025"
        );

        $response->assertStatus(200);
        $this->assertSame(
            [['value' => 'Bretagne', 'count' => 2], ['value' => 'Normandie', 'count' => 1]],
            $response->json('data.values'),
        );
    }

    public function test_query_facet_pagination(): void
    {
        $user = User::factory()->create();
        $dataset = $this->createMockDataset($user, self::FACET_SCHEMA, self::FACET_ROWS);

        $response = $this->withToken($user->createToken('t')->plainTextToken)->getJson(
            "/api/datasets/{$dataset->id}/query?facet=1&columns[0]=annee&facet_limit=1&facet_offset=1"
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.offset', 1)
            ->assertJsonPath('data.limit', 1);
        $this->assertSame([['value' => '2024', 'count' => 2]], $response->json('data.values'));
    }

    public function test_query_facet_from_a_joined_source(): void
    {
        $user = User::factory()->create();
        $cities = $this->createMockDataset($user, ['id', 'city', 'region_code'], [
            [1, 'Paris', 'IDF'],
            [2, 'Lyon', 'ARA'],
            [3, 'Nice', 'PACA'],
            [4, 'Marseille', 'PACA'],
        ], 'Cities');
        $regions = $this->createMockDataset($user, ['code', 'zone'], [
            ['IDF', 'Nord'],
            ['ARA', 'Sud'],
            ['PACA', 'Sud'],
        ], 'Regions');

        $response = $this->withToken($user->createToken('t')->plainTextToken)->getJson(
            "/api/datasets/{$cities->id}/query?".http_build_query([
                'facet' => '1',
                'columns' => ['zone@r'],
                'sources' => [
                    ['id' => 'c', 'dataset_id' => (string) $cities->id],
                    ['id' => 'r', 'dataset_id' => (string) $regions->id],
                ],
                'joins' => [['left_source' => 'c', 'left_column' => 'region_code', 'right_source' => 'r', 'right_column' => 'code', 'type' => 'inner']],
            ])
        );

        $response->assertStatus(200)->assertJsonPath('meta.has_counts', true);
        $this->assertSame(
            [['value' => 'Sud', 'count' => 3], ['value' => 'Nord', 'count' => 1]],
            $response->json('data.values'),
        );
    }

    public function test_query_facet_is_cached_until_dataset_is_reingested(): void
    {
        $user = User::factory()->create();
        $dataset = $this->createMockDataset($user, self::FACET_SCHEMA, self::FACET_ROWS);
        $token = $user->createToken('t')->plainTextToken;
        $url = "/api/datasets/{$dataset->id}/query?facet=1&columns[0]=annee";

        $this->withToken($token)->getJson($url)->assertStatus(200);

        // Nouvelle version (checksum différent) → la clé de cache change, le décompte est recalculé.
        $path = "datasets/{$dataset->id}/v2.parquet";
        Storage::disk('local')->put($path, json_encode(['__mock__' => true, 'schema' => self::FACET_SCHEMA, 'data' => [
            [1, '2030', 'Bretagne'],
        ]]));
        DatasetVersion::create([
            'dataset_id' => $dataset->id,
            'version_number' => 2,
            'parquet_storage_path' => $path,
            'checksum' => 'v2-checksum',
            'file_size_bytes' => 50,
            'row_count' => 1,
        ]);

        $response = $this->withToken($token)->getJson($url);
        $response->assertStatus(200);
        $this->assertSame([['value' => '2030', 'count' => 1]], $response->json('data.values'));
    }

    public function test_query_filter_in_operator(): void
    {
        $user = User::factory()->create();
        $dataset = $this->createMockDataset($user, self::FACET_SCHEMA, self::FACET_ROWS);

        $response = $this->withToken($user->createToken('t')->plainTextToken)->getJson(
            "/api/datasets/{$dataset->id}/query?".http_build_query([
                'columns' => ['id'],
                'filters' => [['column' => 'annee', 'operator' => 'in', 'value' => json_encode(['2024', '2023'])]],
                'sort_column' => 'id',
            ])
        );

        $response->assertStatus(200);
        $this->assertSame(['4', '5', '6'], collect($response->json('data.rows'))->pluck('id')->map(fn ($v) => (string) $v)->all());
    }

    public function test_query_filter_not_in_operator(): void
    {
        $user = User::factory()->create();
        $dataset = $this->createMockDataset($user, self::FACET_SCHEMA, self::FACET_ROWS);

        $response = $this->withToken($user->createToken('t')->plainTextToken)->getJson(
            "/api/datasets/{$dataset->id}/query?".http_build_query([
                'columns' => ['id'],
                'filters' => [['column' => 'annee', 'operator' => 'not_in', 'value' => json_encode(['2024', '2023'])]],
                'sort_column' => 'id',
            ])
        );

        $response->assertStatus(200);
        $this->assertSame(['1', '2', '3'], collect($response->json('data.rows'))->pluck('id')->map(fn ($v) => (string) $v)->all());
    }

    public function test_query_joins_with_another_owned_dataset(): void
    {
        $user = User::factory()->create();
        $dataset = $this->createMockDataset($user, self::SCHEMA, self::ROWS, 'Cities');
        $managers = $this->createMockDataset($user, ['id', 'manager'], [
            [1, 'Alice'],
            [2, 'Bob'],
            [3, 'Chloe'],
            [4, 'David'],
        ], 'Managers');

        $response = $this->withToken($user->createToken('t')->plainTextToken)->getJson(
            "/api/datasets/{$dataset->id}/query?".http_build_query([
                'joins' => [[
                    'dataset_id' => (string) $managers->id,
                    'left_column' => 'id',
                    'right_column' => 'id',
                    'columns' => ['manager'],
                    'type' => 'left',
                ]],
            ])
        );

        $response->assertStatus(200);
        $paris = collect($response->json('data.rows'))->firstWhere('city', 'Paris');
        $this->assertSame('Alice', $paris['manager']);
    }

    public function test_query_symmetric_sources_join_by_source_id(): void
    {
        $user = User::factory()->create();
        $cities = $this->createMockDataset($user, ['id', 'city', 'region_code'], [
            [1, 'Paris', 'IDF'],
            [2, 'Lyon', 'ARA'],
            [3, 'Berlin', 'XX'],
        ], 'Cities');
        $regions = $this->createMockDataset($user, ['code', 'region_name', 'population'], [
            ['IDF', 'Île-de-France', 12000000],
            ['ARA', 'Auvergne-Rhône-Alpes', 8000000],
        ], 'Regions');

        $response = $this->withToken($user->createToken('t')->plainTextToken)->getJson(
            "/api/datasets/{$cities->id}/query?".http_build_query([
                'sources' => [
                    ['id' => (string) $cities->id, 'dataset_id' => (string) $cities->id],
                    ['id' => (string) $regions->id, 'dataset_id' => (string) $regions->id],
                ],
                'joins' => [[
                    'left_source' => (string) $cities->id, 'left_column' => 'region_code',
                    'right_source' => (string) $regions->id, 'right_column' => 'code',
                    'type' => 'left',
                ]],
                'columns' => ['city', 'region_name@'.$regions->id, 'population@'.$regions->id],
            ])
        );

        $response->assertStatus(200);
        // Colonne non homonyme ⇒ clé de ligne nue, column_map ramène la ref qualifiée.
        $map = $response->json('data.column_map');
        $regionKey = $map['region_name@'.$regions->id];
        $this->assertSame('region_name', $regionKey);
        $paris = collect($response->json('data.rows'))->firstWhere('city', 'Paris');
        $this->assertSame('Île-de-France', $paris[$regionKey]);
        // LEFT join : Berlin sans région correspondante reste présente, colonnes jointes nulles.
        $berlin = collect($response->json('data.rows'))->firstWhere('city', 'Berlin');
        $this->assertNull($berlin[$regionKey]);
    }

    public function test_query_chained_join_c_onto_b(): void
    {
        $user = User::factory()->create();
        $a = $this->createMockDataset($user, ['id', 'label', 'b_key'], [[1, 'Un', 'k1'], [2, 'Deux', 'k2']], 'A');
        $b = $this->createMockDataset($user, ['bk', 'c_key'], [['k1', 'c1'], ['k2', 'c2']], 'B');
        $c = $this->createMockDataset($user, ['ck', 'target'], [['c1', 'Cible 1'], ['c2', 'Cible 2']], 'C');

        $response = $this->withToken($user->createToken('t')->plainTextToken)->getJson(
            "/api/datasets/{$a->id}/query?".http_build_query([
                'sources' => [
                    ['id' => 'a', 'dataset_id' => (string) $a->id],
                    ['id' => 'b', 'dataset_id' => (string) $b->id],
                    ['id' => 'c', 'dataset_id' => (string) $c->id],
                ],
                'joins' => [
                    ['left_source' => 'a', 'left_column' => 'b_key', 'right_source' => 'b', 'right_column' => 'bk', 'type' => 'inner'],
                    ['left_source' => 'b', 'left_column' => 'c_key', 'right_source' => 'c', 'right_column' => 'ck', 'type' => 'inner'],
                ],
                'columns' => ['label', 'target@c'],
            ])
        );

        $response->assertStatus(200);
        $targetKey = $response->json('data.column_map.target@c');
        $rows = collect($response->json('data.rows'))->keyBy('label');
        $this->assertSame('Cible 1', $rows['Un'][$targetKey]);
        $this->assertSame('Cible 2', $rows['Deux'][$targetKey]);
    }

    public function test_query_qualified_columns_alias_on_collision(): void
    {
        $user = User::factory()->create();
        $a = $this->createMockDataset($user, ['id', 'name'], [[1, 'A-un'], [2, 'A-deux']], 'A');
        $b = $this->createMockDataset($user, ['id', 'name'], [[1, 'B-un'], [2, 'B-deux']], 'B');

        $response = $this->withToken($user->createToken('t')->plainTextToken)->getJson(
            "/api/datasets/{$a->id}/query?".http_build_query([
                'sources' => [
                    ['id' => 'a', 'dataset_id' => (string) $a->id],
                    ['id' => 'b', 'dataset_id' => (string) $b->id],
                ],
                'joins' => [['left_source' => 'a', 'left_column' => 'id', 'right_source' => 'b', 'right_column' => 'id', 'type' => 'inner']],
                'columns' => ['name', 'name@b'],
            ])
        );

        $response->assertStatus(200);
        $this->assertSame('name@b', $response->json('data.column_map.name@b'));
        $row = collect($response->json('data.rows'))->firstWhere('name', 'A-un');
        $this->assertSame('B-un', $row['name@b']);
    }

    public function test_query_per_column_aggregates(): void
    {
        $user = User::factory()->create();
        $dataset = $this->createMockDataset($user, ['country', 'sales', 'margin'], [
            ['France', 100, 10],
            ['France', 200, 40],
            ['Germany', 50, 5],
        ], 'Deals');

        $response = $this->withToken($user->createToken('t')->plainTextToken)->getJson(
            "/api/datasets/{$dataset->id}/query?".http_build_query([
                'aggregates' => [
                    ['column' => 'sales', 'fn' => 'sum'],
                    ['column' => 'margin', 'fn' => 'avg'],
                ],
                'group_by' => ['country'],
            ])
        );

        $response->assertStatus(200);
        $rows = collect($response->json('data.rows'))->keyBy('country');
        $this->assertSame(300, (int) $rows['France']['sales']);
        $this->assertSame(25, (int) $rows['France']['margin']);
    }

    public function test_query_filter_on_joined_column(): void
    {
        $user = User::factory()->create();
        $cities = $this->createMockDataset($user, ['id', 'city', 'region_code'], [
            [1, 'Paris', 'IDF'],
            [2, 'Lyon', 'ARA'],
        ], 'Cities');
        $regions = $this->createMockDataset($user, ['code', 'population'], [
            ['IDF', 12000000],
            ['ARA', 8000000],
        ], 'Regions');

        $response = $this->withToken($user->createToken('t')->plainTextToken)->getJson(
            "/api/datasets/{$cities->id}/query?".http_build_query([
                'sources' => [
                    ['id' => 'c', 'dataset_id' => (string) $cities->id],
                    ['id' => 'r', 'dataset_id' => (string) $regions->id],
                ],
                'joins' => [['left_source' => 'c', 'left_column' => 'region_code', 'right_source' => 'r', 'right_column' => 'code', 'type' => 'inner']],
                'filters' => [['column' => 'population@r', 'operator' => '>', 'value' => '10000000']],
                'columns' => ['city'],
            ])
        );

        $response->assertStatus(200);
        $cities = collect($response->json('data.rows'))->pluck('city')->all();
        $this->assertSame(['Paris'], $cities);
    }

    public function test_query_disconnected_join_graph_returns_422(): void
    {
        $user = User::factory()->create();
        $a = $this->createMockDataset($user, ['id'], [[1]], 'A');
        $b = $this->createMockDataset($user, ['id'], [[1]], 'B');

        $this->withToken($user->createToken('t')->plainTextToken)->getJson(
            "/api/datasets/{$a->id}/query?".http_build_query([
                'sources' => [
                    ['id' => 'a', 'dataset_id' => (string) $a->id],
                    ['id' => 'b', 'dataset_id' => (string) $b->id],
                ],
                'joins' => [],
            ])
        )->assertStatus(422)->assertJsonPath('code', 'invalid_query_graph');
    }

    public function test_query_multi_source_rejects_unowned_dataset(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $a = $this->createMockDataset($user, ['id', 'k'], [[1, 'x']], 'A');
        $secret = $this->createMockDataset($other, ['k', 'v'], [['x', 'secret']], 'Secret');

        $this->withToken($user->createToken('t')->plainTextToken)->getJson(
            "/api/datasets/{$a->id}/query?".http_build_query([
                'sources' => [
                    ['id' => 'a', 'dataset_id' => (string) $a->id],
                    ['id' => 's', 'dataset_id' => (string) $secret->id],
                ],
                'joins' => [['left_source' => 'a', 'left_column' => 'k', 'right_source' => 's', 'right_column' => 'k', 'type' => 'inner']],
            ])
        )->assertStatus(422);
    }

    public function test_query_returns_404_for_unknown_dataset(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('t')->plainTextToken)
            ->getJson('/api/datasets/999999/query')
            ->assertStatus(404);
    }

    public function test_query_returns_403_when_not_accessible(): void
    {
        $owner = User::factory()->create();
        $dataset = $this->createMockDataset($owner, self::SCHEMA, self::ROWS);

        $intruder = User::factory()->create();

        $this->withToken($intruder->createToken('t')->plainTextToken)
            ->getJson("/api/datasets/{$dataset->id}/query")
            ->assertStatus(403);
    }

    // ---- update / destroy ----

    public function test_update_changes_name_and_description(): void
    {
        $user = User::factory()->create();
        $dataset = $this->createMockDataset($user, self::SCHEMA, self::ROWS);

        $response = $this->withToken($user->createToken('t')->plainTextToken)
            ->patchJson("/api/datasets/{$dataset->id}", ['name' => 'Nouveau nom']);

        $response->assertStatus(200)->assertJsonPath('data.name', 'Nouveau nom');
    }

    public function test_update_returns_403_for_non_owner(): void
    {
        $owner = User::factory()->create();
        $dataset = $this->createMockDataset($owner, self::SCHEMA, self::ROWS);

        $intruder = User::factory()->create();

        $this->withToken($intruder->createToken('t')->plainTextToken)
            ->patchJson("/api/datasets/{$dataset->id}", ['name' => 'Hack'])
            ->assertStatus(403);
    }

    public function test_destroy_removes_dataset_and_parquet_files_for_owner(): void
    {
        $user = User::factory()->create();
        $dataset = $this->createMockDataset($user, self::SCHEMA, self::ROWS);
        $parquetPath = $dataset->latestVersion->parquet_storage_path;

        $this->withToken($user->createToken('t')->plainTextToken)
            ->deleteJson("/api/datasets/{$dataset->id}")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('datasets', ['id' => $dataset->id]);
        Storage::disk('local')->assertMissing($parquetPath);
    }

    public function test_destroy_detaches_non_owner_attached_source_without_deleting_it(): void
    {
        $owner = User::factory()->create();
        $dataset = $this->createMockDataset($owner, self::SCHEMA, self::ROWS);
        $dataset->dataSource->users()->attach($attachedUser = User::factory()->create()->id);

        $this->withToken(User::find($attachedUser)->createToken('t')->plainTextToken)
            ->deleteJson("/api/datasets/{$dataset->id}")
            ->assertStatus(200);

        $this->assertDatabaseHas('datasets', ['id' => $dataset->id]);
        $this->assertDatabaseMissing('data_source_user', ['data_source_id' => $dataset->data_source_id, 'user_id' => $attachedUser]);
    }

    public function test_destroy_returns_403_for_unrelated_user(): void
    {
        $owner = User::factory()->create();
        $dataset = $this->createMockDataset($owner, self::SCHEMA, self::ROWS);

        $intruder = User::factory()->create();

        $this->withToken($intruder->createToken('t')->plainTextToken)
            ->deleteJson("/api/datasets/{$dataset->id}")
            ->assertStatus(403);
    }
}
