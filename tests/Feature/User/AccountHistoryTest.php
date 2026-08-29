<?php

namespace Tests\Feature\User;

use App\Models\StudioContent;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): array
    {
        $user = User::factory()->create();

        return [$user, $user->createToken('test')->plainTextToken];
    }

    public function test_store_records_a_view_and_index_groups_it(): void
    {
        [$user, $token] = $this->actingUser();
        $content = StudioContent::factory()->published()->create(['title' => 'Sécheresse 2026', 'slug' => 'secheresse-2026']);

        $this->withToken($token)->postJson('/api/me/history', ['slug' => 'secheresse-2026', 'progress' => 40])
            ->assertOk();

        $this->assertDatabaseHas('user_content_views', [
            'user_id' => $user->id,
            'studio_content_id' => $content->id,
            'progress' => 40,
        ]);

        $this->withToken($token)->getJson('/api/me/history')
            ->assertOk()
            ->assertJsonPath('data.groups.0.key', 'today')
            ->assertJsonPath('data.groups.0.items.0.title', 'Sécheresse 2026');
    }

    public function test_store_is_idempotent_per_content(): void
    {
        [$user, $token] = $this->actingUser();
        $content = StudioContent::factory()->published()->create(['slug' => 'x']);

        $this->withToken($token)->postJson('/api/me/history', ['slug' => 'x'])->assertOk();
        $this->withToken($token)->postJson('/api/me/history', ['slug' => 'x'])->assertOk();

        $this->assertSame(1, $user->contentViews()->count());
        $this->assertSame(2, $user->contentViews()->first()->view_count);
    }

    public function test_in_progress_returns_partially_read_contents(): void
    {
        [, $token] = $this->actingUser();
        StudioContent::factory()->published()->create(['slug' => 'a']);
        $this->withToken($token)->postJson('/api/me/history', ['slug' => 'a', 'progress' => 62])->assertOk();

        $this->withToken($token)->getJson('/api/me/history/in-progress')
            ->assertOk()
            ->assertJsonPath('data.0.progress', 62);
    }

    public function test_clear_wipes_history(): void
    {
        [$user, $token] = $this->actingUser();
        StudioContent::factory()->published()->create(['slug' => 'a']);
        $this->withToken($token)->postJson('/api/me/history', ['slug' => 'a'])->assertOk();

        $this->withToken($token)->deleteJson('/api/me/history')->assertOk();

        $this->assertSame(0, $user->contentViews()->count());
    }
}
