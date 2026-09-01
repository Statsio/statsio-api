<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserResourceActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $admin->getKey()])
            ->assertActionHidden(DeleteAction::class);
    }

    public function test_admin_can_delete_another_user(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();
        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $target->getKey()])
            ->assertActionVisible(DeleteAction::class);
    }

    public function test_is_admin_toggle_is_disabled_on_own_record(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $admin->getKey()])
            ->assertFormFieldDisabled('is_admin');
    }

    public function test_status_can_be_updated(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['status' => 'active']);
        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $target->getKey()])
            ->fillForm(['status' => 'suspended'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('suspended', $target->fresh()->status);
    }

    public function test_trashed_users_are_listed_and_restorable(): void
    {
        $admin = User::factory()->admin()->create();
        $deleted = User::factory()->create();
        $deleted->delete();
        $this->actingAs($admin);

        Livewire::test(ListUsers::class)
            ->filterTable('trashed', true)
            ->assertCanSeeTableRecords([$deleted])
            ->callTableAction(RestoreAction::class, $deleted);

        $this->assertNull($deleted->fresh()->deleted_at);
    }
}
