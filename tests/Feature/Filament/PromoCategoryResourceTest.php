<?php

namespace Tests\Feature\Filament;

use App\Models\Marketing\PromoCategory;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromoCategoryResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_the_list_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/promo-categories')
            ->assertOk();
    }

    public function test_admin_can_view_the_create_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/promo-categories/create')
            ->assertOk();
    }

    public function test_admin_can_view_the_edit_page(): void
    {
        $admin = User::factory()->admin()->create();
        $category = PromoCategory::create([
            'name' => 'Test',
            'title_line_1' => '<p><strong>Titre</strong></p>',
            'infos' => [
                ['title' => 'Info', 'description' => 'Description', 'cta_label' => 'Voir', 'cta_link' => 'https://statsio.fr'],
            ],
        ]);

        $this->actingAs($admin)
            ->get("/admin/promo-categories/{$category->id}/edit")
            ->assertOk();
    }
}
