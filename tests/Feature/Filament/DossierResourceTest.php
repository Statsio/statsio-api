<?php

namespace Tests\Feature\Filament;

use App\Domain\Content\Enums\SubBrandEnum;
use App\Filament\Resources\Dossiers\Pages\EditDossier;
use App\Models\Content\ContentCategory;
use App\Models\Content\Dossier;
use App\Models\User\User;
use Database\Factories\DossierFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DossierResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_sub_brand_defaults_to_all_and_is_cast_to_the_enum(): void
    {
        $default = Dossier::factory()->create();
        $this->assertSame(SubBrandEnum::All, $default->sub_brand);

        $tv = Dossier::factory()->subBrand(SubBrandEnum::Tvstats)->create();
        $this->assertSame(SubBrandEnum::Tvstats, $tv->fresh()->sub_brand);
    }

    public function test_admin_can_change_the_sub_brand_of_a_dossier(): void
    {
        $category = ContentCategory::firstOrCreate(
            ['slug' => 'sante'],
            ['name' => 'Santé', 'position' => 0],
        );
        $dossier = DossierFactory::new()->create(['name' => 'Épidémies']);
        $dossier->contentCategories()->sync([$category->id]);

        $this->actingAs($this->admin());

        Livewire::test(EditDossier::class, ['record' => $dossier->getKey()])
            ->assertFormSet(['sub_brand' => SubBrandEnum::All->value])
            ->fillForm(['sub_brand' => SubBrandEnum::Medistats->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(SubBrandEnum::Medistats, $dossier->fresh()->sub_brand);
    }

    public function test_content_category_scope_filters_by_sub_brand(): void
    {
        $tv = ContentCategory::create(['slug' => 'br-tv', 'name' => 'TV', 'position' => 90, 'sub_brand' => SubBrandEnum::Tvstats]);
        $medi = ContentCategory::create(['slug' => 'br-medi', 'name' => 'Médoc', 'position' => 91, 'sub_brand' => SubBrandEnum::Medistats]);
        $shared = ContentCategory::create(['slug' => 'br-all', 'name' => 'Général', 'position' => 92, 'sub_brand' => SubBrandEnum::All]);

        $tvIds = ContentCategory::forSubBrand(SubBrandEnum::Tvstats)->pluck('id');
        $this->assertTrue($tvIds->contains($tv->id));
        $this->assertTrue($tvIds->contains($shared->id));
        $this->assertFalse($tvIds->contains($medi->id));

        // `all` (ou null) => aucun filtre.
        $this->assertTrue(ContentCategory::forSubBrand(SubBrandEnum::All)->pluck('id')->contains($medi->id));
        $this->assertTrue(ContentCategory::forSubBrand(null)->pluck('id')->contains($medi->id));
    }

    public function test_changing_the_sub_brand_prunes_incompatible_selected_categories(): void
    {
        $tv = ContentCategory::create(['slug' => 'p-tv', 'name' => 'TV', 'position' => 80, 'sub_brand' => SubBrandEnum::Tvstats]);
        $shared = ContentCategory::create(['slug' => 'p-all', 'name' => 'Général', 'position' => 81, 'sub_brand' => SubBrandEnum::All]);

        $dossier = DossierFactory::new()->create(['name' => 'Mixte', 'sub_brand' => SubBrandEnum::All]);
        $dossier->contentCategories()->sync([$tv->id, $shared->id]);

        $this->actingAs($this->admin());

        Livewire::test(EditDossier::class, ['record' => $dossier->getKey()])
            ->fillForm(['sub_brand' => SubBrandEnum::Medistats->value])
            ->assertFormSet(fn (array $state): bool => ! in_array($tv->id, $state['contentCategories'] ?? [], false)
                && in_array($shared->id, array_map('intval', $state['contentCategories'] ?? []), true))
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing([$shared->id], $dossier->fresh()->contentCategories->pluck('id')->all());
    }
}
