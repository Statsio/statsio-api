<?php

namespace Tests\Feature\DataIngestion;

use App\Jobs\RefreshLiveAggregateJob;
use App\Models\DataIngestion\DataSource;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LiveDatasetQueryTest extends TestCase
{
    use RefreshDatabase;

    private function fakeWaterQualityApi(): void
    {
        Http::fake(function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            $matchesParis = (isset($query['code_departement']) && $query['code_departement'] === '75')
                || (isset($query['nom_commune']) && $query['nom_commune'] === 'Paris');

            if ($matchesParis) {
                return Http::response([
                    'count' => 1,
                    'data' => [
                        ['code_departement' => '75', 'nom_commune' => 'Paris', 'date_prelevement' => '2024-01-15T00:00:00Z'],
                    ],
                ]);
            }

            if (isset($query['date_min_prelevement'])) {
                return Http::response([
                    'count' => 10,
                    'data' => [
                        ['code_departement' => '75', 'nom_commune' => 'Paris', 'date_prelevement' => '2024-01-15T00:00:00Z'],
                        ['code_departement' => '77', 'nom_commune' => 'Melun', 'date_prelevement' => '2024-02-20T00:00:00Z'],
                    ],
                ]);
            }

            if (isset($query['code_departement']) || isset($query['nom_commune'])) {
                // Valeur de filtre qui ne correspond à aucune ligne connue de ce fixture.
                return Http::response(['count' => 0, 'data' => []]);
            }

            return Http::response([
                'count' => 100,
                'data' => [
                    ['code_departement' => '75', 'nom_commune' => 'Paris', 'date_prelevement' => '2024-01-15T00:00:00Z'],
                    ['code_departement' => '77', 'nom_commune' => 'Melun', 'date_prelevement' => '2024-02-20T00:00:00Z'],
                ],
            ]);
        });
    }

    private function createLiveDataSource(User $user, string $token): int
    {
        $response = $this->withToken($token)->postJson('/api/api-sources', [
            'name' => "Hub'eau qualité eau potable",
            'url' => 'https://example.com/water',
            'method' => 'GET',
            'data_path' => 'data',
            'materialization' => 'live',
            'pagination' => ['style' => 'page', 'param_name' => 'page', 'param_start' => 1, 'size_param' => 'size', 'page_size' => 20],
        ]);

        return DataSource::first()->dataset->id;
    }

    public function test_query_with_mapped_filter_hits_upstream_and_returns_matching_total(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->fakeWaterQualityApi();
        $datasetId = $this->createLiveDataSource($user, $token);

        $response = $this->withToken($token)->getJson(
            "/api/datasets/{$datasetId}/query?filters[0][column]=code_departement&filters[0][operator]==&filters[0][value]=75"
        );

        $response->assertStatus(200);
        $response->assertJsonPath('data.total_rows', 1);
        $response->assertJsonPath('data.rows.0.code_departement', '75');

        $this->assertDatabaseCount('dataset_versions', 0);
    }

    public function test_unmapped_filter_column_is_rejected_with_422(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->fakeWaterQualityApi();
        $datasetId = $this->createLiveDataSource($user, $token);

        // date_prelevement n'est mappée qu'en plage (gte/lte) — un filtre d'égalité dessus reste rejeté.
        $response = $this->withToken($token)->getJson(
            "/api/datasets/{$datasetId}/query?filters[0][column]=date_prelevement&filters[0][operator]==&filters[0][value]=2024-01-15"
        );

        $response->assertStatus(422)->assertJsonPath('code', 'unsupported_live_operation');
    }

    public function test_search_across_mapped_columns_hits_upstream_and_merges_results(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->fakeWaterQualityApi();
        $datasetId = $this->createLiveDataSource($user, $token);

        // Reproduit le bloc de recherche Studio : plusieurs search_columns + search_q,
        // comme envoyé par SearchBlock.vue (search_columns[]=nom_commune&search_columns[]=code_departement).
        $response = $this->withToken($token)->getJson(
            "/api/datasets/{$datasetId}/query?search_columns[0]=nom_commune&search_columns[1]=code_departement&search_q=Paris"
        );

        $response->assertStatus(200);
        $response->assertJsonPath('data.total_rows', 1);
        $response->assertJsonPath('data.rows.0.nom_commune', 'Paris');
    }

    public function test_search_on_a_column_without_a_mapped_param_is_rejected_with_422(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->fakeWaterQualityApi();
        $datasetId = $this->createLiveDataSource($user, $token);

        $response = $this->withToken($token)->getJson(
            "/api/datasets/{$datasetId}/query?search_columns[0]=date_prelevement&search_q=2024"
        );

        $response->assertStatus(422)->assertJsonPath('code', 'unsupported_live_operation');
    }

    public function test_join_on_a_live_dataset_query_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->fakeWaterQualityApi();
        $datasetId = $this->createLiveDataSource($user, $token);

        $response = $this->withToken($token)->getJson(
            "/api/datasets/{$datasetId}/query?joins[0][dataset_id]=999&joins[0][left_column]=a&joins[0][right_column]=b"
        );

        $response->assertStatus(422)->assertJsonPath('code', 'unsupported_live_operation');
    }

    public function test_aggregate_without_group_by_streams_all_pages_and_computes_a_real_average(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        Http::fake(function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $page = (int) ($query['page'] ?? 1);

            $rows = $page === 1
                ? array_fill(0, 20, ['resultat_numerique' => 10, 'code_departement' => '75'])
                // Page plus courte que page_size (20) => signal de fin de pagination.
                : array_fill(0, 5, ['resultat_numerique' => 20, 'code_departement' => '75']);

            return Http::response(['count' => 25, 'data' => $rows]);
        });

        // query_mapping fourni explicitement plutôt que détecté automatiquement (l'heuristique
        // de FilterCapabilityProbe compare des `count` qui varient selon le filtre — hors sujet
        // ici, on veut juste vérifier que le filtre reste appliqué sur CHAQUE page pendant le
        // streaming de l'agrégation).
        $this->withToken($token)->postJson('/api/api-sources', [
            'name' => 'KPI aggregate test',
            'url' => 'https://example.com/water',
            'method' => 'GET',
            'data_path' => 'data',
            'materialization' => 'live',
            'pagination' => ['style' => 'page', 'param_name' => 'page', 'param_start' => 1, 'size_param' => 'size', 'page_size' => 20],
            'query_mapping' => ['filters' => ['code_departement' => ['param' => 'code_departement', 'operators' => ['eq']]]],
        ]);
        $datasetId = DataSource::first()->dataset->id;

        $response = $this->withToken($token)->getJson(
            "/api/datasets/{$datasetId}/query?aggregate=avg&aggregate_columns[0]=resultat_numerique&filters[0][column]=code_departement&filters[0][operator]==&filters[0][value]=75"
        );

        $response->assertStatus(200);
        // (20*10 + 5*20) / 25 = 12
        $response->assertJsonPath('data.rows.0.resultat_numerique', 12);

        Http::assertSent(function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return ($query['page'] ?? null) === '2' && ($query['code_departement'] ?? null) === '75';
        });
    }

    public function test_stale_aggregate_is_served_instantly_and_refreshed_in_background(): void
    {
        config(['statsio.data_ingestion.live_query.aggregate_cache_ttl_seconds' => 5]);

        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        Http::fake(function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $page = (int) ($query['page'] ?? 1);

            $rows = $page === 1
                ? array_fill(0, 20, ['resultat_numerique' => 10])
                : array_fill(0, 5, ['resultat_numerique' => 20]);

            return Http::response(['count' => 25, 'data' => $rows]);
        });

        $this->withToken($token)->postJson('/api/api-sources', [
            'name' => 'Stale aggregate test',
            'url' => 'https://example.com/water',
            'method' => 'GET',
            'data_path' => 'data',
            'materialization' => 'live',
            'pagination' => ['style' => 'page', 'param_name' => 'page', 'param_start' => 1, 'size_param' => 'size', 'page_size' => 20],
        ]);
        $datasetId = DataSource::first()->dataset->id;

        $queryUrl = "/api/datasets/{$datasetId}/query?aggregate=avg&aggregate_columns[0]=resultat_numerique";

        // Premier appel : aucune valeur connue, calcul synchrone.
        $first = $this->withToken($token)->getJson($queryUrl);
        $first->assertStatus(200);
        $first->assertJsonPath('data.rows.0.resultat_numerique', 12);

        // La fenêtre de fraîcheur (5s) expire — la valeur reste en cache indéfiniment (stale-while-revalidate).
        $this->travel(10)->seconds();

        Queue::fake();
        $second = $this->withToken($token)->getJson($queryUrl);
        $second->assertStatus(200);
        // Même valeur immédiatement servie depuis le cache, pas de recalcul synchrone.
        $second->assertJsonPath('data.rows.0.resultat_numerique', 12);

        Queue::assertPushed(RefreshLiveAggregateJob::class);
    }

    public function test_aggregate_with_group_by_is_still_rejected(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->fakeWaterQualityApi();
        $datasetId = $this->createLiveDataSource($user, $token);

        $response = $this->withToken($token)->getJson(
            "/api/datasets/{$datasetId}/query?aggregate=avg&aggregate_columns[0]=resultat_numerique&group_by[0]=code_departement"
        );

        $response->assertStatus(422)->assertJsonPath('code', 'unsupported_live_operation');
    }

    public function test_preview_returns_live_rows(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->fakeWaterQualityApi();
        $datasetId = $this->createLiveDataSource($user, $token);

        $response = $this->withToken($token)->getJson("/api/datasets/{$datasetId}/preview?limit=5");

        $response->assertStatus(200);
        $response->assertJsonPath('data.total', 100);
        $this->assertCount(2, $response->json('data.rows'));
    }
}
