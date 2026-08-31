<?php

namespace Tests\Feature\Studio;

use App\Models\DataIngestion\Dataset;
use App\Models\DataIngestion\DataSource;
use App\Models\User\User;
use Database\Factories\StudioContentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicStudioBlockTest extends TestCase
{
    use RefreshDatabase;

    private function createDataset(User $user): Dataset
    {
        $dataSource = DataSource::create([
            'user_id' => $user->id,
            'name' => 'Source test',
            'type' => 'csv',
            'original_filename' => 'test.csv',
            'raw_storage_path' => 'data-sources/test.csv',
            'file_size_bytes' => 100,
        ]);

        return Dataset::create([
            'data_source_id' => $dataSource->id,
            'user_id' => $user->id,
            'name' => 'Prix carburants',
        ]);
    }

    public function test_show_public_block_returns_404_for_unpublished_content(): void
    {
        $content = StudioContentFactory::new()->create([
            'type' => 'statsdata',
            'blocks' => [['id' => 'blk1', 'type' => 'kpi']],
        ]);

        $this->getJson("/api/studio/content/public/{$content->slug}/blocks/blk1")
            ->assertStatus(404);
    }

    public function test_show_public_block_serves_a_draft_source_to_its_editor(): void
    {
        $owner = User::factory()->create();
        $dataset = $this->createDataset($owner);
        $content = StudioContentFactory::new()->create([
            'user_id' => $owner->id,
            'type' => 'statsdata',
            'status' => 'draft',
            'blocks' => [[
                'id' => 'blk1',
                'type' => 'kpi',
                'datasetId' => (string) $dataset->id,
                'config' => ['title' => 'Prix moyen'],
            ]],
        ]);

        // Anonyme : masqué.
        $this->getJson("/api/studio/content/public/{$content->slug}/blocks/blk1")->assertStatus(404);

        // L'éditeur du brouillon : autorisé (aperçu du bloc dans le Studio).
        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/studio/content/public/{$content->slug}/blocks/blk1")
            ->assertStatus(200)
            ->assertJsonPath('data.block.id', 'blk1');
    }

    public function test_query_public_serves_a_draft_source_dataset_to_its_editor(): void
    {
        $owner = User::factory()->create();
        $dataset = $this->createDataset($owner);
        $content = StudioContentFactory::new()->create([
            'user_id' => $owner->id,
            'type' => 'statsdata',
            'status' => 'draft',
            'blocks' => [['id' => 'blk1', 'type' => 'bar', 'datasetId' => (string) $dataset->id]],
        ]);

        $this->getJson("/api/studio/content/public/{$content->slug}/datasets/{$dataset->id}/query")->assertStatus(404);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/studio/content/public/{$content->slug}/datasets/{$dataset->id}/query")
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_show_public_block_returns_404_for_unknown_block(): void
    {
        $content = StudioContentFactory::new()->published()->create([
            'type' => 'statsdata',
            'blocks' => [['id' => 'blk1', 'type' => 'kpi']],
        ]);

        $this->getJson("/api/studio/content/public/{$content->slug}/blocks/nope")
            ->assertStatus(404);
    }

    public function test_show_public_block_returns_404_for_non_embeddable_type(): void
    {
        $content = StudioContentFactory::new()->published()->create([
            'type' => 'statsdata',
            'blocks' => [['id' => 'blk1', 'type' => 'paragraph', 'config' => ['content' => '<p>x</p>']]],
        ]);

        $this->getJson("/api/studio/content/public/{$content->slug}/blocks/blk1")
            ->assertStatus(404);
    }

    public function test_show_public_block_returns_block_doc_and_datasets(): void
    {
        $owner = User::factory()->create();
        $dataset = $this->createDataset($owner);
        $content = StudioContentFactory::new()->published()->create([
            'user_id' => $owner->id,
            'type' => 'statsdata',
            'title' => 'Le prix des carburants',
            'blocks' => [[
                'id' => 'blk1',
                'type' => 'kpi',
                'datasetId' => (string) $dataset->id,
                'fieldMapping' => ['valueColumn' => 'prix', 'aggregate' => 'avg'],
                'config' => ['title' => 'Prix moyen national'],
            ]],
        ]);

        $this->getJson("/api/studio/content/public/{$content->slug}/blocks/blk1")
            ->assertStatus(200)
            ->assertJsonPath('data.block.id', 'blk1')
            ->assertJsonPath('data.block.type', 'kpi')
            ->assertJsonPath('data.doc.slug', $content->slug)
            ->assertJsonPath('data.doc.title', 'Le prix des carburants')
            ->assertJsonCount(1, 'data.datasets')
            ->assertJsonPath('data.datasets.0.name', 'Prix carburants');
    }

    public function test_show_public_block_returns_the_source_page_params_for_the_block(): void
    {
        $owner = User::factory()->create();
        $dataset = $this->createDataset($owner);
        $content = StudioContentFactory::new()->published()->create([
            'user_id' => $owner->id,
            'type' => 'statsdata',
            'pages' => [['id' => 'page-main', 'title' => 'Principale', 'params' => [
                ['name' => 'carburant', 'label' => 'Carburant', 'column' => 'carburant', 'defaultValue' => 'Gazole'],
            ]]],
            'sections' => [['id' => 's1', 'layout' => '1-col', 'pageId' => 'page-main']],
            'blocks' => [[
                'id' => 'blk1',
                'type' => 'line',
                'zoneId' => 's1-0',
                'datasetId' => (string) $dataset->id,
                'filters' => [['column' => 'carburant', 'operator' => '=', 'value' => '{{carburant}}']],
            ]],
        ]);

        $this->getJson("/api/studio/content/public/{$content->slug}/blocks/blk1")
            ->assertStatus(200)
            ->assertJsonPath('data.params.0.name', 'carburant')
            ->assertJsonPath('data.params.0.defaultValue', 'Gazole');
    }

    public function test_show_public_block_does_not_count_a_view(): void
    {
        $content = StudioContentFactory::new()->published()->create([
            'type' => 'statsdata',
            'views_count' => 0,
            'blocks' => [['id' => 'blk1', 'type' => 'kpi']],
        ]);

        $this->getJson("/api/studio/content/public/{$content->slug}/blocks/blk1")->assertStatus(200);

        $this->assertSame(0, $content->fresh()->views_count);
    }

    public function test_list_public_blocks_filters_to_embeddable_types(): void
    {
        $owner = User::factory()->create();
        $dataset = $this->createDataset($owner);
        $content = StudioContentFactory::new()->published()->create([
            'user_id' => $owner->id,
            'type' => 'statsdata',
            'sections' => [['id' => 's1', 'layout' => '1-col']],
            'blocks' => [
                ['id' => 'h1', 'type' => 'heading', 'zoneId' => 's1-0', 'config' => ['content' => '<h2>T</h2>']],
                ['id' => 'k1', 'type' => 'kpi', 'zoneId' => 's1-0', 'datasetId' => (string) $dataset->id, 'config' => ['title' => 'Prix moyen']],
                ['id' => 'b1', 'type' => 'bar', 'zoneId' => 's1-0', 'datasetId' => (string) $dataset->id, 'config' => []],
                ['id' => 'p1', 'type' => 'paragraph', 'zoneId' => 's1-0', 'config' => ['content' => '<p>x</p>']],
            ],
        ]);

        $response = $this->getJson("/api/studio/content/public/{$content->slug}/blocks")
            ->assertStatus(200)
            ->assertJsonPath('data.doc.slug', $content->slug)
            ->assertJsonCount(2, 'data.blocks');

        $ids = collect($response->json('data.blocks'))->pluck('id')->all();
        $this->assertSame(['k1', 'b1'], $ids);
        $this->assertSame('Prix moyen', $response->json('data.blocks.0.title'));
        $this->assertSame('Prix carburants', $response->json('data.blocks.0.datasetName'));
        $this->assertSame('Graphique en barres', $response->json('data.blocks.1.title'));
    }

    public function test_article_can_query_another_users_published_statsdata_via_source_slug(): void
    {
        // Utilisateur B publie un Statsdata avec un bloc lié à son dataset.
        $userB = User::factory()->create();
        $dataset = $this->createDataset($userB);
        $source = StudioContentFactory::new()->published()->create([
            'user_id' => $userB->id,
            'type' => 'statsdata',
            'blocks' => [[
                'id' => 'blk1',
                'type' => 'bar',
                'datasetId' => (string) $dataset->id,
                'fieldMapping' => ['xAxis' => 'region', 'yAxes' => ['prix']],
                'config' => [],
            ]],
        ]);

        // Utilisateur A publie un article qui embarque ce bloc (sd-embed).
        $userA = User::factory()->create();
        StudioContentFactory::new()->published()->create([
            'user_id' => $userA->id,
            'type' => 'article',
            'blocks' => [[
                'id' => 'emb1',
                'type' => 'sd-embed',
                'config' => ['sourceSlug' => $source->slug, 'sourceBlockId' => 'blk1'],
            ]],
        ]);

        // Le front récupère les données du bloc embarqué VIA le slug du Statsdata source.
        $this->getJson("/api/studio/content/public/{$source->slug}/datasets/{$dataset->id}/query")
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }
}
