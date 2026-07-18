<?php

namespace Tests\Feature\Admin;

use App\Models\DataIngestion\SourceProvenance;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSourceProvenanceControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    private function asAdmin()
    {
        return $this->withToken($this->admin->createToken('test')->plainTextToken);
    }

    public function test_index_returns_provenances_with_data_sources_count(): void
    {
        // Les 8 provenances par défaut sont seedées directement par la migration.
        $response = $this->asAdmin()->getJson('/api/admin/source-provenances');

        $response->assertStatus(200);
        $this->assertCount(8, $response->json());
        $this->assertArrayHasKey('data_sources_count', $response->json()[0]);
    }

    public function test_store_creates_provenance_with_auto_incremented_position(): void
    {
        $response = $this->asAdmin()->postJson('/api/admin/source-provenances', [
            'name' => 'Nouvelle Provenance',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('slug', 'nouvelle-provenance')
            ->assertJsonPath('position', 8);
    }

    public function test_update_reslugifies_on_name_change(): void
    {
        $provenance = SourceProvenance::where('slug', 'insee')->firstOrFail();

        $this->asAdmin()->patchJson("/api/admin/source-provenances/{$provenance->id}", [
            'name' => 'INSEE Renommé',
        ])->assertStatus(200)->assertJsonPath('slug', 'insee-renomme');
    }

    public function test_destroy_blocks_when_data_sources_exist(): void
    {
        $provenance = SourceProvenance::where('slug', 'media')->firstOrFail();
        $user = User::factory()->create();
        \App\Models\DataIngestion\DataSource::create([
            'user_id' => $user->id,
            'name' => 'Source test',
            'type' => 'csv',
            'original_filename' => 'test.csv',
            'raw_storage_path' => 'data-sources/test.csv',
            'file_size_bytes' => 100,
            'provenance_id' => $provenance->id,
        ]);

        $this->asAdmin()->deleteJson("/api/admin/source-provenances/{$provenance->id}")->assertStatus(422);
    }

    public function test_destroy_succeeds_when_unused(): void
    {
        $provenance = SourceProvenance::where('slug', 'academique')->firstOrFail();

        $this->asAdmin()->deleteJson("/api/admin/source-provenances/{$provenance->id}")->assertStatus(204);
        $this->assertDatabaseMissing('source_provenances', ['id' => $provenance->id]);
    }
}
