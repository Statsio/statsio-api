<?php

namespace Tests\Feature\User;

use App\Models\StudioContent;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountFavoritesTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): array
    {
        $user = User::factory()->create();

        return [$user, $user->createToken('test')->plainTextToken];
    }

    public function test_toggle_adds_then_removes_a_favorite(): void
    {
        [$user, $token] = $this->actingUser();
        $content = StudioContent::factory()->published()->create();

        $this->withToken($token)->postJson('/api/me/favorites', ['id' => $content->id])
            ->assertOk()
            ->assertJsonPath('data.favorited', true);

        $this->assertDatabaseHas('user_favorites', [
            'user_id' => $user->id,
            'favoritable_id' => $content->id,
        ]);

        $this->withToken($token)->postJson('/api/me/favorites', ['id' => $content->id])
            ->assertOk()
            ->assertJsonPath('data.favorited', false);

        $this->assertDatabaseMissing('user_favorites', [
            'user_id' => $user->id,
            'favoritable_id' => $content->id,
        ]);
    }

    public function test_index_returns_favorited_contents(): void
    {
        [, $token] = $this->actingUser();
        $content = StudioContent::factory()->published()->create(['title' => 'Pouvoir d\'achat']);

        $this->withToken($token)->postJson('/api/me/favorites', ['id' => $content->id])->assertOk();

        $this->withToken($token)->getJson('/api/me/favorites')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Pouvoir d\'achat')
            ->assertJsonPath('data.0.id', (string) $content->id);
    }

    public function test_delete_removes_a_favorite(): void
    {
        [$user, $token] = $this->actingUser();
        $content = StudioContent::factory()->published()->create();
        $this->withToken($token)->postJson('/api/me/favorites', ['id' => $content->id])->assertOk();

        $this->withToken($token)->deleteJson("/api/me/favorites/{$content->id}")
            ->assertOk()
            ->assertJsonPath('data.favorited', false);

        $this->assertDatabaseMissing('user_favorites', ['user_id' => $user->id]);
    }

    public function test_me_exposes_favorites_count(): void
    {
        [, $token] = $this->actingUser();
        $content = StudioContent::factory()->published()->create();
        $this->withToken($token)->postJson('/api/me/favorites', ['id' => $content->id])->assertOk();

        $this->withToken($token)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.user.counts.favorites', 1);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/me/favorites')->assertUnauthorized();
    }
}
