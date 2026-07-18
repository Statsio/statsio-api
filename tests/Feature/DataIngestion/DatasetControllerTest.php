<?php

namespace Tests\Feature\DataIngestion;

use App\Models\DataIngestion\DataSource;
use App\Models\DataIngestion\Dataset;
use App\Models\DataIngestion\DatasetColumn;
use App\Models\DataIngestion\DatasetVersion;
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
