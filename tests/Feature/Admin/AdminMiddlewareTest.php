<?php

namespace Tests\Feature\Admin;

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_guest_gets_401_on_admin_route(): void
    {
        $this->getJson('/api/admin/tv/categories')->assertStatus(401);
    }

    public function test_non_admin_user_gets_403_on_admin_route(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->token($user))
            ->getJson('/api/admin/tv/categories')
            ->assertStatus(403)
            ->assertJsonPath('error', 'Forbidden');
    }

    public function test_admin_user_passes_through_to_controller(): void
    {
        $admin = User::factory()->admin()->create();

        $this->withToken($this->token($admin))
            ->getJson('/api/admin/tv/categories')
            ->assertStatus(200);
    }
}
