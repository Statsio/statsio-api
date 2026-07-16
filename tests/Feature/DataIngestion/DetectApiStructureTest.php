<?php

namespace Tests\Feature\DataIngestion;

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DetectApiStructureTest extends TestCase
{
    use RefreshDatabase;

    private function authToken(): string
    {
        $user = User::factory()->create();

        return $user->createToken('test')->plainTextToken;
    }

    /** Fake façon Hub'Eau : `count` diminue quand `code_departement` est présent dans la requête. */
    private function fakeWaterQualityApi(): void
    {
        Http::fake(function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            $count = 100;
            if (isset($query['code_departement'])) {
                $count = 40;
            }

            return Http::response([
                'count' => $count,
                'data' => [
                    ['code_departement' => '75', 'resultat' => '12.5'],
                    ['code_departement' => '77', 'resultat' => '8.2'],
                ],
            ]);
        });
    }

    public function test_full_detection_returns_structure_schema_filters_and_capabilities(): void
    {
        $this->fakeWaterQualityApi();

        $response = $this->withToken($this->authToken())->postJson('/api/source-api/detect-structure', [
            'url' => 'https://example.com/water',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('partial', false)
            ->assertJsonPath('method', 'GET')
            ->assertJsonPath('data_path', 'data');

        $data = $response->json();
        $this->assertSame('code_departement', $data['query_mapping']['filters']['code_departement']['param']);
        $this->assertNotEmpty($data['schema']);
        $this->assertContains('geographic', array_column($data['schema'], 'semantic_role'));
        $this->assertArrayHasKey('compatibility_score', $data['capabilities']);
        $this->assertArrayHasKey('compatible_chart_types', $data['capabilities']);
        $this->assertNotEmpty($data['sample_rows']);
    }

    public function test_no_records_array_found_returns_partial_response_without_calling_prober(): void
    {
        Http::fake(['example.com/*' => Http::response(['status' => 'ok', 'total' => 0])]);

        $response = $this->withToken($this->authToken())->postJson('/api/source-api/detect-structure', [
            'url' => 'https://example.com/status',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('partial', true)
            ->assertJsonPath('reason', 'no_records_array_found');

        $this->assertArrayHasKey('raw_sample', $response->json());
        Http::assertSentCount(1);
    }

    public function test_both_get_and_post_failing_returns_422(): void
    {
        Http::fake(['example.com/*' => Http::response(['error' => 'nope'], 500)]);

        $response = $this->withToken($this->authToken())->postJson('/api/source-api/detect-structure', [
            'url' => 'https://example.com/broken',
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_probe_failure_after_successful_structure_detection_returns_partial_response(): void
    {
        // Pagination "page" confirmée par sondage (contrairement à none/next_link) exige un
        // paramètre de première page non vide (page=1&size=100) — LiveApiSourceProber::probe()
        // doit donc refaire sa propre requête plutôt que réutiliser la page déjà récupérée par
        // ApiStructureDetector::detect(), et c'est CETTE requête qui échoue ici.
        Http::fake(['example.com/*' => Http::sequence()
            ->push(['data' => [['id' => 1]], 'total' => 100]) // detect() : enveloppe + total trouvés
            ->push(['data' => array_fill(0, 5, ['id' => 1]), 'total' => 100]) // confirmation du paramètre size=5
            ->push(['data' => 'not-a-list', 'total' => 100]),  // re-fetch du prober : échoue à extraire les enregistrements
        ]);

        $response = $this->withToken($this->authToken())->postJson('/api/source-api/detect-structure', [
            'url' => 'https://example.com/flaky',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('partial', true)
            ->assertJsonPath('reason', 'probe_failed')
            ->assertJsonPath('data_path', 'data')
            ->assertJsonPath('pagination.style', 'page');
    }

    public function test_prefetched_first_page_is_reused_when_no_pagination_params_are_needed(): void
    {
        // Style de pagination "none" (aucun total détecté) → buildFirstPageQuery() est vide →
        // LiveApiSourceProber::probe() doit réutiliser la page déjà récupérée par
        // ApiStructureDetector::detect() plutôt que de la refetcher — une seule requête HTTP au total.
        Http::fake(['example.com/*' => Http::response(['data' => [['id' => 1], ['id' => 2]]])]);

        $response = $this->withToken($this->authToken())->postJson('/api/source-api/detect-structure', [
            'url' => 'https://example.com/items',
        ]);

        $response->assertStatus(200)->assertJsonPath('success', true)->assertJsonPath('partial', false);
        Http::assertSentCount(1);
    }

    public function test_exhausted_time_budget_truncates_filter_probing_but_still_returns_a_full_response(): void
    {
        config(['statsio.data_ingestion.live_query.detect_time_budget_seconds' => 0]);
        $this->fakeWaterQualityApi();

        $response = $this->withToken($this->authToken())->postJson('/api/source-api/detect-structure', [
            'url' => 'https://example.com/water',
        ]);

        $response->assertStatus(200)->assertJsonPath('success', true)->assertJsonPath('partial', false);
        $this->assertTrue($response->json('query_mapping.probe_truncated'));
        $this->assertSame([], $response->json('query_mapping.filters'));
    }

    public function test_url_is_required(): void
    {
        $response = $this->withToken($this->authToken())->postJson('/api/source-api/detect-structure', []);

        $response->assertStatus(422)->assertJsonValidationErrors('url');
    }
}
