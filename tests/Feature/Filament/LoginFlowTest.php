<?php

namespace Tests\Feature\Filament;

use App\Models\User\User;
use Filament\Auth\Pages\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class LoginFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_authenticate_through_the_filament_login_form(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'flow@statsio.fr',
            'password' => Hash::make('secret-password'),
        ]);

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'flow@statsio.fr',
                'password' => 'secret-password',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($admin, 'web');
    }

    public function test_non_admin_cannot_authenticate_into_the_panel(): void
    {
        User::factory()->create([
            'email' => 'nope@statsio.fr',
            'password' => Hash::make('secret-password'),
            'is_admin' => false,
        ]);

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'nope@statsio.fr',
                'password' => 'secret-password',
            ])
            ->call('authenticate')
            ->assertHasFormErrors();

        $this->assertGuest('web');
    }
}
