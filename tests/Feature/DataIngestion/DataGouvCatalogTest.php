<?php

namespace Tests\Feature\DataIngestion;

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DataGouvCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function authToken(): string
    {
        return User::factory()->create()->createToken('test')->plainTextToken;
    }

    public function test_search_requires_authentication(): void
    {
        $this->getJson('/api/data-sources/datagouv/search?q=carburants')->assertStatus(401);
        Http::assertNothingSent();
    }

    public function test_search_validates_query_length(): void
    {
        $this->withToken($this->authToken())
            ->getJson('/api/data-sources/datagouv/search?q=a')
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_search_returns_normalized_datasets(): void
    {
        Http::fake([
            'www.data.gouv.fr/api/1/datasets/*' => Http::response([
                'total' => 1,
                'data' => [[
                    'id' => '662156f3880cee221f36e171',
                    'slug' => 'le-prix-des-carburants',
                    'title' => 'Le prix des carburants',
                    'page' => 'https://www.data.gouv.fr/fr/datasets/le-prix-des-carburants/',
                    'last_update' => '2026-09-01T00:00:00+00:00',
                    'organization' => ['name' => 'Ministère', 'page' => 'https://www.data.gouv.fr/fr/organizations/min/'],
                    'resources' => [['id' => 'a'], ['id' => 'b']],
                ]],
            ]),
        ]);

        $this->withToken($this->authToken())
            ->getJson('/api/data-sources/datagouv/search?q=carburants')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.datasets.0.slug', 'le-prix-des-carburants')
            ->assertJsonPath('data.datasets.0.organization.name', 'Ministère')
            ->assertJsonPath('data.datasets.0.resources_count', 2);
    }

    public function test_dataset_detail_flags_tabular_resources_and_accepts_a_url_ref(): void
    {
        Http::fake([
            'www.data.gouv.fr/api/1/datasets/le-prix-des-carburants/' => Http::response([
                'id' => '662156f3880cee221f36e171',
                'slug' => 'le-prix-des-carburants',
                'title' => 'Le prix des carburants',
                'page' => 'https://www.data.gouv.fr/fr/datasets/le-prix-des-carburants/',
                'resources' => [
                    ['id' => 'd368c882-bb1f-429a-86c1-021e5c01fdf6', 'title' => 'CSV', 'format' => 'csv'],
                    ['id' => 'af899b04-9610-4098-820f-0c8440428562', 'title' => 'JSON', 'format' => 'json'],
                ],
            ]),
            'tabular-api.data.gouv.fr/api/resources/d368c882-bb1f-429a-86c1-021e5c01fdf6/' => Http::response(['ok' => true], 200),
            'tabular-api.data.gouv.fr/api/resources/*' => Http::response([], 404),
        ]);

        $this->withToken($this->authToken())
            ->getJson('/api/data-sources/datagouv/dataset?ref='.urlencode('https://www.data.gouv.fr/fr/datasets/le-prix-des-carburants/#/resources/d368c882-bb1f-429a-86c1-021e5c01fdf6'))
            ->assertOk()
            ->assertJsonPath('data.slug', 'le-prix-des-carburants')
            ->assertJsonPath('data.preselect_resource_id', 'd368c882-bb1f-429a-86c1-021e5c01fdf6')
            ->assertJsonPath('data.resources.0.tabular_available', true)
            ->assertJsonPath('data.resources.0.tabular_url', 'https://tabular-api.data.gouv.fr/api/resources/d368c882-bb1f-429a-86c1-021e5c01fdf6/data/')
            ->assertJsonPath('data.resources.1.tabular_available', false);
    }

    public function test_dataset_detail_returns_404_when_unknown(): void
    {
        Http::fake([
            'www.data.gouv.fr/api/1/datasets/*' => Http::response([], 404),
        ]);

        $this->withToken($this->authToken())
            ->getJson('/api/data-sources/datagouv/dataset?ref=inconnu-xyz')
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }
}
