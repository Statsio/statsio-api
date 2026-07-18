<?php

namespace Tests\Unit\Services\Maladies;

use App\Services\Maladies\Icd11ApiClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Icd11ApiClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['services.icd11_api.release_id' => '2024-01']);
        config(['services.icd11_api.client_id' => 'client-id']);
        config(['services.icd11_api.client_secret' => 'client-secret']);
    }

    public function test_search_extracts_id_strips_html_and_filters_post_coordinated_entities(): void
    {
        Http::fake([
            'icdaccessmanagement.who.int/*' => Http::response(['access_token' => 'test-token']),
            'id.who.int/*' => Http::response(['destinationEntities' => [
                ['id' => 'https://id.who.int/icd/release/11/2024-01/mms/119724091', 'title' => '<em>Diabète</em> de type 2'],
                ['id' => 'https://id.who.int/icd/release/11/2024-01/mms/1217915084/unspecified', 'title' => 'Post-coordonnée'],
                ['id' => ''],
            ]]),
        ]);

        $result = (new Icd11ApiClient())->search('diabete');

        $this->assertSame([['id' => '119724091', 'title' => 'Diabète de type 2']], $result);
    }

    public function test_get_by_id_returns_null_on_404(): void
    {
        Http::fake([
            'icdaccessmanagement.who.int/*' => Http::response(['access_token' => 'test-token']),
            'id.who.int/*' => Http::response([], 404),
        ]);

        $this->assertNull((new Icd11ApiClient())->getById('999999999'));
    }

    public function test_get_by_id_maps_all_fields_including_synonyms_and_inclusions(): void
    {
        Http::fake([
            'icdaccessmanagement.who.int/*' => Http::response(['access_token' => 'test-token']),
            'id.who.int/*' => Http::response([
                'code' => '5A11',
                'title' => ['@value' => 'Diabète de type 2'],
                'definition' => ['@value' => 'Une maladie métabolique.'],
                'indexTerm' => [['label' => ['@value' => 'DT2']]],
                'inclusion' => [['label' => ['@value' => 'Diabète non insulino-dépendant']]],
                'classKind' => 'category',
                'parent' => ['https://id.who.int/icd/release/11/2024-01/mms/123'],
                'child' => ['https://id.who.int/icd/release/11/2024-01/mms/456'],
            ]),
        ]);

        $result = (new Icd11ApiClient())->getById('119724091');

        $this->assertSame('119724091', $result['id']);
        $this->assertSame('5A11', $result['code']);
        $this->assertSame('Diabète de type 2', $result['title']);
        $this->assertSame('Une maladie métabolique.', $result['definition']);
        $this->assertSame(['DT2'], $result['synonyms']);
        $this->assertSame(['Diabète non insulino-dépendant'], $result['inclusions']);
        $this->assertSame('category', $result['classKind']);
        $this->assertSame(['123'], $result['parentIds']);
        $this->assertSame(['456'], $result['childIds']);
    }

    public function test_get_title_returns_null_when_entity_missing(): void
    {
        Http::fake([
            'icdaccessmanagement.who.int/*' => Http::response(['access_token' => 'test-token']),
            'id.who.int/*' => Http::response([], 404),
        ]);

        $this->assertNull((new Icd11ApiClient())->getTitle('999999999'));
    }

    public function test_current_release_id_uses_configured_release_when_not_latest(): void
    {
        Http::fake([
            'id.who.int/icd/entity' => Http::response(['releaseId' => 'should-not-be-used']),
        ]);

        $this->assertSame('2024-01', (new Icd11ApiClient())->currentReleaseId());
        Http::assertNothingSent();
    }

    public function test_release_id_resolves_via_api_when_configured_as_latest(): void
    {
        config(['services.icd11_api.release_id' => 'latest']);
        Http::fake([
            'icdaccessmanagement.who.int/*' => Http::response(['access_token' => 'test-token']),
            'id.who.int/icd/entity' => Http::response(['releaseId' => '2023-05']),
        ]);

        $this->assertSame('2023-05', (new Icd11ApiClient())->currentReleaseId());
    }

    public function test_token_is_cached_across_calls(): void
    {
        Http::fake([
            'icdaccessmanagement.who.int/*' => Http::response(['access_token' => 'test-token']),
            'id.who.int/*' => Http::response([], 404),
        ]);

        $client = new Icd11ApiClient();
        $client->getById('111');
        $client->getById('222');

        Http::assertSentCount(3); // 1 token + 2 entity lookups (no release lookup, release_id is configured)
    }
}
