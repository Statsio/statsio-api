<?php

namespace Tests\Feature\Soins;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SoinsControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Http::fake(['ghoapi.azureedge.net/*' => Http::response(['value' => [
            ['SpatialDim' => 'FRA', 'TimeDim' => 2020, 'NumericValue' => '65', 'Dim1' => 'SEX_BTSX'],
        ]])]);
    }

    public function test_index_returns_default_indicator_grid(): void
    {
        $response = $this->getJson('/api/soins');

        $response->assertStatus(200)
            ->assertJsonPath('indicator.key', 'physicians')
            ->assertJsonPath('indicator.indicatorCode', 'HWF_0001');

        $fra = collect($response->json('countries'))->firstWhere('iso3', 'FRA');
        $this->assertSame(6.5, $fra['value']); // scale 0.1
    }

    public function test_index_accepts_a_supported_indicator(): void
    {
        $this->getJson('/api/soins?indicator=uhcIndex')
            ->assertStatus(200)
            ->assertJsonPath('indicator.key', 'uhcIndex');
    }

    public function test_index_rejects_unknown_indicator(): void
    {
        $this->getJson('/api/soins?indicator=unknown')->assertStatus(422);
    }
}
