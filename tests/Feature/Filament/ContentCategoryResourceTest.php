<?php

namespace Tests\Feature\Filament;

use App\Domain\Content\Enums\SubBrandEnum;
use App\Filament\Resources\ContentCategories\Pages\CreateContentCategory;
use App\Filament\Resources\ContentCategories\Pages\EditContentCategory;
use App\Models\Content\ContentCategory;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContentCategoryResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_sub_brand_defaults_to_all_and_is_cast_to_the_enum(): void
    {
        $category = ContentCategory::create(['slug' => 'demo-brand', 'name' => 'Démo', 'position' => 99]);

        $this->assertSame(SubBrandEnum::All, $category->fresh()->sub_brand);
    }

    public function test_admin_can_create_a_category_scoped_to_a_sub_brand(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateContentCategory::class)
            ->fillForm([
                'name' => 'Audiovisuel',
                'sub_brand' => SubBrandEnum::Tvstats->value,
                'position' => 20,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $category = ContentCategory::where('name', 'Audiovisuel')->firstOrFail();
        $this->assertSame('audiovisuel', $category->slug);
        $this->assertSame(SubBrandEnum::Tvstats, $category->sub_brand);
    }

    public function test_admin_can_change_the_sub_brand_of_a_category(): void
    {
        $category = ContentCategory::create(['slug' => 'sante-brand', 'name' => 'Santé démo', 'position' => 50]);

        $this->actingAs($this->admin());

        Livewire::test(EditContentCategory::class, ['record' => $category->getKey()])
            ->assertFormSet(['sub_brand' => SubBrandEnum::All->value])
            ->fillForm(['sub_brand' => SubBrandEnum::Medistats->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(SubBrandEnum::Medistats, $category->fresh()->sub_brand);
    }
}
