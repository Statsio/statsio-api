<?php

namespace Tests\Feature\DataIngestion;

use App\Jobs\RefreshApiDataSourceJob;
use App\Models\DataIngestion\DataSource;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RefreshDueApiDataSourcesTest extends TestCase
{
    use RefreshDatabase;

    private function apiSource(array $overrides = []): DataSource
    {
        return DataSource::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'name' => 'Ressource data.gouv.fr',
            'type' => 'json',
            'source_kind' => 'api',
            'materialization' => 'snapshot',
            'api_config' => ['url' => 'https://example.com/data/'],
            'original_filename' => 'Ressource data.gouv.fr.json',
            'raw_storage_path' => null,
            'file_size_bytes' => 0,
            'status' => 'ready',
        ], $overrides));
    }

    public function test_dispatches_a_job_for_each_due_scheduled_source(): void
    {
        Queue::fake();

        $due = $this->apiSource([
            'refresh_frequency' => 'hourly',
            'next_refresh_at' => now()->subMinutes(5),
        ]);
        // Planifiée mais pas encore échue.
        $this->apiSource([
            'refresh_frequency' => 'daily',
            'next_refresh_at' => now()->addHours(3),
        ]);
        // Jamais : jamais resynchronisée.
        $this->apiSource([
            'refresh_frequency' => 'none',
            'next_refresh_at' => null,
        ]);

        $this->artisan('data-sources:refresh-due')->assertSuccessful();

        Queue::assertPushed(RefreshApiDataSourceJob::class, 1);
        Queue::assertPushed(
            RefreshApiDataSourceJob::class,
            fn (RefreshApiDataSourceJob $job) => $job->dataSource->is($due),
        );
    }

    public function test_ignores_upload_sources_even_when_due(): void
    {
        Queue::fake();

        DataSource::create([
            'user_id' => User::factory()->create()->id,
            'name' => 'Fichier importé',
            'type' => 'csv',
            'source_kind' => 'upload',
            'materialization' => 'snapshot',
            'original_filename' => 'x.csv',
            'raw_storage_path' => 'data-sources/x.csv',
            'file_size_bytes' => 100,
            'refresh_frequency' => 'hourly',
            'next_refresh_at' => now()->subDay(),
            'status' => 'ready',
        ]);

        $this->artisan('data-sources:refresh-due')->assertSuccessful();

        Queue::assertNothingPushed();
    }
}
