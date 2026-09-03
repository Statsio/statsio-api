<?php

namespace Tests\Feature\Content;

use App\Models\User\User;
use Database\Factories\DossierFactory;
use Database\Factories\StudioContentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DossierTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    public function test_dossiers_listing_requires_authentication(): void
    {
        $this->getJson('/api/dossiers')->assertStatus(401);
    }

    public function test_dossiers_listing_returns_only_active_dossiers(): void
    {
        DossierFactory::new()->create(['name' => 'Guerre en Ukraine']);
        DossierFactory::new()->inactive()->create(['name' => 'Sujet archivé']);

        $this->withToken($this->token)->getJson('/api/dossiers')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Guerre en Ukraine');
    }

    public function test_pinned_dossiers_endpoint_is_public_and_returns_only_active_pinned(): void
    {
        DossierFactory::new()->pinned()->create(['name' => 'Présidentielle 2027', 'position' => 1]);
        DossierFactory::new()->create(['name' => 'Non épinglé']);
        DossierFactory::new()->pinned()->inactive()->create(['name' => 'Épinglé mais archivé']);

        $this->getJson('/api/dossiers/pinned')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Présidentielle 2027')
            ->assertJsonPath('data.0.slug', fn ($slug) => is_string($slug));
    }

    public function test_suggestions_rank_title_and_category_matches(): void
    {
        DossierFactory::new()->withCategories(['monde'])->create([
            'name' => 'Guerre en Ukraine',
            'keywords' => ['russie', 'kiev'],
        ]);
        DossierFactory::new()->create(['name' => 'Marché immobilier', 'keywords' => ['loyer']]);

        $content = StudioContentFactory::new()->create([
            'user_id' => $this->user->id,
            'title' => "L'offensive russe en Ukraine s'intensifie",
            'categories' => ['monde', 'enquete'],
        ]);

        $this->withToken($this->token)
            ->getJson("/api/studio/content/{$content->slug}/dossier-suggestions")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Guerre en Ukraine');
    }

    public function test_suggestions_404_for_a_non_owner(): void
    {
        $other = User::factory()->create();
        $content = StudioContentFactory::new()->create(['user_id' => $other->id, 'title' => 'Titre']);

        $this->withToken($this->token)
            ->getJson("/api/studio/content/{$content->slug}/dossier-suggestions")
            ->assertStatus(404);
    }

    public function test_sync_dossiers_replaces_the_current_set(): void
    {
        $content = StudioContentFactory::new()->create(['user_id' => $this->user->id]);
        $a = DossierFactory::new()->create();
        $b = DossierFactory::new()->create();
        $content->dossiers()->sync([$a->id]);

        $this->withToken($this->token)
            ->putJson("/api/studio/content/{$content->slug}/dossiers", ['dossier_ids' => [$b->id]])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $b->id);

        $this->assertDatabaseMissing('dossier_studio_content', [
            'studio_content_id' => $content->id,
            'dossier_id' => $a->id,
        ]);
        $this->assertDatabaseHas('dossier_studio_content', [
            'studio_content_id' => $content->id,
            'dossier_id' => $b->id,
        ]);
    }

    public function test_sync_dossiers_rejects_unknown_ids(): void
    {
        $content = StudioContentFactory::new()->create(['user_id' => $this->user->id]);

        $this->withToken($this->token)
            ->putJson("/api/studio/content/{$content->slug}/dossiers", ['dossier_ids' => [999999]])
            ->assertStatus(422);
    }

    public function test_publish_attaches_dossiers_and_republish_can_change_them(): void
    {
        $content = StudioContentFactory::new()->create(['user_id' => $this->user->id, 'title' => 'V1']);
        $a = DossierFactory::new()->create();
        $b = DossierFactory::new()->create();

        $this->withToken($this->token)
            ->postJson("/api/studio/content/{$content->slug}/publish", [
                'published_as' => 'user',
                'dossier_ids' => [$a->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.dossiers.0.id', $a->id);

        $this->withToken($this->token)
            ->postJson("/api/studio/content/{$content->slug}/publish", ['dossier_ids' => [$b->id]])
            ->assertOk();

        $this->assertEqualsCanonicalizing(
            [$b->id],
            $content->fresh()->dossiers->pluck('id')->all(),
        );
    }

    public function test_publish_without_dossier_ids_leaves_placement_untouched(): void
    {
        $content = StudioContentFactory::new()->create(['user_id' => $this->user->id, 'title' => 'V1']);
        $dossier = DossierFactory::new()->create();
        $content->dossiers()->sync([$dossier->id]);

        $this->withToken($this->token)
            ->postJson("/api/studio/content/{$content->slug}/publish", ['published_as' => 'user'])
            ->assertOk();

        $this->assertDatabaseHas('dossier_studio_content', [
            'studio_content_id' => $content->id,
            'dossier_id' => $dossier->id,
        ]);
    }
}
