<?php

namespace Tests\Unit\Services\Medicaments;

use App\Services\Medicaments\GiygasApiClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GiygasApiClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_search_sanitizes_accented_characters_before_calling_api(): void
    {
        Http::fake(['medicaments-api.giygas.dev/*' => Http::response(['results' => []])]);

        (new GiygasApiClient())->search('médicament énergisant');

        Http::assertSent(fn ($request) => $request['search'] === 'medicament energisant');
    }

    public function test_generiques_returns_upstream_payload(): void
    {
        Http::fake(['medicaments-api.giygas.dev/*' => Http::response(['results' => ['doliprane']])]);

        $result = (new GiygasApiClient())->generiques('doliprane');

        $this->assertSame(['results' => ['doliprane']], $result);
    }

    /**
     * L'API giygas renvoie 404 "No medicaments found" (pas un tableau vide) quand une recherche
     * ne matche rien — doit être traité comme "aucun résultat", pas comme une erreur, sinon
     * chaque recherche sans résultat casse la page de recherche côté frontend.
     */
    public function test_search_returns_empty_array_on_upstream_404(): void
    {
        Http::fake(['medicaments-api.giygas.dev/*' => Http::response(['code' => 404, 'message' => 'No medicaments found'], 404)]);

        $this->assertSame([], (new GiygasApiClient())->search('zzznotfoundzzz'));
    }

    public function test_generiques_returns_empty_array_on_upstream_404(): void
    {
        Http::fake(['medicaments-api.giygas.dev/*' => Http::response(['code' => 404, 'message' => 'No generiques found'], 404)]);

        $this->assertSame([], (new GiygasApiClient())->generiques('zzznotfoundzzz'));
    }

    public function test_get_by_cis_returns_null_on_404(): void
    {
        Http::fake(['medicaments-api.giygas.dev/*' => Http::response([], 404)]);

        $this->assertNull((new GiygasApiClient())->getByCis(12345678));
    }

    public function test_get_by_cis_returns_payload_when_found(): void
    {
        Http::fake(['medicaments-api.giygas.dev/*' => Http::response(['cis' => 12345678, 'denomination' => 'DOLIPRANE'])]);

        $result = (new GiygasApiClient())->getByCis(12345678);

        $this->assertSame(['cis' => 12345678, 'denomination' => 'DOLIPRANE'], $result);
    }

    public function test_get_by_cip_returns_null_on_404(): void
    {
        Http::fake(['medicaments-api.giygas.dev/*' => Http::response([], 404)]);

        $this->assertNull((new GiygasApiClient())->getByCip('3400930000001'));
    }

    public function test_get_by_cip_returns_payload_when_found(): void
    {
        Http::fake(['medicaments-api.giygas.dev/*' => Http::response(['cis' => 12345678, 'cip13' => '3400930000001'])]);

        $result = (new GiygasApiClient())->getByCip('3400930000001');

        $this->assertSame(['cis' => 12345678, 'cip13' => '3400930000001'], $result);
    }
}
