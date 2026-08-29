<?php

namespace Tests\Feature\User;

use App\Models\StudioContent;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_matches_favorites_history_and_own_contents(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $own = StudioContent::factory()->for($user)->published()->create(['title' => 'Le pouvoir d\'achat des ménages']);
        $fav = StudioContent::factory()->published()->create(['title' => 'Pouvoir et territoires', 'slug' => 'pt']);

        $this->withToken($token)->postJson('/api/me/favorites', ['id' => $fav->id])->assertOk();
        $this->withToken($token)->postJson('/api/me/history', ['slug' => 'pt'])->assertOk();

        $response = $this->withToken($token)->getJson('/api/me/search?q=pouvoir')->assertOk();

        $response->assertJsonPath('data.contents.0.id', (string) $own->id);
        $response->assertJsonCount(1, 'data.favorites');
        $response->assertJsonCount(1, 'data.history');
    }

    public function test_short_query_returns_empty_sections(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/me/search?q=a')
            ->assertOk()
            ->assertJsonPath('data.favorites', [])
            ->assertJsonPath('data.contents', []);
    }
}
