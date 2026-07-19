<?php

namespace Tests\Feature\Pays;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaysControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['services.icd11_api.release_id' => '2024-01']);

        Http::fake([
            'ghoapi.azureedge.net/*' => Http::response(['value' => [
                ['SpatialDim' => 'FRA', 'TimeDim' => 2020, 'NumericValue' => '82.5', 'Dim1' => 'SEX_BTSX'],
            ]]),
            'icdaccessmanagement.who.int/*' => Http::response(['access_token' => 'test-token']),
            'id.who.int/*' => Http::response([
                'code' => 'X00',
                'title' => ['@value' => 'Maladie test'],
            ]),
        ]);
    }

    public function test_index_returns_default_indicator_and_country_list(): void
    {
        $response = $this->getJson('/api/pays');

        $response->assertStatus(200)
            ->assertJsonPath('indicator.key', 'lifeExp')
            ->assertJsonPath('indicator.indicatorCode', 'WHOSIS_000001');

        $countries = $response->json('countries');
        $this->assertNotEmpty($countries);
        $fra = collect($countries)->firstWhere('iso3', 'FRA');
        $this->assertSame(82.5, $fra['value']);
    }

    public function test_index_accepts_a_supported_indicator(): void
    {
        $response = $this->getJson('/api/pays?indicator=physicians');

        $response->assertStatus(200)->assertJsonPath('indicator.key', 'physicians');
    }

    public function test_index_rejects_unknown_indicator(): void
    {
        $this->getJson('/api/pays?indicator=unknown')->assertStatus(422);
    }

    public function test_show_returns_country_detail(): void
    {
        $response = $this->getJson('/api/pays/FRA');

        $response->assertStatus(200)
            ->assertJsonPath('iso3', 'FRA')
            ->assertJsonPath('name', 'France');
    }

    public function test_show_returns_404_for_unknown_iso3(): void
    {
        $this->getJson('/api/pays/ZZZ')->assertStatus(404);
    }
}
