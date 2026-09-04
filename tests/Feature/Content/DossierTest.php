<?php

namespace Tests\Feature\Content;

use App\Domain\Content\Enums\SubBrandEnum;
use App\Models\Content\ContentCategory;
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

    public function test_public_catalog_is_open_and_ranks_active_dossiers_with_counts(): void
    {
        $ukraine = DossierFactory::new()->create(['name' => 'Guerre en Ukraine', 'position' => 0]);
        $climat = DossierFactory::new()->create(['name' => 'Crise climatique', 'position' => 1]);
        DossierFactory::new()->inactive()->create(['name' => 'Archivé']);

        $a = StudioContentFactory::new()->published()->create(['type' => 'article']);
        $b = StudioContentFactory::new()->published()->create(['type' => 'statsdata']);
        StudioContentFactory::new()->create(['type' => 'article']); // brouillon → ignoré
        $ukraine->studioContents()->sync([$a->id, $b->id]);
        $climat->studioContents()->sync([$a->id]);

        $res = $this->getJson('/api/dossiers/catalog')
            ->assertOk()
            ->assertJsonPath('data.stats.dossiers', 2)
            ->assertJsonPath('data.featured.slug', $ukraine->slug)
            ->assertJsonPath('data.featured.content_count', 2);

        $this->assertSame(1, collect($res->json('data.data'))->firstWhere('slug', $climat->slug)['content_count']);
    }

    public function test_public_catalog_filters_by_sub_brand(): void
    {
        DossierFactory::new()->subBrand(SubBrandEnum::Tvstats)->create(['name' => 'Dossier TV']);
        DossierFactory::new()->subBrand(SubBrandEnum::Medistats)->create(['name' => 'Dossier Santé']);
        DossierFactory::new()->subBrand(SubBrandEnum::All)->create(['name' => 'Dossier Partout']);

        $res = $this->getJson('/api/dossiers/catalog?sub_brand=tvstats')->assertOk();
        $names = collect($res->json('data.data'))
            ->push($res->json('data.featured'))
            ->filter()
            ->pluck('name')->sort()->values()->all();
        $this->assertSame(['Dossier Partout', 'Dossier TV'], $names);
        $this->assertSame(2, $res->json('data.meta.total'));

        $this->assertSame(3, $this->getJson('/api/dossiers/catalog')->json('data.meta.total'));
    }

    public function test_public_catalog_category_facets_are_scoped_to_the_sub_brand(): void
    {
        ContentCategory::create(['slug' => 'brand-tv', 'name' => 'Émissions', 'position' => 90, 'sub_brand' => SubBrandEnum::Tvstats]);
        ContentCategory::create(['slug' => 'brand-politique', 'name' => 'Politique', 'position' => 91, 'sub_brand' => SubBrandEnum::Statsio]);
        ContentCategory::create(['slug' => 'brand-monde', 'name' => 'Monde', 'position' => 92, 'sub_brand' => SubBrandEnum::All]);

        // Un dossier « toutes marques » tagué d'une rubrique Statsio : il apparaît
        // sur TVStats, mais sa rubrique Statsio ne doit pas remonter dans les facettes.
        DossierFactory::new()->subBrand(SubBrandEnum::All)->withCategories(['brand-politique'])->create(['name' => 'Partout Politique']);
        DossierFactory::new()->subBrand(SubBrandEnum::Tvstats)->withCategories(['brand-tv'])->create(['name' => 'TV Émissions']);
        DossierFactory::new()->subBrand(SubBrandEnum::All)->withCategories(['brand-monde'])->create(['name' => 'Partout Monde']);

        $facets = collect(
            $this->getJson('/api/dossiers/catalog?sub_brand=tvstats')->assertOk()->json('data.facets.categories')
        )->pluck('value')->all();

        $this->assertContains('brand-tv', $facets);
        $this->assertContains('brand-monde', $facets);
        $this->assertNotContains('brand-politique', $facets);
    }

    public function test_public_catalog_filters_by_search(): void
    {
        DossierFactory::new()->create(['name' => 'Guerre en Ukraine']);
        DossierFactory::new()->create(['name' => 'Marché immobilier']);

        $this->getJson('/api/dossiers/catalog?q=ukraine')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.name', 'Guerre en Ukraine');
    }

    public function test_public_dossier_detail_returns_only_published_contents_and_type_counts(): void
    {
        $dossier = DossierFactory::new()->create(['name' => 'Guerre en Ukraine']);
        $article = StudioContentFactory::new()->published()->create(['type' => 'article', 'title' => 'Six mois de livraisons']);
        $stat = StudioContentFactory::new()->published()->create(['type' => 'statsdata', 'title' => 'Sanctions']);
        $draft = StudioContentFactory::new()->create(['type' => 'article', 'title' => 'Brouillon']);
        $dossier->studioContents()->sync([$article->id, $stat->id, $draft->id]);

        $this->getJson("/api/dossiers/public/{$dossier->slug}")
            ->assertOk()
            ->assertJsonPath('data.dossier.name', 'Guerre en Ukraine')
            ->assertJsonPath('data.counts.all', 2)
            ->assertJsonPath('data.counts.article', 1)
            ->assertJsonPath('data.counts.statsdata', 1)
            ->assertJsonCount(2, 'data.items');
    }

    public function test_public_dossier_detail_404_when_inactive_or_missing(): void
    {
        $dossier = DossierFactory::new()->inactive()->create();

        $this->getJson("/api/dossiers/public/{$dossier->slug}")->assertStatus(404);
        $this->getJson('/api/dossiers/public/inconnu')->assertStatus(404);
    }
}
