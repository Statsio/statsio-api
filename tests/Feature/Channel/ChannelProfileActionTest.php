<?php

namespace Tests\Feature\Channel;

use App\Domain\Channel\Actions\ChannelProfileAction;
use App\Models\Channel\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelProfileActionTest extends TestCase
{
    use RefreshDatabase;

    private function action(): ChannelProfileAction
    {
        return new ChannelProfileAction();
    }

    public function test_update_profile_only_updates_provided_fields(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $profile = $channel->profile;
        $originalDescription = $profile->description;

        $updated = $this->action()->updateProfile($profile, ['name' => 'Nouveau nom']);

        $this->assertSame('Nouveau nom', $updated->name);
        $this->assertSame($originalDescription, $updated->description);
    }

    public function test_toggle_featured_flips_the_flag(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $profile = $channel->profile;
        $this->assertFalse((bool) $profile->is_featured);

        $updated = $this->action()->toggleFeatured($profile);
        $this->assertTrue((bool) $updated->is_featured);

        $updated = $this->action()->toggleFeatured($updated);
        $this->assertFalse((bool) $updated->is_featured);
    }

    public function test_update_statistics_only_applies_known_keys(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $profile = $channel->profile;

        // 'subscriber_count' n'est pas une colonne réelle (c'est un accessor calculé depuis
        // channel->subscribers()) : updateStatistics() doit l'ignorer silencieusement plutôt
        // que de tenter d'écrire dedans, comme 'unrelated_key'.
        $updated = $this->action()->updateStatistics($profile, [
            'view_count' => 1000,
            'is_featured' => true,
            'subscriber_count' => 999,
            'unrelated_key' => 'ignored',
        ]);

        $this->assertSame(1000, $updated->view_count);
        $this->assertTrue((bool) $updated->is_featured);
        $this->assertSame(0, $updated->subscriber_count);
    }

    public function test_social_links_can_be_added_updated_and_listed(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $profile = $channel->profile;

        $this->action()->addLink($profile, 'website', 'Mon site', 'https://example.com');
        $this->assertCount(1, $this->action()->getAllLinks($profile));

        $this->action()->updateSocialLinks($profile, ['twitter' => 'https://twitter.com/x', 'instagram' => '']);

        $social = $this->action()->getSocialLinks($profile);
        $this->assertSame(['twitter' => 'https://twitter.com/x'], $social);
        // Le lien "website" (non social) survit à updateSocialLinks, qui ne purge que les types sociaux.
        $this->assertCount(2, $this->action()->getAllLinks($profile));
    }

    public function test_update_social_links_replaces_previous_social_links(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $profile = $channel->profile;

        $this->action()->updateSocialLinks($profile, ['twitter' => 'https://twitter.com/old']);
        $this->action()->updateSocialLinks($profile, ['twitter' => 'https://twitter.com/new']);

        $social = $this->action()->getSocialLinks($profile);
        $this->assertSame(['twitter' => 'https://twitter.com/new'], $social);
    }

    public function test_remove_link_deletes_it(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $profile = $channel->profile;
        $this->action()->addLink($profile, 'website', 'Mon site', 'https://example.com');
        $linkId = $this->action()->getAllLinks($profile)->first()->id;

        $this->action()->removeLink($profile, $linkId);

        $this->assertCount(0, $this->action()->getAllLinks($profile));
    }

    public function test_get_profile_by_id_and_get_all_profiles(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        Channel::factory()->withProfile()->create();

        $found = $this->action()->getProfileById($channel->profile->id);
        $this->assertSame($channel->profile->id, $found->id);

        $this->assertNull($this->action()->getProfileById(999999));
        $this->assertCount(2, $this->action()->getAllProfiles()->items());
    }

    public function test_delete_profile_removes_it(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $profileId = $channel->profile->id;

        $this->action()->deleteProfile($channel->profile);

        $this->assertDatabaseMissing('channel_profiles', ['id' => $profileId]);
    }
}
