<?php

namespace Tests\Unit\Services\Maladies;

use App\Services\Maladies\UmlsApiClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UmlsApiClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['services.umls_api.key' => 'test-key']);
    }

    public function test_returns_null_when_api_key_not_configured(): void
    {
        config(['services.umls_api.key' => null]);

        $this->assertNull((new UmlsApiClient())->getSymptomsAndRiskFactors('5A11'));
        Http::assertNothingSent();
    }

    public function test_returns_null_when_crosswalk_finds_no_cui(): void
    {
        Http::fake(['uts-ws.nlm.nih.gov/*' => Http::response(['result' => []])]);

        $this->assertNull((new UmlsApiClient())->getSymptomsAndRiskFactors('5A11'));
    }

    public function test_returns_null_when_crosswalk_request_fails(): void
    {
        Http::fake(['uts-ws.nlm.nih.gov/*' => Http::response([], 500)]);

        $this->assertNull((new UmlsApiClient())->getSymptomsAndRiskFactors('5A11'));
    }

    public function test_returns_null_on_connection_exception_during_crosswalk(): void
    {
        Http::fake(['uts-ws.nlm.nih.gov/*' => fn () => throw new ConnectionException('down')]);

        $this->assertNull((new UmlsApiClient())->getSymptomsAndRiskFactors('5A11'));
    }

    public function test_maps_symptom_and_risk_factor_relations_by_label(): void
    {
        Http::fake([
            'uts-ws.nlm.nih.gov/rest/crosswalk/*' => Http::response(['result' => [['ui' => 'C0011860']]]),
            'uts-ws.nlm.nih.gov/rest/content/*' => Http::response(['result' => [
                ['relatedIdName' => 'Polyurie', 'additionalRelationLabel' => 'has_finding', 'rootSource' => 'SNOMEDCT_US'],
                ['relatedIdName' => 'Obésité', 'additionalRelationLabel' => 'has_risk_factor', 'rootSource' => 'MSH'],
                ['relatedIdName' => 'Non pertinent', 'additionalRelationLabel' => 'isa', 'rootSource' => 'MSH'],
            ]]),
        ]);

        $result = (new UmlsApiClient())->getSymptomsAndRiskFactors('5A11');

        $this->assertSame([['label' => 'Polyurie', 'source' => 'UMLS (SNOMEDCT_US)']], $result['symptoms']);
        $this->assertSame([['label' => 'Obésité', 'source' => 'UMLS (MSH)']], $result['riskFactors']);
    }

    public function test_returns_null_when_no_relations_match_known_labels(): void
    {
        Http::fake([
            'uts-ws.nlm.nih.gov/rest/crosswalk/*' => Http::response(['result' => [['ui' => 'C0011860']]]),
            'uts-ws.nlm.nih.gov/rest/content/*' => Http::response(['result' => [
                ['relatedIdName' => 'Non pertinent', 'additionalRelationLabel' => 'isa', 'rootSource' => 'MSH'],
            ]]),
        ]);

        $this->assertNull((new UmlsApiClient())->getSymptomsAndRiskFactors('5A11'));
    }

    public function test_returns_null_when_relations_request_fails(): void
    {
        Http::fake([
            'uts-ws.nlm.nih.gov/rest/crosswalk/*' => Http::response(['result' => [['ui' => 'C0011860']]]),
            'uts-ws.nlm.nih.gov/rest/content/*' => Http::response([], 500),
        ]);

        $this->assertNull((new UmlsApiClient())->getSymptomsAndRiskFactors('5A11'));
    }
}
