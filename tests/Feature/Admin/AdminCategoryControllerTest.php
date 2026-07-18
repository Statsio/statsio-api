<?php

namespace Tests\Feature\Admin;

use App\Models\Tv\TvCategory;
use App\Models\User\User;
use Database\Factories\TvProgramFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCategoryControllerTest extends TestCase
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

    public function test_index_returns_categories_with_programs_count(): void
    {
        // Les 14 catégories par défaut sont seedées directement par la migration.
        $response = $this->asAdmin()->getJson('/api/admin/tv/categories');

        $response->assertStatus(200);
        $this->assertCount(14, $response->json());
        $this->assertArrayHasKey('programs_count', $response->json()[0]);
    }

    public function test_store_creates_category_and_slugifies_name(): void
    {
        $response = $this->asAdmin()->postJson('/api/admin/tv/categories', [
            'name' => 'Actualités Locales',
        ]);

        $response->assertStatus(201)->assertJsonPath('slug', 'actualites-locales');
    }

    public function test_update_changes_name_and_reslugifies(): void
    {
        $category = TvCategory::where('slug', 'fiction')->firstOrFail();

        $this->asAdmin()->patchJson("/api/admin/tv/categories/{$category->id}", [
            'name' => 'Fiction Premium',
        ])->assertStatus(200)->assertJsonPath('slug', 'fiction-premium');
    }

    public function test_destroy_blocks_when_programs_use_category(): void
    {
        $category = TvCategory::where('slug', 'sport')->firstOrFail();
        $program = TvProgramFactory::new()->create();
        $program->categories()->attach($category->id);

        $this->asAdmin()->deleteJson("/api/admin/tv/categories/{$category->id}")->assertStatus(422);
    }

    public function test_destroy_succeeds_when_unused(): void
    {
        $category = TvCategory::where('slug', 'musique')->firstOrFail();

        $this->asAdmin()->deleteJson("/api/admin/tv/categories/{$category->id}")->assertStatus(204);
        $this->assertDatabaseMissing('tv_categories', ['id' => $category->id]);
    }
}
