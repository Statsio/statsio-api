<?php

namespace Tests\Feature\DataIngestion;

use App\Jobs\ProcessDataSourceJob;
use App\Models\DataIngestion\DataSource;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CreateLiveApiDataSourceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Fake d'une API façon Hub'Eau : `count` diminue quand `code_departement`
     * ou `date_min_prelevement` sont présents dans la requête — de quoi
     * exercer FilterCapabilityProbe sans dépendre du réseau.
     */
    private function fakeWaterQualityApi(): void
    {
        Http::fake(function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            $count = 100;
            if (isset($query['code_departement'])) {
                $count = 40;
            }
            if (isset($query['date_min_prelevement'])) {
                $count = 10;
            }

            return Http::response([
                'count' => $count,
                'data' => [
                    ['code_departement' => '75', 'date_prelevement' => '2024-01-15T00:00:00Z'],
                    ['code_departement' => '77', 'date_prelevement' => '2024-02-20T00:00:00Z'],
                ],
            ]);
        });
    }

    public function test_creates_live_source_synchronously_without_dispatching_a_job(): void
    {
        Queue::fake();
        $this->fakeWaterQualityApi();

        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/api-sources', [
            'name' => "Hub'eau qualité eau potable",
            'url' => 'https://example.com/water',
            'method' => 'GET',
            'data_path' => 'data',
            'materialization' => 'live',
            'pagination' => ['style' => 'page', 'param_name' => 'page', 'param_start' => 1, 'size_param' => 'size', 'page_size' => 20],
        ]);

        $response->assertStatus(201)->assertJsonPath('success', true);

        Queue::assertNotPushed(ProcessDataSourceJob::class);

        $dataSource = DataSource::first();
        $this->assertSame('live', $dataSource->materialization->value);
        $this->assertSame('ready', $dataSource->status->value);
        $this->assertNull($dataSource->raw_storage_path);
        $this->assertSame('none', $dataSource->refresh_frequency->value);

        $mapping = $dataSource->api_config['query_mapping'];
        $this->assertSame('count', $mapping['count_path']);
        $this->assertSame('code_departement', $mapping['filters']['code_departement']['param']);
        $this->assertContains('eq', $mapping['filters']['code_departement']['operators']);
        $this->assertSame(
            ['gte_param' => 'date_min_prelevement', 'lte_param' => 'date_max_prelevement'],
            $mapping['filters']['date_prelevement']['range'],
        );

        $dataset = $dataSource->dataset;
        $this->assertNotNull($dataset);
        $this->assertSame('ready', $dataset->status->value);
        $this->assertNull($dataset->parquet_path);
        $this->assertSame(100, $dataset->row_count);
        $this->assertCount(2, $dataset->columns);
        $this->assertDatabaseCount('dataset_versions', 0);
    }

    public function test_manual_query_mapping_overrides_take_priority_over_detection(): void
    {
        Queue::fake();
        $this->fakeWaterQualityApi();

        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/api-sources', [
            'name' => "Hub'eau qualité eau potable",
            'url' => 'https://example.com/water',
            'method' => 'GET',
            'data_path' => 'data',
            'materialization' => 'live',
            'pagination' => ['style' => 'page', 'param_name' => 'page', 'param_start' => 1, 'size_param' => 'size', 'page_size' => 20],
            'query_mapping' => [
                'filters' => [
                    'code_departement' => ['param' => 'code_departement', 'operators' => ['eq']],
                    'code_commune' => ['param' => 'code_commune', 'operators' => ['eq']],
                ],
            ],
        ]);

        $response->assertStatus(201);

        $mapping = DataSource::first()->api_config['query_mapping'];
        // La correction manuelle ajoute code_commune, en plus de ce que la détection auto a trouvé.
        $this->assertArrayHasKey('code_commune', $mapping['filters']);
        $this->assertArrayHasKey('code_departement', $mapping['filters']);
    }
}
