<?php

namespace Tests\Feature\Studio;

use App\Domain\Content\Enums\SubBrandEnum;
use App\Models\Channel\Channel;
use App\Models\Content\ContentCategory;
use App\Models\User\User;
use Database\Factories\DossierFactory;
use Database\Factories\StudioContentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicStudioCatalogTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_catalog_returns_only_published_articles_without_blocks(): void
    {
        $published = StudioContentFactory::new()->published()->create([
            'user_id' => $this->user->id,
            'type' => 'article',
            'title' => 'Article publié',
            'description' => 'Un chapô',
            'categories' => ['Économie', 'enquete'],
            'blocks' => [
                ['type' => 'paragraph', 'config' => ['text' => str_repeat('mot ', 200)]],
                ['type' => 'bar', 'datasetId' => '12', 'config' => []],
            ],
        ]);
        $published->forceFill(['views_count' => 40])->save();
        StudioContentFactory::new()->create([
            'user_id' => $this->user->id,
            'type' => 'article',
            'title' => 'Brouillon',
        ]);
        StudioContentFactory::new()->published()->create([
            'user_id' => $this->user->id,
            'type' => 'statsdata',
            'title' => 'Un dataset',
        ]);

        $response = $this->getJson('/api/studio/content/public/catalog?type=article');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', (string) $published->id)
            ->assertJsonPath('data.0.format', 'enquete')
            ->assertJsonPath('data.0.category', 'Économie')
            ->assertJsonPath('data.0.linked_datasets_count', 1)
            ->assertJsonPath('data.0.charts_count', 1)
            ->assertJsonMissingPath('data.0.blocks')
            ->assertJsonPath('stats.published', 1);
        $this->assertGreaterThanOrEqual(1, $response->json('data.0.reading_minutes'));
    }

    public function test_catalog_exposes_the_primary_linked_dossier(): void
    {
        $withDossier = StudioContentFactory::new()->published()->create([
            'user_id' => $this->user->id,
            'type' => 'article',
            'title' => 'Contenu rangé',
        ]);
        $withDossier->forceFill(['views_count' => 10])->save();
        $active = DossierFactory::new()->create(['name' => 'Guerre en Ukraine', 'position' => 0]);
        $archived = DossierFactory::new()->inactive()->create(['name' => 'Sujet archivé', 'position' => 1]);
        $withDossier->dossiers()->sync([$active->id, $archived->id]);

        $noDossier = StudioContentFactory::new()->published()->create([
            'user_id' => $this->user->id,
            'type' => 'article',
            'title' => 'Contenu libre',
        ]);

        $response = $this->getJson('/api/studio/content/public/catalog?type=article&sort=views');

        $response->assertOk()
            ->assertJsonPath('data.0.id', (string) $withDossier->id)
            ->assertJsonPath('data.0.dossier.slug', $active->slug)
            ->assertJsonPath('data.0.dossier.name', 'Guerre en Ukraine')
            ->assertJsonPath('data.1.id', (string) $noDossier->id)
            ->assertJsonPath('data.1.dossier', null);
    }

    public function test_catalog_search_filters_by_title(): void
    {
        StudioContentFactory::new()->published()->create([
            'user_id' => $this->user->id,
            'type' => 'article',
            'title' => 'Urgences saturées',
        ]);
        StudioContentFactory::new()->published()->create([
            'user_id' => $this->user->id,
            'type' => 'article',
            'title' => 'Prix des carburants',
        ]);

        $response = $this->getJson('/api/studio/content/public/catalog?type=article&q=urgences');

        $response->assertOk()->assertJsonPath('meta.total', 1);
        $this->assertSame('Urgences saturées', $response->json('data.0.title'));
    }

    public function test_catalog_has_data_and_sort_by_views(): void
    {
        $without = StudioContentFactory::new()->published()->create([
            'user_id' => $this->user->id,
            'type' => 'article',
            'title' => 'Sans data',
            'blocks' => [['type' => 'paragraph', 'config' => ['text' => 'hello']]],
        ]);
        $without->forceFill(['views_count' => 999])->save();
        $withData = StudioContentFactory::new()->published()->create([
            'user_id' => $this->user->id,
            'type' => 'article',
            'title' => 'Avec data',
            'blocks' => [['type' => 'line', 'datasetId' => '99', 'config' => []]],
        ]);
        $withData->forceFill(['views_count' => 3])->save();

        $response = $this->getJson('/api/studio/content/public/catalog?type=article&has_data=1&sort=views');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', (string) $withData->id);
    }

    public function test_catalog_respects_per_page_and_hides_featured_when_filtered(): void
    {
        foreach (range(1, 5) as $i) {
            $row = StudioContentFactory::new()->published()->create([
                'user_id' => $this->user->id,
                'type' => 'article',
                'title' => "Article $i",
                'is_featured' => $i === 3,
                'featured_priority' => $i === 3 ? 1 : null,
            ]);
            $row->forceFill(['views_count' => $i])->save();
        }

        $plain = $this->getJson('/api/studio/content/public/catalog?type=article&per_page=3&sort=views');
        $plain->assertOk()
            ->assertJsonPath('meta.shown', 3)
            ->assertJsonPath('meta.has_more', true)
            ->assertJsonPath('featured.title', 'Article 3')
            ->assertJsonPath('data.0.title', 'Article 3')
            ->assertJsonPath('data.0.is_featured', true);

        $filtered = $this->getJson('/api/studio/content/public/catalog?type=article&q=Article&per_page=3');
        $filtered->assertOk()->assertJsonPath('featured', null);
    }

    public function test_catalog_has_no_featured_card_when_the_admin_flagged_nothing(): void
    {
        StudioContentFactory::new()->published()->create([
            'user_id' => $this->user->id,
            'type' => 'article',
            'title' => 'Ordinaire',
        ]);

        $this->getJson('/api/studio/content/public/catalog?type=article')
            ->assertOk()
            ->assertJsonPath('featured', null)
            ->assertJsonPath('data.0.is_featured', false);
    }

    public function test_catalog_pins_extra_featured_contents_first_by_priority(): void
    {
        $normalTop = StudioContentFactory::new()->published()->create([
            'user_id' => $this->user->id, 'type' => 'article', 'title' => 'Très lu',
        ]);
        $normalTop->forceFill(['views_count' => 9999])->save();

        $second = StudioContentFactory::new()->published()->create([
            'user_id' => $this->user->id, 'type' => 'article', 'title' => 'À la une #2',
            'is_featured' => true, 'featured_priority' => 2,
        ]);
        $first = StudioContentFactory::new()->published()->create([
            'user_id' => $this->user->id, 'type' => 'article', 'title' => 'À la une #1',
            'is_featured' => true, 'featured_priority' => 1,
        ]);

        $res = $this->getJson('/api/studio/content/public/catalog?type=article&sort=views')->assertOk();

        $this->assertSame((string) $first->id, $res->json('featured.id'));
        $this->assertSame(['À la une #1', 'À la une #2', 'Très lu'], collect($res->json('data'))->pluck('title')->all());
    }

    public function test_catalog_filters_by_channel_id(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $own = StudioContentFactory::new()->published()->create([
            'user_id' => $this->user->id,
            'type' => 'article',
            'channel_id' => $channel->id,
            'published_as' => 'channel',
            'title' => 'De la chaîne',
        ]);
        StudioContentFactory::new()->published()->create([
            'user_id' => $this->user->id,
            'type' => 'article',
            'title' => 'Indépendant',
        ]);

        $response = $this->getJson("/api/studio/content/public/catalog?type=article&channel_id={$channel->id}");

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', (string) $own->id)
            ->assertJsonPath('data.0.publisher.is_channel', true);
    }

    public function test_catalog_filters_by_sub_brand(): void
    {
        $tv = StudioContentFactory::new()->published()->create([
            'user_id' => $this->user->id,
            'type' => 'article',
            'title' => 'Sur TVStats',
            'sub_brand' => 'tvstats',
        ]);
        StudioContentFactory::new()->published()->create([
            'user_id' => $this->user->id,
            'type' => 'article',
            'title' => 'Sur Statsio',
            'sub_brand' => 'statsio',
        ]);

        $this->getJson('/api/studio/content/public/catalog?type=article&sub_brand=tvstats')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', (string) $tv->id)
            ->assertJsonPath('data.0.sub_brand', 'tvstats');
    }

    public function test_category_facets_are_scoped_to_the_sub_brand(): void
    {
        ContentCategory::create(['slug' => 'brand-tv', 'name' => 'TV', 'position' => 90, 'sub_brand' => SubBrandEnum::Tvstats]);
        ContentCategory::create(['slug' => 'brand-eco', 'name' => 'Éco', 'position' => 91, 'sub_brand' => SubBrandEnum::Statsio]);

        StudioContentFactory::new()->published()->create([
            'user_id' => $this->user->id,
            'type' => 'article',
            'sub_brand' => 'tvstats',
            'categories' => ['brand-tv', 'brand-eco'],
        ]);

        $facets = collect(
            $this->getJson('/api/studio/content/public/catalog?type=article&sub_brand=tvstats')
                ->assertOk()
                ->json('facets.categories')
        )->pluck('value')->all();

        $this->assertContains('brand-tv', $facets);
        $this->assertNotContains('brand-eco', $facets);
    }
}
