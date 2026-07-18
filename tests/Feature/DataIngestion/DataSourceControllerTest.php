<?php

namespace Tests\Feature\DataIngestion;

use App\Jobs\ProcessDataSourceJob;
use App\Models\DataIngestion\DataSource;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DataSourceControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createDataSource(User $user, array $overrides = []): DataSource
    {
        return DataSource::create(array_merge([
            'user_id' => $user->id,
            'name' => 'Source test',
            'type' => 'csv',
            'source_kind' => 'upload',
            'materialization' => 'snapshot',
            'original_filename' => 'test.csv',
            'raw_storage_path' => 'data-sources/'.uniqid().'.csv',
            'file_size_bytes' => 100,
            'status' => 'ready',
        ], $overrides));
    }

    public function test_index_lists_own_and_attached_sources(): void
    {
        $user = User::factory()->create();
        $owned = $this->createDataSource($user, ['name' => 'Owned']);

        $other = User::factory()->create();
        $attached = $this->createDataSource($other, ['name' => 'Attached']);
        $attached->users()->attach($user->id);

        $this->createDataSource($other, ['name' => 'Unrelated']);

        $response = $this->withToken($user->createToken('t')->plainTextToken)->getJson('/api/data-sources');

        $response->assertStatus(200)->assertJsonPath('success', true);
        $names = collect($response->json('data'))->pluck('name')->all();
        sort($names);
        $this->assertSame(['Attached', 'Owned'], $names);
    }

    public function test_show_returns_403_when_not_accessible(): void
    {
        $owner = User::factory()->create();
        $dataSource = $this->createDataSource($owner);

        $intruder = User::factory()->create();

        $this->withToken($intruder->createToken('t')->plainTextToken)
            ->getJson("/api/data-sources/{$dataSource->id}")
            ->assertStatus(403);
    }

    public function test_show_returns_data_source_detail_for_owner(): void
    {
        $user = User::factory()->create();
        $dataSource = $this->createDataSource($user);

        $response = $this->withToken($user->createToken('t')->plainTextToken)
            ->getJson("/api/data-sources/{$dataSource->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Source test')
            ->assertJsonPath('data.is_owner', true);
    }

    public function test_update_changes_metadata_only(): void
    {
        $user = User::factory()->create();
        $dataSource = $this->createDataSource($user);

        $response = $this->withToken($user->createToken('t')->plainTextToken)
            ->patchJson("/api/data-sources/{$dataSource->id}", ['name' => 'Nouveau nom', 'visibility' => 'public']);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Nouveau nom')
            ->assertJsonPath('data.visibility', 'public');
    }

    public function test_update_returns_403_for_non_owner(): void
    {
        $owner = User::factory()->create();
        $dataSource = $this->createDataSource($owner);

        $intruder = User::factory()->create();

        $this->withToken($intruder->createToken('t')->plainTextToken)
            ->patchJson("/api/data-sources/{$dataSource->id}", ['name' => 'Hack'])
            ->assertStatus(403);
    }

    public function test_update_with_file_replaces_it_and_dispatches_processing_job(): void
    {
        Queue::fake();
        Storage::fake('local');
        $user = User::factory()->create();
        $dataSource = $this->createDataSource($user);

        $response = $this->withToken($user->createToken('t')->plainTextToken)
            ->post("/api/data-sources/{$dataSource->id}", [
                '_method' => 'PATCH',
                'file' => UploadedFile::fake()->create('nouveau.csv', 10, 'text/csv'),
            ]);

        $response->assertStatus(200)->assertJsonPath('data.status', 'pending');
        $this->assertSame('nouveau.csv', $dataSource->fresh()->original_filename);
        Queue::assertPushed(ProcessDataSourceJob::class);
    }

    public function test_attach_public_adds_source_to_users_account(): void
    {
        $owner = User::factory()->create();
        $dataSource = $this->createDataSource($owner, ['visibility' => 'public']);

        $user = User::factory()->create();

        $response = $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson("/api/data-sources/{$dataSource->id}/attach");

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseHas('data_source_user', ['data_source_id' => $dataSource->id, 'user_id' => $user->id]);
    }

    public function test_attach_public_rejects_private_source(): void
    {
        $owner = User::factory()->create();
        $dataSource = $this->createDataSource($owner, ['visibility' => 'private']);

        $user = User::factory()->create();

        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson("/api/data-sources/{$dataSource->id}/attach")
            ->assertStatus(403);
    }
}
