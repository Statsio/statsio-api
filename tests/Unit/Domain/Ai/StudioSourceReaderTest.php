<?php

namespace Tests\Unit\Domain\Ai;

use App\Domain\Ai\Support\StudioSourceReader;
use App\Models\DataIngestion\Dataset;
use App\Models\DataIngestion\DatasetColumn;
use App\Models\DataIngestion\DataSource;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudioSourceReaderTest extends TestCase
{
    use RefreshDatabase;

    private function reader(): StudioSourceReader
    {
        return app(StudioSourceReader::class);
    }

    private function makeDataset(User $owner, string $name, string $visibility = 'private'): Dataset
    {
        $source = DataSource::create([
            'user_id' => $owner->id,
            'name' => $name,
            'type' => 'csv',
            'source_kind' => 'upload',
            'materialization' => 'snapshot',
            'original_filename' => 'test.csv',
            'raw_storage_path' => 'data-sources/'.uniqid().'.csv',
            'file_size_bytes' => 100,
            'status' => 'ready',
            'visibility' => $visibility,
            'categories' => ['economie'],
        ]);

        $dataset = Dataset::create([
            'data_source_id' => $source->id,
            'user_id' => $owner->id,
            'name' => $name,
            'row_count' => 120,
            'status' => 'ready',
        ]);

        DatasetColumn::create(['dataset_id' => $dataset->id, 'name' => 'date_mesure', 'type' => 'date', 'column_order' => 0, 'sample_values' => ['2019-01-01', '2020-01-01', '2021-01-01']]);
        DatasetColumn::create(['dataset_id' => $dataset->id, 'name' => 'region', 'type' => 'string', 'column_order' => 1, 'sample_values' => ['Bretagne', 'Normandie', 'Bretagne', 'Normandie']]);
        DatasetColumn::create(['dataset_id' => $dataset->id, 'name' => 'population', 'type' => 'integer', 'column_order' => 2, 'sample_values' => [3000, 3300, 3100, 3300]]);

        return $dataset;
    }

    public function test_accessible_datasets_returns_owned_ready_datasets_with_roles(): void
    {
        $user = User::factory()->create();
        $this->makeDataset($user, 'Population régionale');
        $this->makeDataset(User::factory()->create(), 'Autre source privée'); // pas accessible

        $result = $this->reader()->accessibleDatasets($user);

        $this->assertCount(1, $result);
        $this->assertSame('Population régionale', $result[0]['name']);

        $roles = collect($result[0]['columns'])->pluck('role', 'name');
        $this->assertSame('temporal', $roles['date_mesure']);
        $this->assertSame('measure', $roles['population']);
        $this->assertContains($roles['region'], ['dimension', 'geographic']);
    }

    public function test_accessible_datasets_includes_attached_public_source(): void
    {
        $user = User::factory()->create();
        $publicDataset = $this->makeDataset(User::factory()->create(), 'INSEE emploi', 'public');
        $publicDataset->dataSource->users()->syncWithoutDetaching([$user->id]);

        $names = collect($this->reader()->accessibleDatasets($user))->pluck('name');

        $this->assertTrue($names->contains('INSEE emploi'));
    }

    public function test_public_catalog_filters_and_flags_attachment(): void
    {
        $user = User::factory()->create();
        $this->makeDataset(User::factory()->create(), 'Chômage par département', 'public');
        $this->makeDataset(User::factory()->create(), 'Prix immobilier', 'public');

        $result = $this->reader()->publicCatalog($user, 'chômage');

        $this->assertCount(1, $result);
        $this->assertSame('Chômage par département', $result[0]['name']);
        $this->assertFalse($result[0]['already_attached']);
    }

    public function test_dataset_schema_returns_null_when_not_accessible(): void
    {
        $user = User::factory()->create();
        $foreign = $this->makeDataset(User::factory()->create(), 'Privée');

        $this->assertNull($this->reader()->datasetSchema($user, $foreign->id));
    }

    public function test_dataset_schema_includes_samples(): void
    {
        $user = User::factory()->create();
        $dataset = $this->makeDataset($user, 'Ma source');

        $schema = $this->reader()->datasetSchema($user, $dataset->id);

        $this->assertSame('Ma source', $schema['name']);
        $this->assertSame('date_mesure', $schema['columns'][0]['name']);
        $this->assertSame(['2019-01-01', '2020-01-01', '2021-01-01'], $schema['columns'][0]['samples']);
    }
}
