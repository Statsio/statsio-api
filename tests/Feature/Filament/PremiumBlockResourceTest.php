<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\PremiumBlocks\Pages\CreatePremiumBlock;
use App\Filament\Resources\PremiumBlocks\Pages\ListPremiumBlocks;
use App\Models\PremiumBlockType;
use App\Models\User\User;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PremiumBlockResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_admin_can_flag_a_block_as_premium(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreatePremiumBlock::class)
            ->fillForm(['block_type' => 'map'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('premium_block_types', ['block_type' => 'map']);
    }

    public function test_flagging_the_same_block_twice_fails_validation(): void
    {
        PremiumBlockType::create(['block_type' => 'map']);
        $this->actingAs($this->admin());

        // Ne peut arriver via l'UI (les options excluent déjà les blocs premium) —
        // couvre la défense en profondeur de la contrainte unique côté formulaire.
        Livewire::test(CreatePremiumBlock::class)
            ->fillForm(['block_type' => 'map'])
            ->call('create')
            ->assertHasFormErrors(['block_type']);

        $this->assertSame(1, PremiumBlockType::where('block_type', 'map')->count());
    }

    public function test_deleting_a_premium_block_row_unflags_it(): void
    {
        $block = PremiumBlockType::create(['block_type' => 'map']);
        $this->actingAs($this->admin());

        Livewire::test(ListPremiumBlocks::class)
            ->callTableAction(DeleteAction::class, $block);

        $this->assertModelMissing($block);
    }
}
