<?php

namespace Tests\Feature\Admin;

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserControllerTest extends TestCase
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

    public function test_index_lists_users_with_profile(): void
    {
        User::factory()->withProfile()->create();

        $this->asAdmin()->getJson('/api/admin/users')
            ->assertStatus(200)
            ->assertJsonPath('total', 2); // admin + the created user
    }

    public function test_index_filters_by_search_on_email(): void
    {
        User::factory()->create(['email' => 'findme@example.com']);
        User::factory()->create(['email' => 'other@example.com']);

        $response = $this->asAdmin()->getJson('/api/admin/users?search=FINDME');

        $response->assertStatus(200);
        $emails = collect($response->json('data'))->pluck('email')->all();
        $this->assertSame(['findme@example.com'], $emails);
    }

    public function test_index_filters_by_search_on_profile_name(): void
    {
        $match = User::factory()->create();
        $match->profile()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
        $other = User::factory()->create();
        $other->profile()->create(['first_name' => 'Bob', 'last_name' => 'Martin']);

        $response = $this->asAdmin()->getJson('/api/admin/users?search=lovelace');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$match->id], $ids);
    }

    public function test_index_filters_deleted_status_with_only_trashed(): void
    {
        $deleted = User::factory()->create();
        $deleted->delete();
        User::factory()->create();

        $response = $this->asAdmin()->getJson('/api/admin/users?status=deleted');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$deleted->id], $ids);
    }

    public function test_show_returns_404_unknown_user(): void
    {
        $this->asAdmin()->getJson('/api/admin/users/999999')->assertStatus(404);
    }

    public function test_update_toggles_is_admin_and_status(): void
    {
        $user = User::factory()->create();

        $response = $this->asAdmin()->patchJson("/api/admin/users/{$user->id}", [
            'is_admin' => true,
            'status' => 'suspended',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.is_admin', true)
            ->assertJsonPath('data.status', 'suspended');
    }

    public function test_update_prevents_removing_own_admin_role(): void
    {
        $this->asAdmin()->patchJson("/api/admin/users/{$this->admin->id}", [
            'is_admin' => false,
        ])->assertStatus(422);
    }

    public function test_destroy_soft_deletes_user(): void
    {
        $user = User::factory()->create();

        $this->asAdmin()->deleteJson("/api/admin/users/{$user->id}")->assertStatus(200);

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_destroy_prevents_deleting_self(): void
    {
        $this->asAdmin()->deleteJson("/api/admin/users/{$this->admin->id}")->assertStatus(422);
    }

    public function test_restore_undoes_soft_delete(): void
    {
        $user = User::factory()->create();
        $user->delete();

        $this->asAdmin()->postJson("/api/admin/users/{$user->id}/restore")->assertStatus(200);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'deleted_at' => null]);
    }
}
