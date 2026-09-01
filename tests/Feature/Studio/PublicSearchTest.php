<?php

namespace Tests\Feature\Studio;

use App\Models\Channel\Channel;
use Database\Factories\ChannelProfileFactory;
use Database\Factories\StudioContentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_groups_published_content_and_channels(): void
    {
        StudioContentFactory::new()->published()->create(['type' => 'article', 'title' => 'Carburants : un an d’enquête']);
        StudioContentFactory::new()->published()->create(['type' => 'statsdata', 'title' => 'Le prix des carburants']);
        StudioContentFactory::new()->published()->create(['type' => 'survey', 'title' => 'Faut-il taxer les carburants ?']);
        StudioContentFactory::new()->create(['type' => 'article', 'title' => 'Carburants brouillon non publié']);
        StudioContentFactory::new()->published()->create(['type' => 'article', 'title' => 'Sujet sans rapport']);

        Channel::factory()
            ->has(ChannelProfileFactory::new()->state(['name' => 'Observatoire des carburants']), 'profile')
            ->create(['status' => 'active']);

        $res = $this->getJson('/api/studio/content/public/search?q=carburant')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('total', 4);

        $groups = collect($res->json('groups'))->keyBy('type');

        $this->assertSame(1, $groups['article']['total']);
        $this->assertSame(1, $groups['statsdata']['total']);
        $this->assertSame(1, $groups['survey']['total']);
        $this->assertSame(1, $groups['channel']['total']);
        $this->assertSame('Le prix des carburants', $groups['statsdata']['items'][0]['title']);
        $this->assertSame('Observatoire des carburants', $groups['channel']['items'][0]['name']);
    }

    public function test_search_needs_at_least_two_characters(): void
    {
        StudioContentFactory::new()->published()->create(['type' => 'article', 'title' => 'A']);

        $this->getJson('/api/studio/content/public/search?q=a')
            ->assertStatus(200)
            ->assertJsonPath('total', 0)
            ->assertJsonPath('groups', []);
    }

    public function test_search_caps_results_per_group_but_keeps_real_total(): void
    {
        StudioContentFactory::new()->published()->count(9)->create(['type' => 'article', 'title' => 'Dossier climat']);

        $res = $this->getJson('/api/studio/content/public/search?q=climat')
            ->assertStatus(200);

        $article = collect($res->json('groups'))->firstWhere('type', 'article');

        $this->assertSame(9, $article['total']);
        $this->assertCount(6, $article['items']);
    }
}
