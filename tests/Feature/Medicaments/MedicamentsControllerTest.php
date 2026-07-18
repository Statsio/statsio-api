<?php

namespace Tests\Feature\Medicaments;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MedicamentsControllerTest extends TestCase
{
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
}
