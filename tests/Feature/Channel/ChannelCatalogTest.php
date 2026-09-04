<?php

namespace Tests\Feature\Channel;

use App\Domain\Channel\Enums\ChannelBadgeEnum;
use App\Models\Channel\Badge;
use App\Models\Channel\Channel;
use App\Models\Channel\ChannelCategory;
use App\Models\StudioContent;
use App\Models\User\User;
use Database\Factories\ChannelProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function channel(array $profile = [], bool $verified = false): Channel
    {
        $channel = Channel::factory()
            ->has(ChannelProfileFactory::new()->state($profile), 'profile')
            ->create(['status' => 'active']);

        if ($verified) {
            $channel->channelBadges()->attach(Badge::firstOrCreate(
                ['slug' => ChannelBadgeEnum::VERIFIED->value],
                ['label' => 'Chaîne vérifiée'],
            ));
        }

        return $channel;
    }

    private function publish(Channel $channel, string $type = 'article', ?\DateTimeInterface $at = null): StudioContent
    {
        $content = StudioContent::factory()->published()->create([
            'type' => $type,
            'published_as' => 'channel',
            'channel_id' => $channel->id,
        ]);

        if ($at) {
            $content->forceFill(['updated_at' => $at])->saveQuietly();
        }

        return $content;
    }

    public function test_returns_catalog_shape(): void
    {
        $this->channel(['name' => 'Statsio Énergie']);

        $this->getJson('/api/channels/catalog')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'data' => [['id', 'name', 'handle', 'kind', 'verified', 'categories', 'followers_count', 'publications_count', 'statsdata_count', 'pace']],
                    'meta' => ['total', 'shown', 'per_page', 'has_more'],
                    'facets' => ['kinds', 'themes', 'paces'],
                    'stats' => ['active', 'verified', 'publications_month', 'last_channel_at'],
                    'featured',
                ],
            ]);
    }

    public function test_filters_by_kind(): void
    {
        $this->channel(['name' => 'Une Rédaction', 'kind' => 'redaction']);
        $this->channel(['name' => 'Un Institut', 'kind' => 'institution']);

        $names = collect($this->getJson('/api/channels/catalog?kind=redaction')->json('data.data'))->pluck('name');

        $this->assertSame(['Une Rédaction'], $names->all());
    }

    public function test_filters_by_verified(): void
    {
        $this->channel(['name' => 'Vérifiée'], verified: true);
        $this->channel(['name' => 'Pas vérifiée']);

        $names = collect($this->getJson('/api/channels/catalog?verified=1')->json('data.data'))->pluck('name');

        $this->assertSame(['Vérifiée'], $names->all());
    }

    public function test_filters_by_theme_category(): void
    {
        $withEco = $this->channel(['name' => 'Éco']);
        $withEco->profile->channelCategories()->sync(ChannelCategory::where('slug', 'economie')->pluck('id'));
        $this->channel(['name' => 'Sans catégorie']);

        $names = collect($this->getJson('/api/channels/catalog?category=economie')->json('data.data'))->pluck('name');

        $this->assertSame(['Éco'], $names->all());
    }

    public function test_filters_by_sub_brand(): void
    {
        $this->channel(['name' => 'Chaîne TV', 'sub_brand' => 'tvstats']);
        $this->channel(['name' => 'Chaîne Santé', 'sub_brand' => 'medistats']);
        $this->channel(['name' => 'Chaîne Partout', 'sub_brand' => 'all']);
        $this->channel(['name' => 'Chaîne Statsio', 'sub_brand' => 'statsio']);

        $res = $this->getJson('/api/channels/catalog?sub_brand=tvstats')->assertOk();
        $names = collect($res->json('data.data'))
            ->push($res->json('data.featured'))
            ->filter()
            ->pluck('name')->unique()->sort()->values()->all();
        $this->assertSame(['Chaîne Partout', 'Chaîne TV'], $names);
        $this->assertSame(2, $res->json('data.meta.total'));

        $this->assertSame(4, $this->getJson('/api/channels/catalog')->json('data.meta.total'));
    }

    public function test_search_matches_name_and_handle(): void
    {
        $this->channel(['name' => 'Zorglub Data', 'handle' => 'zorglub']);
        $this->channel(['name' => 'Autre']);

        $names = collect($this->getJson('/api/channels/catalog?q=ZORG')->json('data.data'))->pluck('name');

        $this->assertSame(['Zorglub Data'], $names->all());
    }

    public function test_publication_counts_and_pace_are_derived(): void
    {
        $channel = $this->channel(['name' => 'Prolifique']);
        for ($i = 0; $i < 15; $i++) {
            $this->publish($channel, $i % 3 === 0 ? 'statsdata' : 'article', now()->subDays(2));
        }

        $item = collect($this->getJson('/api/channels/catalog')->json('data.data'))->firstWhere('name', 'Prolifique');

        $this->assertSame(15, $item['publications_count']);
        $this->assertSame(5, $item['statsdata_count']);
        $this->assertSame('jour', $item['pace']);
    }

    public function test_featured_present_without_filter_and_absent_with_filter(): void
    {
        $this->channel(['name' => 'A', 'is_featured' => true]);
        $this->channel(['name' => 'B']);

        $this->assertNotNull($this->getJson('/api/channels/catalog')->json('data.featured'));
        $this->assertNull($this->getJson('/api/channels/catalog?kind=redaction')->json('data.featured'));
    }

    public function test_pagination_reports_has_more(): void
    {
        Channel::factory()->has(ChannelProfileFactory::new(), 'profile')->count(12)->create(['status' => 'active']);

        $meta = $this->getJson('/api/channels/catalog?per_page=9')->json('data.meta');

        $this->assertSame(9, $meta['shown']);
        $this->assertTrue($meta['has_more']);
        $this->assertSame(12, $meta['total']);
    }

    public function test_sort_by_followers(): void
    {
        $small = $this->channel(['name' => 'Petite']);
        $big = $this->channel(['name' => 'Grande']);
        $big->subscribers()->attach(User::factory()->count(3)->create()->pluck('id'), ['subscribed_at' => now()]);

        $names = collect($this->getJson('/api/channels/catalog?sort=followers')->json('data.data'))->pluck('name');

        $this->assertSame('Grande', $names->first());
    }
}
