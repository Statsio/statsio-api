<?php

namespace Tests\Feature\Maladies;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MaladiesControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['services.icd11_api.release_id' => '2024-01']);
    }

    /**
     * Les stubs Http::fake() s'accumulent (un appel n'écrase pas le précédent, voir
     * PendingRequest::buildStubHandler qui prend le premier match) : on les enregistre donc
     * explicitement par test plutôt que dans setUp, pour que le test 404 puisse fournir sa
     * propre réponse "id.who.int" sans qu'un stub générique posé plus tôt ne la court-circuite.
     */
    private function fakeSuccessfulIcd11AndWho(): void
    {
        Http::fake([
            'ghoapi.azureedge.net/*' => Http::response(['value' => [
                ['SpatialDim' => 'GLOBAL', 'TimeDim' => 2020, 'NumericValue' => '10', 'Dim1' => 'SEX_BTSX'],
            ]]),
            'icdaccessmanagement.who.int/*' => Http::response(['access_token' => 'test-token']),
            'id.who.int/*/search*' => Http::response(['destinationEntities' => [
                ['id' => 'https://id.who.int/icd/release/11/2024-01/mms/119724091', 'title' => 'Diabète de type 2'],
            ]]),
            'id.who.int/*' => Http::response([
                'code' => '5A11',
                'title' => ['@value' => 'Diabète de type 2'],
            ]),
        ]);
    }

    public function test_search_requires_minimum_two_characters(): void
    {
        $this->getJson('/api/maladies/search?q=a')->assertStatus(422);
    }

    public function test_search_returns_id_and_name_pairs(): void
    {
        $this->fakeSuccessfulIcd11AndWho();

        $response = $this->getJson('/api/maladies/search?q=diabete');

        $response->assertStatus(200);
        $this->assertSame([['id' => '119724091', 'name' => 'Diabète de type 2']], $response->json());
    }

    public function test_populaires_returns_tracked_diseases(): void
    {
        $this->fakeSuccessfulIcd11AndWho();

        $response = $this->getJson('/api/maladies/populaires');

        $response->assertStatus(200);
        $this->assertNotEmpty($response->json());
    }

    public function test_show_returns_404_when_entity_not_found(): void
    {
        Http::fake([
            'icdaccessmanagement.who.int/*' => Http::response(['access_token' => 'test-token']),
            'id.who.int/*' => Http::response([], 404),
        ]);

        $this->getJson('/api/maladies/999999999')->assertStatus(404);
    }

    public function test_show_includes_stats_and_top_countries_when_indicator_tracked(): void
    {
        $this->fakeSuccessfulIcd11AndWho();

        // 119724091 = diabète de type 2, suivi via l'indicateur NCD_DIABETES_PREVALENCE_AGESTD.
        $response = $this->getJson('/api/maladies/119724091');

        $response->assertStatus(200)
            ->assertJsonPath('id', '119724091')
            ->assertJsonPath('indicatorUnit', '% de la population');

        $this->assertNotNull($response->json('stats'));
    }
}
