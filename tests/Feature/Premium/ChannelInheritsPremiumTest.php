<?php

namespace Tests\Feature\Premium;

use App\Models\Channel\Channel;
use App\Models\PremiumBlockType;
use App\Models\User\User;
use Database\Factories\StudioContentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Une chaîne hérite du Premium de son propriétaire : tout contenu publié « au nom de la
 * chaîne » (published_as=channel) suit ce statut, même si son auteur (rédacteur, invité…)
 * n'a pas d'abonnement personnel — voir PremiumBlockGate::isEffectivelyPremium().
 */
class ChannelInheritsPremiumTest extends TestCase
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

    private function channelWithPremiumOwner(): Channel
    {
        $owner = User::factory()->premium()->create();
        $channel = Channel::factory()->withProfile()->create();
        $channel->users()->attach($owner->id, ['role' => 'owner', 'subscribed_at' => now()]);

        return $channel;
    }

    public function test_non_premium_redactor_can_add_a_premium_block_to_content_published_for_a_premium_channel(): void
    {
        $channel = $this->channelWithPremiumOwner();
        $redactor = User::factory()->create();
        $channel->users()->attach($redactor->id, ['role' => 'redactor', 'subscribed_at' => now()]);

        $content = StudioContentFactory::new()->create([
            'user_id' => $redactor->id,
            'published_as' => 'channel',
            'channel_id' => $channel->id,
        ]);

        $this->withToken($redactor->createToken('t')->plainTextToken)
            ->putJson("/api/studio/content/{$content->slug}", ['blocks' => [$this->mapBlock()]])
            ->assertStatus(200);
    }

    public function test_non_premium_redactor_cannot_add_a_premium_block_for_a_freemium_channel(): void
    {
        $owner = User::factory()->create(); // pas premium
        $channel = Channel::factory()->withProfile()->create();
        $channel->users()->attach($owner->id, ['role' => 'owner', 'subscribed_at' => now()]);
        $redactor = User::factory()->create();
        $channel->users()->attach($redactor->id, ['role' => 'redactor', 'subscribed_at' => now()]);

        $content = StudioContentFactory::new()->create([
            'user_id' => $redactor->id,
            'published_as' => 'channel',
            'channel_id' => $channel->id,
        ]);

        $this->withToken($redactor->createToken('t')->plainTextToken)
            ->putJson("/api/studio/content/{$content->slug}", ['blocks' => [$this->mapBlock()]])
            ->assertStatus(403);
    }

    public function test_redactor_loses_the_inherited_premium_once_the_owner_downgrades(): void
    {
        $channel = $this->channelWithPremiumOwner();
        $owner = $channel->users()->wherePivot('role', 'owner')->first();
        $redactor = User::factory()->create();
        $channel->users()->attach($redactor->id, ['role' => 'redactor', 'subscribed_at' => now()]);

        $content = StudioContentFactory::new()->create([
            'user_id' => $redactor->id,
            'published_as' => 'channel',
            'channel_id' => $channel->id,
            'blocks' => [$this->mapBlock()],
        ]);

        // Le propriétaire perd le Premium (résiliation, webhook subscription.deleted…).
        $owner->update(['offer_id' => null]);
        $token = $redactor->createToken('t')->plainTextToken;

        // Le bloc premium déjà en place reste grandfathéré : supprimable, publiable...
        $this->withToken($token)
            ->putJson("/api/studio/content/{$content->slug}", ['title' => 'Retitré'])
            ->assertStatus(200);
        $this->withToken($token)
            ->postJson("/api/studio/content/{$content->slug}/publish")
            ->assertStatus(200);

        // ... mais ne peut plus être modifié, et aucun nouveau bloc premium ne peut être ajouté.
        $this->withToken($token)
            ->putJson("/api/studio/content/{$content->slug}", [
                'blocks' => [$this->mapBlock(['config' => ['title' => 'Changé']])],
            ])
            ->assertStatus(403);

        $secondContent = StudioContentFactory::new()->create([
            'user_id' => $redactor->id,
            'published_as' => 'channel',
            'channel_id' => $channel->id,
        ]);
        $this->withToken($token)
            ->putJson("/api/studio/content/{$secondContent->slug}", ['blocks' => [$this->mapBlock(['id' => 'b2'])]])
            ->assertStatus(403);
    }
}
