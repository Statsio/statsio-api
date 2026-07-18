<?php

namespace Tests\Feature\Admin;

use App\Models\User\User;
use Database\Factories\TvBroadcastFactory;
use Database\Factories\TvChannelFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminChannelControllerTest extends TestCase
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

    public function test_index_filters_by_active(): void
    {
        TvChannelFactory::new()->create(['is_active' => true]);
        TvChannelFactory::new()->create(['is_active' => false]);

        $response = $this->asAdmin()->getJson('/api/admin/tv/channels?active=0');

        $response->assertStatus(200);
        $this->assertTrue(collect($response->json('data'))->every(fn ($c) => $c['is_active'] === false));
    }

    public function test_index_filters_by_search(): void
    {
        TvChannelFactory::new()->create(['display_name' => 'Zorglub TV', 'slug' => 'zorglubtest']);
        TvChannelFactory::new()->create(['display_name' => 'Autre Chaine', 'slug' => 'autrechainetest']);

        $response = $this->asAdmin()->getJson('/api/admin/tv/channels?search=ZORGLUB');

        $response->assertStatus(200);
        $names = collect($response->json('data'))->pluck('display_name')->all();
        $this->assertSame(['Zorglub TV'], $names);
    }

    public function test_show_returns_broadcasts_count(): void
    {
        $channel = TvChannelFactory::new()->create();
        TvBroadcastFactory::new()->create(['tv_channel_id' => $channel->slug]);

        $this->asAdmin()->getJson("/api/admin/tv/channels/{$channel->id}")
            ->assertStatus(200)
            ->assertJsonPath('broadcasts_count', 1);
    }

    public function test_store_creates_channel(): void
    {
        $response = $this->asAdmin()->postJson('/api/admin/tv/channels', [
            'slug' => 'nouvelle_chaine',
            'number' => 42,
            'display_name' => 'Nouvelle Chaîne',
        ]);

        $response->assertStatus(201)->assertJsonPath('slug', 'nouvelle_chaine');
        $this->assertDatabaseHas('tv_channels', ['slug' => 'nouvelle_chaine']);
    }

    public function test_store_rejects_duplicate_slug(): void
    {
        TvChannelFactory::new()->create(['slug' => 'dup_slug']);

        $this->asAdmin()->postJson('/api/admin/tv/channels', [
            'slug' => 'dup_slug',
            'number' => 43,
            'display_name' => 'Autre',
        ])->assertStatus(422);
    }

    public function test_store_rejects_invalid_slug_format(): void
    {
        $this->asAdmin()->postJson('/api/admin/tv/channels', [
            'slug' => 'Invalid Slug!',
            'number' => 44,
            'display_name' => 'Autre',
        ])->assertStatus(422);
    }

    public function test_update_changes_fields(): void
    {
        $channel = TvChannelFactory::new()->create();

        $this->asAdmin()->patchJson("/api/admin/tv/channels/{$channel->id}", [
            'display_name' => 'Nom mis à jour',
        ])->assertStatus(200)->assertJsonPath('display_name', 'Nom mis à jour');
    }

    public function test_upload_logo_stores_file_and_updates_url(): void
    {
        Storage::fake(config('statsio.media.disk'));
        $channel = TvChannelFactory::new()->create();

        $response = $this->asAdmin()->post("/api/admin/tv/channels/{$channel->id}/logo", [
            'logo' => UploadedFile::fake()->create('logo.png', 10, 'image/png'),
        ]);

        $response->assertStatus(200);
        $this->assertNotNull($response->json('url'));
        $this->assertSame($response->json('url'), $channel->fresh()->logo_url);
    }

    public function test_destroy_blocks_when_broadcasts_exist(): void
    {
        $channel = TvChannelFactory::new()->create();
        TvBroadcastFactory::new()->create(['tv_channel_id' => $channel->slug]);

        $this->asAdmin()->deleteJson("/api/admin/tv/channels/{$channel->id}")->assertStatus(422);
        $this->assertDatabaseHas('tv_channels', ['id' => $channel->id]);
    }

    public function test_destroy_succeeds_when_no_broadcasts(): void
    {
        $channel = TvChannelFactory::new()->create();

        $this->asAdmin()->deleteJson("/api/admin/tv/channels/{$channel->id}")->assertStatus(204);
        $this->assertDatabaseMissing('tv_channels', ['id' => $channel->id]);
    }
}
