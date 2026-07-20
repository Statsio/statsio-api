<?php

namespace Tests\Feature\Medicaments;

use App\Models\Medicaments\MedicamentSalesStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MedicamentsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_search_requires_query_param(): void
    {
        $this->getJson('/api/medicaments/search')->assertStatus(422);
    }

    public function test_search_returns_upstream_results(): void
    {
        Http::fake(['medicaments-api.giygas.dev/*' => Http::response(['results' => [['cis' => 12345678, 'denomination' => 'DOLIPRANE']]])]);

        $response = $this->getJson('/api/medicaments/search?q=doliprane');

        $response->assertStatus(200)->assertJson(['results' => [['cis' => 12345678, 'denomination' => 'DOLIPRANE']]]);
    }

    public function test_search_returns_empty_array_when_upstream_has_no_matches(): void
    {
        Http::fake(['medicaments-api.giygas.dev/*' => Http::response(['code' => 404, 'message' => 'No medicaments found'], 404)]);

        $this->getJson('/api/medicaments/search?q=zzznotfoundzzz')
            ->assertStatus(200)
            ->assertExactJson([]);
    }

    public function test_generiques_requires_libelle_param(): void
    {
        $this->getJson('/api/medicaments/generiques')->assertStatus(422);
    }

    public function test_generiques_returns_results(): void
    {
        Http::fake(['medicaments-api.giygas.dev/*' => Http::response(['results' => []])]);

        $this->getJson('/api/medicaments/generiques?libelle=doliprane')
            ->assertStatus(200)
            ->assertJson(['results' => []]);
    }

    public function test_show_returns_404_for_unknown_cis(): void
    {
        Http::fake(['medicaments-api.giygas.dev/*' => Http::response([], 404)]);

        $this->getJson('/api/medicaments/99999999')->assertStatus(404);
    }

    public function test_show_returns_medicament_when_found(): void
    {
        Http::fake(['medicaments-api.giygas.dev/*' => Http::response(['cis' => 12345678, 'denomination' => 'DOLIPRANE'])]);

        $this->getJson('/api/medicaments/12345678')
            ->assertStatus(200)
            ->assertJson(['cis' => 12345678, 'denomination' => 'DOLIPRANE']);
    }

    public function test_ventes_returns_404_when_medicament_unknown(): void
    {
        Http::fake(['medicaments-api.giygas.dev/*' => Http::response([], 404)]);

        $this->getJson('/api/medicaments/99999999/ventes')->assertStatus(404);
    }

    public function test_ventes_returns_404_when_no_sales_data(): void
    {
        Http::fake(['medicaments-api.giygas.dev/*' => Http::response([
            'cis' => 12345678,
            'presentation' => [['cip13' => 3400930000001]],
        ])]);

        $this->getJson('/api/medicaments/12345678/ventes')->assertStatus(404);
    }

    public function test_ventes_returns_trend_when_sales_data_exists(): void
    {
        Http::fake(['medicaments-api.giygas.dev/*' => Http::response([
            'cis' => 12345678,
            'presentation' => [['cip13' => 3400930000001]],
        ])]);

        MedicamentSalesStat::create([
            'cip13' => '3400930000001', 'year' => 2023, 'label' => 'DOLIPRANE', 'boxes_delivered' => 42,
        ]);

        $this->getJson('/api/medicaments/12345678/ventes')
            ->assertStatus(200)
            ->assertJson(['value' => 42, 'year' => 2023]);
    }

    public function test_top_ventes_returns_ranked_results(): void
    {
        Http::fake(['medicaments-api.giygas.dev/*' => Http::response(['cis' => 12345678, 'cip13' => '3400930000001'])]);

        MedicamentSalesStat::create([
            'cip13' => '3400930000001', 'year' => 2023, 'label' => 'DOLIPRANE', 'boxes_delivered' => 42,
        ]);

        $response = $this->getJson('/api/medicaments/ventes/top')->assertStatus(200);
        $response->assertJsonPath('results.0.cip13', '3400930000001');
        $response->assertJsonPath('results.0.boxes', 42);
        $response->assertJsonPath('results.0.cis', 12345678);
    }

    public function test_image_requires_nom_param(): void
    {
        $this->getJson('/api/medicaments/image')->assertStatus(422);
    }

    public function test_image_returns_url_when_found(): void
    {
        Http::fake(['fr.wikipedia.org/*' => Http::response(['thumbnail' => ['source' => 'https://x/img.jpg']])]);

        $this->getJson('/api/medicaments/image?nom=Doliprane')
            ->assertStatus(200)
            ->assertJson(['url' => 'https://x/img.jpg']);
    }
}
