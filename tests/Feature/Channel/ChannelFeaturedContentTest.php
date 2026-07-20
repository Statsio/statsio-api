<?php

namespace Tests\Feature\Channel;

use App\Models\Channel\Channel;
use App\Models\User\User;
use Database\Factories\StudioContentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelFeaturedContentTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(Channel $channel, string $role): string
    {
        $user = User::factory()->create();
        $channel->users()->attach($user->id, ['role' => $role, 'subscribed_at' => now()]);

        return $user->createToken('test')->plainTextToken;
    }

    public function test_owner_can_set_and_clear_featured_content(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $token = $this->actingAsRole($channel, 'owner');

        $article = StudioContentFactory::new()->published()->create([
            'channel_id' => $channel->id,
            'published_as' => 'channel',
            'type' => 'article',
        ]);

        $response = $this->withToken($token)->putJson("/api/channels/{$channel->id}/featured", [
            'featured_article_id' => $article->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.article.id', (string) $article->id)
            ->assertJsonPath('data.statsdata', null)
            ->assertJsonPath('data.survey', null);

        $this->assertSame($article->id, $channel->profile->fresh()->featured_article_id);

        $clear = $this->withToken($token)->putJson("/api/channels/{$channel->id}/featured", [
            'featured_article_id' => null,
        ]);

        $clear->assertStatus(200)->assertJsonPath('data.article', null);
        $this->assertNull($channel->profile->fresh()->featured_article_id);
    }

    public function test_admin_can_set_featured_content(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $token = $this->actingAsRole($channel, 'admin');

        $dataset = StudioContentFactory::new()->published()->create([
            'channel_id' => $channel->id,
            'published_as' => 'channel',
            'type' => 'statsdata',
        ]);

        $this->withToken($token)->putJson("/api/channels/{$channel->id}/featured", [
            'featured_statsdata_id' => $dataset->id,
        ])->assertStatus(200)->assertJsonPath('data.statsdata.id', (string) $dataset->id);
    }

    public function test_moderator_cannot_set_featured_content(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $token = $this->actingAsRole($channel, 'moderator');

        $article = StudioContentFactory::new()->published()->create([
            'channel_id' => $channel->id,
            'published_as' => 'channel',
            'type' => 'article',
        ]);

        $this->withToken($token)->putJson("/api/channels/{$channel->id}/featured", [
            'featured_article_id' => $article->id,
        ])->assertStatus(403);
    }

    public function test_non_member_cannot_set_featured_content(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $stranger = User::factory()->create();
        $token = $stranger->createToken('test')->plainTextToken;

        $article = StudioContentFactory::new()->published()->create([
            'channel_id' => $channel->id,
            'published_as' => 'channel',
            'type' => 'article',
        ]);

        $this->withToken($token)->putJson("/api/channels/{$channel->id}/featured", [
            'featured_article_id' => $article->id,
        ])->assertStatus(403);
    }

    public function test_cannot_feature_content_from_another_channel(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $otherChannel = Channel::factory()->withProfile()->create();
        $token = $this->actingAsRole($channel, 'owner');

        $foreignArticle = StudioContentFactory::new()->published()->create([
            'channel_id' => $otherChannel->id,
            'published_as' => 'channel',
            'type' => 'article',
        ]);

        $this->withToken($token)->putJson("/api/channels/{$channel->id}/featured", [
            'featured_article_id' => $foreignArticle->id,
        ])->assertStatus(422);
    }

    public function test_cannot_feature_a_draft_content(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $token = $this->actingAsRole($channel, 'owner');

        $draftArticle = StudioContentFactory::new()->create([
            'channel_id' => $channel->id,
            'published_as' => 'channel',
            'type' => 'article',
            'status' => 'draft',
        ]);

        $this->withToken($token)->putJson("/api/channels/{$channel->id}/featured", [
            'featured_article_id' => $draftArticle->id,
        ])->assertStatus(422);
    }

    public function test_cannot_feature_content_of_the_wrong_type(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $token = $this->actingAsRole($channel, 'owner');

        $survey = StudioContentFactory::new()->published()->create([
            'channel_id' => $channel->id,
            'published_as' => 'channel',
            'type' => 'survey',
        ]);

        $this->withToken($token)->putJson("/api/channels/{$channel->id}/featured", [
            'featured_article_id' => $survey->id,
        ])->assertStatus(422);
    }

    public function test_show_includes_featured_content(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $token = $this->actingAsRole($channel, 'owner');

        $article = StudioContentFactory::new()->published()->create([
            'channel_id' => $channel->id,
            'published_as' => 'channel',
            'type' => 'article',
        ]);

        $this->withToken($token)->putJson("/api/channels/{$channel->id}/featured", [
            'featured_article_id' => $article->id,
        ])->assertStatus(200);

        $this->getJson("/api/channels/{$channel->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.profile.featured.article.id', (string) $article->id)
            ->assertJsonPath('data.profile.featured.statsdata', null)
            ->assertJsonPath('data.profile.featured.survey', null);
    }

    public function test_get_featured_content_endpoint_is_public(): void
    {
        $channel = Channel::factory()->withProfile()->create();

        $this->getJson("/api/channels/{$channel->id}/featured")
            ->assertStatus(200)
            ->assertJsonPath('data.article', null)
            ->assertJsonPath('data.statsdata', null)
            ->assertJsonPath('data.survey', null);
    }
}
