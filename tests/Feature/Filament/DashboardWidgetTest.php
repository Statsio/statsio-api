<?php

namespace Tests\Feature\Filament;

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_stats_widget(): void
    {
        User::factory()->count(3)->create();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('Utilisateurs');
    }
}
