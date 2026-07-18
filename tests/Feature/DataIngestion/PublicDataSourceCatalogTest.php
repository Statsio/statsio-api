<?php

namespace Tests\Feature\DataIngestion;

use App\Models\DataIngestion\DataSource;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicDataSourceCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function createDataSource(User $user, array $overrides = []): DataSource
    {
        return DataSource::create(array_merge([
            'user_id' => $user->id,
            'name' => 'Source test',
            'type' => 'csv',
            'original_filename' => 'test.csv',
            'raw_storage_path' => 'data-sources/test.csv',
            'file_size_bytes' => 100,
            'visibility' => 'public',
        ], $overrides));
    }

    public function test_public_catalog_only_returns_public_sources(): void
    {
        $user = User::factory()->create();
        $this->createDataSource($user, ['name' => 'Publique']);
        $this->createDataSource($user, ['name' => 'Privee', 'visibility' => 'private']);

        $response = $this->withToken($user->createToken('test')->plainTextToken)
            ->getJson('/api/data-sources/public');

        $response->assertStatus(200)->assertJsonPath('success', true);
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertSame(['Publique'], $names);
    }

    public function test_public_catalog_filters_by_search_query(): void
    {
        $user = User::factory()->create();
        $this->createDataSource($user, ['name' => 'Zorglub Dataset']);
        $this->createDataSource($user, ['name' => 'Autre Source']);

        $response = $this->withToken($user->createToken('test')->plainTextToken)
            ->getJson('/api/data-sources/public?q=ZORGLUB');

        $response->assertStatus(200);
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertSame(['Zorglub Dataset'], $names);
    }

    public function test_public_catalog_requires_authentication(): void
    {
        $this->getJson('/api/data-sources/public')->assertStatus(401);
    }
}
