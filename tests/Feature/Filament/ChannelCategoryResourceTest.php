<?php

namespace Tests\Feature\Filament;

use App\Domain\Content\Enums\SubBrandEnum;
use App\Filament\Resources\ChannelCategories\ChannelCategoryResource;
use App\Filament\Resources\ChannelCategories\Pages\EditChannelCategory;
use App\Models\Channel\ChannelCategory;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ChannelCategoryResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_seeded_channel_categories_default_to_all_and_are_cast(): void
    {
        $category = ChannelCategory::where('slug', 'sport')->firstOrFail();
        $this->assertSame(SubBrandEnum::All, $category->sub_brand);
    }

    public function test_resource_is_edit_only(): void
    {
        $this->assertFalse(ChannelCategoryResource::canCreate());
    }

    public function test_admin_can_scope_a_channel_category_to_a_domain(): void
    {
        $category = ChannelCategory::where('slug', 'actus_medias')->firstOrFail();

        $this->actingAs($this->admin());

        Livewire::test(EditChannelCategory::class, ['record' => $category->getKey()])
            ->assertFormSet(['sub_brand' => SubBrandEnum::All->value])
            ->fillForm(['sub_brand' => SubBrandEnum::Tvstats->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(SubBrandEnum::Tvstats, $category->fresh()->sub_brand);
    }

    public function test_scope_filters_channel_categories_by_domain(): void
    {
        ChannelCategory::where('slug', 'sport')->update(['sub_brand' => SubBrandEnum::Tvstats->value]);

        $tvSlugs = ChannelCategory::forSubBrand(SubBrandEnum::Tvstats)->pluck('slug');
        $this->assertTrue($tvSlugs->contains('sport'));       // propre à tvstats
        $this->assertTrue($tvSlugs->contains('politique'));   // 'all'

        $mediSlugs = ChannelCategory::forSubBrand('medistats')->pluck('slug');
        $this->assertFalse($mediSlugs->contains('sport'));
    }
}
