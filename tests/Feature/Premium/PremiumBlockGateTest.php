<?php

namespace Tests\Feature\Premium;

use App\Models\PremiumBlockType;
use App\Models\User\User;
use Database\Factories\StudioContentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vérifie que les blocs marqués premium (`premium_block_types`) sont bloqués à l'AJOUT
 * et à la MODIFICATION pour les utilisateurs freemium — mais jamais à la lecture, au
 * déplacement, à la suppression ni à la publication d'un bloc premium déjà en place
 * (grandfathering — voir PremiumBlockGate::diff()).
 */
class PremiumBlockGateTest extends TestCase
{
    use RefreshDatabase;

    private function mapBlock(array $overrides = []): array
    {
        return array_merge(
            ['id' => 'b1', 'type' => 'map', 'zoneId' => 's1-0', 'fieldMapping' => [], 'config' => []],
            $overrides,
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        PremiumBlockType::create(['block_type' => 'map']);
    }

    public function test_freemium_user_cannot_create_content_with_a_premium_block(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson('/api/studio/content', [
                'title' => 'Carte des résultats',
                'blocks' => [$this->mapBlock()],
            ])
            ->assertStatus(403)
            ->assertJsonPath('blocked_blocks', ['map']);

        $this->assertDatabaseMissing('studio_contents', ['title' => 'Carte des résultats']);
    }

    public function test_premium_user_can_create_content_with_a_premium_block(): void
    {
        $user = User::factory()->premium()->create();

        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson('/api/studio/content', [
                'title' => 'Carte des résultats',
                'blocks' => [$this->mapBlock()],
            ])
            ->assertStatus(201);
    }

    public function test_freemium_user_cannot_add_a_premium_block_via_update(): void
    {
        $user = User::factory()->create();
        $content = StudioContentFactory::new()->create(['user_id' => $user->id]);

        $this->withToken($user->createToken('t')->plainTextToken)
            ->putJson("/api/studio/content/{$content->slug}", [
                'blocks' => [$this->mapBlock()],
            ])
            ->assertStatus(403)
            ->assertJsonPath('blocked_blocks', ['map']);
    }

    public function test_update_without_touching_blocks_is_unaffected_by_the_gate(): void
    {
        $user = User::factory()->create();
        $content = StudioContentFactory::new()->create(['user_id' => $user->id, 'blocks' => [$this->mapBlock()]]);

        $this->withToken($user->createToken('t')->plainTextToken)
            ->putJson("/api/studio/content/{$content->slug}", ['title' => 'Nouveau titre'])
            ->assertStatus(200);
    }

    public function test_freemium_user_can_resave_an_unchanged_premium_block_via_update(): void
    {
        $user = User::factory()->create();
        $content = StudioContentFactory::new()->create(['user_id' => $user->id, 'blocks' => [$this->mapBlock()]]);

        // Même bloc, à l'identique, renvoyé dans le payload (autosave classique) : jamais bloquant.
        $this->withToken($user->createToken('t')->plainTextToken)
            ->putJson("/api/studio/content/{$content->slug}", ['blocks' => [$this->mapBlock()]])
            ->assertStatus(200);
    }

    public function test_freemium_user_cannot_modify_an_existing_premium_block_via_update(): void
    {
        $user = User::factory()->create();
        $content = StudioContentFactory::new()->create(['user_id' => $user->id, 'blocks' => [$this->mapBlock()]]);

        $this->withToken($user->createToken('t')->plainTextToken)
            ->putJson("/api/studio/content/{$content->slug}", [
                'blocks' => [$this->mapBlock(['config' => ['title' => 'Changé']])],
            ])
            ->assertStatus(403)
            ->assertJsonPath('blocked_blocks', ['map']);

        $this->assertSame([], $content->fresh()->blocks[0]['config'] ?? []);
    }

    public function test_freemium_user_can_remove_an_existing_premium_block_via_update(): void
    {
        $user = User::factory()->create();
        $content = StudioContentFactory::new()->create(['user_id' => $user->id, 'blocks' => [$this->mapBlock()]]);

        $this->withToken($user->createToken('t')->plainTextToken)
            ->putJson("/api/studio/content/{$content->slug}", ['blocks' => []])
            ->assertStatus(200);

        $this->assertSame([], $content->fresh()->blocks);
    }

    public function test_freemium_user_can_publish_a_draft_already_containing_a_premium_block(): void
    {
        $user = User::factory()->create();
        $content = StudioContentFactory::new()->create(['user_id' => $user->id, 'blocks' => [$this->mapBlock()]]);

        // Grandfathering : un brouillon qui contient déjà un bloc premium reste publiable
        // même par un auteur qui n'a jamais eu (ou n'a plus) le Premium — seuls l'ajout et
        // la modification d'un bloc premium sont bloqués, jamais la publication.
        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson("/api/studio/content/{$content->slug}/publish")
            ->assertStatus(200);

        $this->assertSame('published', $content->fresh()->status);
    }

    public function test_premium_user_can_publish_a_draft_containing_a_premium_block(): void
    {
        $user = User::factory()->premium()->create();
        $content = StudioContentFactory::new()->create(['user_id' => $user->id, 'blocks' => [$this->mapBlock()]]);

        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson("/api/studio/content/{$content->slug}/publish")
            ->assertStatus(200);

        $this->assertSame('published', $content->fresh()->status);
    }

    public function test_expired_premium_no_longer_bypasses_the_gate(): void
    {
        $user = User::factory()->premium()->create(['premium_until' => now()->subDay()]);

        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson('/api/studio/content', [
                'title' => 'Carte des résultats',
                'blocks' => [$this->mapBlock()],
            ])
            ->assertStatus(403);
    }

    public function test_unflagging_a_block_removes_the_gate(): void
    {
        PremiumBlockType::query()->delete();
        $user = User::factory()->create();

        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson('/api/studio/content', [
                'title' => 'Carte des résultats',
                'blocks' => [$this->mapBlock()],
            ])
            ->assertStatus(201);
    }
}
