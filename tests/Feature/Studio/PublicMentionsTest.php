<?php

namespace Tests\Feature\Studio;

use Database\Factories\StudioContentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicMentionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_mentions_search_returns_published_content_across_types(): void
    {
        StudioContentFactory::new()->published()->create(['type' => 'statsdata', 'title' => 'Le prix des carburants']);
        StudioContentFactory::new()->published()->create(['type' => 'article', 'title' => 'Carburants : un an d’enquête']);
        StudioContentFactory::new()->create(['type' => 'article', 'title' => 'Carburants brouillon non publié']);
        StudioContentFactory::new()->published()->create(['type' => 'survey', 'title' => 'Autre sujet']);

        $res = $this->getJson('/api/studio/content/public/mentions?q=carburant')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');

        $titles = collect($res->json('data'))->pluck('title')->all();
        $this->assertContains('Le prix des carburants', $titles);
        $this->assertContains('Carburants : un an d’enquête', $titles);
    }

    public function test_mentions_search_can_filter_by_type(): void
    {
        StudioContentFactory::new()->published()->create(['type' => 'statsdata', 'title' => 'Loyers moyens']);
        StudioContentFactory::new()->published()->create(['type' => 'article', 'title' => 'Loyers : décryptage']);

        $this->getJson('/api/studio/content/public/mentions?q=loyers&type=statsdata')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'statsdata');
    }

    public function test_mentions_search_needs_at_least_two_characters(): void
    {
        StudioContentFactory::new()->published()->create(['type' => 'article', 'title' => 'A']);

        $this->getJson('/api/studio/content/public/mentions?q=a')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }
}
