<?php

namespace Tests\Feature\Channel;

use App\Domain\Media\Actions\MediaAction;
use App\Models\Channel\Channel;
use App\Models\Channel\ChannelCategory;
use App\Models\Media;
use App\Models\User\User;
use Database\Factories\ChannelProfileFactory;
use Database\Factories\MediaFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChannelControllerTest extends TestCase
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

    public function test_can_list_channels(): void
    {
        Channel::factory()->withProfile()->count(3)->create();

        $response = $this->getJson('/api/channels');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data']);
    }

    public function test_can_list_channels_filtered_by_search(): void
    {
        Channel::factory()->has(ChannelProfileFactory::new()->state(['name' => 'Zorglub Channel']), 'profile')->create();
        Channel::factory()->has(ChannelProfileFactory::new()->state(['name' => 'Autre Chaine']), 'profile')->create();

        $response = $this->getJson('/api/channels?search=ZORGLUB');

        $response->assertStatus(200)->assertJsonPath('success', true);
        $names = collect($response->json('data.data'))->pluck('profile.name')->all();
        $this->assertSame(['Zorglub Channel'], $names);
    }

    public function test_can_list_channel_categories(): void
    {
        $response = $this->getJson('/api/channels/categories');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_authenticated_user_can_create_channel(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/channels', [
            'name' => 'Mon Canal Test',
            'handle' => 'mon_canal_test',
            'description' => 'Description du canal',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('channels', 1);
    }

    public function test_create_channel_defaults_sub_brand_to_statsio(): void
    {
        $this->withToken($this->token)->postJson('/api/channels', [
            'name' => 'Sans domaine', 'handle' => 'sans_domaine',
        ])->assertStatus(201);

        $this->assertDatabaseHas('channel_profiles', ['handle' => 'sans_domaine', 'sub_brand' => 'statsio']);
    }

    public function test_update_channel_persists_sub_brand(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $channel->users()->attach($this->user->id, ['role' => 'owner', 'subscribed_at' => now()]);

        $this->withToken($this->token)->putJson("/api/channels/{$channel->id}", [
            'sub_brand' => 'medistats',
        ])->assertStatus(200);

        $this->assertDatabaseHas('channel_profiles', ['channel_id' => $channel->id, 'sub_brand' => 'medistats']);
    }

    public function test_channel_categories_expose_and_filter_by_sub_brand(): void
    {
        ChannelCategory::where('slug', 'science')->update(['sub_brand' => 'medistats']);

        $all = collect($this->getJson('/api/channels/categories')->assertOk()->json('data'));
        $this->assertSame('medistats', $all->firstWhere('slug', 'science')['sub_brand']);
        $this->assertSame('all', $all->firstWhere('slug', 'sport')['sub_brand']);

        $tvSlugs = collect($this->getJson('/api/channels/categories?sub_brand=tvstats')->assertOk()->json('data'))
            ->pluck('slug');
        $this->assertContains('sport', $tvSlugs);       // « toutes les marques »
        $this->assertNotContains('science', $tvSlugs);  // propre à Medistats
    }

    public function test_create_channel_persists_custom_color(): void
    {
        // Régression : custom_color_primary/secondary manquaient des rules() de
        // CreateChannelRequest, donc FormRequest::validated() les supprimait
        // silencieusement — la couleur choisie à la création (ChannelConsentModal
        // côté front) n'était jamais enregistrée.
        $response = $this->withToken($this->token)->postJson('/api/channels', [
            'name' => 'Canal Coloré',
            'handle' => 'canal_colore',
            'custom_color_primary' => '#123456',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('channel_profiles', [
            'handle' => 'canal_colore',
            'custom_color_primary' => '#123456',
        ]);
    }

    public function test_unauthenticated_user_cannot_create_channel(): void
    {
        $response = $this->postJson('/api/channels', [
            'name' => 'Canal',
            'handle' => 'canal',
        ]);

        $response->assertStatus(401);
    }

    public function test_can_show_existing_channel(): void
    {
        $channel = Channel::factory()->withProfile()->create();

        $response = $this->getJson("/api/channels/{$channel->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_show_returns_404_for_unknown_channel(): void
    {
        $response = $this->getJson('/api/channels/99999');

        $response->assertStatus(404);
    }

    public function test_authenticated_user_can_list_their_channels(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/channels/my');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_authenticated_user_can_delete_own_channel(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $channel->users()->attach($this->user->id, ['role' => 'owner', 'subscribed_at' => now()]);

        $response = $this->withToken($this->token)->deleteJson("/api/channels/{$channel->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('channels', ['id' => $channel->id]);
    }

    public function test_admin_redactor_and_guest_cannot_delete_channel(): void
    {
        foreach (['admin', 'redactor', 'guest'] as $role) {
            $channel = Channel::factory()->withProfile()->create();
            $user = User::factory()->create();
            $channel->users()->attach($user->id, ['role' => $role, 'subscribed_at' => now()]);
            $token = $user->createToken('test')->plainTextToken;

            $this->withToken($token)->deleteJson("/api/channels/{$channel->id}")
                ->assertStatus(403);
            $this->assertDatabaseHas('channels', ['id' => $channel->id]);
        }
    }

    public function test_non_member_cannot_delete_channel(): void
    {
        $channel = Channel::factory()->withProfile()->create();

        $this->withToken($this->token)->deleteJson("/api/channels/{$channel->id}")
            ->assertStatus(403);
        $this->assertDatabaseHas('channels', ['id' => $channel->id]);
    }

    public function test_delete_returns_403_for_unknown_channel(): void
    {
        // L'autorisation (owner uniquement, voir DeleteChannelRequest) est vérifiée avant
        // même de savoir si la chaîne existe — un non-membre d'une chaîne inconnue reçoit
        // donc 403, pas 404 (comportement standard des FormRequest Laravel).
        $response = $this->withToken($this->token)->deleteJson('/api/channels/99999');

        $response->assertStatus(403);
    }

    public function test_can_list_channel_members(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $channel->users()->attach($this->user->id, ['role' => 'owner', 'subscribed_at' => now()]);

        $response = $this->withToken($this->token)->getJson("/api/channels/{$channel->id}/members");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_can_check_handle_availability(): void
    {
        $response = $this->getJson('/api/channels/check-handle/my-unique-handle');

        $response->assertStatus(200);
    }

    public function test_can_update_channel_profile_fields(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $channel->users()->attach($this->user->id, ['role' => 'owner', 'subscribed_at' => now()]);

        $response = $this->withToken($this->token)->putJson("/api/channels/{$channel->id}", [
            'name' => 'Nom mis à jour',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Nom mis à jour');
    }

    public function test_non_member_cannot_update_channel(): void
    {
        $channel = Channel::factory()->withProfile()->create();

        $this->withToken($this->token)->putJson("/api/channels/{$channel->id}", [
            'name' => 'Nom mis à jour',
        ])->assertStatus(403);
    }

    public function test_update_returns_404_when_channel_has_no_profile(): void
    {
        $channel = Channel::factory()->create();
        $channel->users()->attach($this->user->id, ['role' => 'owner', 'subscribed_at' => now()]);

        $this->withToken($this->token)->putJson("/api/channels/{$channel->id}", [
            'name' => 'Peu importe',
        ])->assertStatus(404);
    }

    public function test_can_update_media(): void
    {
        Storage::fake(config('statsio.media.disk'));
        $channel = Channel::factory()->withProfile()->create();
        $channel->users()->attach($this->user->id, ['role' => 'owner', 'subscribed_at' => now()]);

        $response = $this->withToken($this->token)->post("/api/channels/{$channel->id}/media", [
            'logo' => UploadedFile::fake()->create('logo.png', 10, 'image/png'),
        ]);

        $response->assertStatus(200)->assertJsonPath('success', true);
    }

    public function test_can_update_media_from_library(): void
    {
        // MediaAction stubbé (stockage disque non testable dans ce sandbox) : on
        // vérifie que le média de la bibliothèque est bien rattaché au profil.
        $this->mock(MediaAction::class, function ($mock) {
            $mock->shouldReceive('duplicate')->once()
                ->andReturn(new Media(['path' => 'channels/logos/copy.png', 'type' => 'image/png']));
            $mock->shouldReceive('getUrl')->andReturn('http://localhost/api/media/99/file');
        });

        $channel = Channel::factory()->withProfile()->create();
        $channel->users()->attach($this->user->id, ['role' => 'owner', 'subscribed_at' => now()]);

        $source = MediaFactory::new()->create(['user_id' => $this->user->id]);

        $this->withToken($this->token)->postJson("/api/channels/{$channel->id}/media", [
            'logo_media_id' => $source->id,
        ])->assertStatus(200)->assertJsonPath('success', true);

        $logo = $channel->profile->fresh()->media()->where('collection_name', 'logo')->first();
        $this->assertNotNull($logo);
        $this->assertNotSame($source->id, $logo->id);
        $this->assertSame($this->user->id, $logo->user_id);
    }

    public function test_update_media_ignores_a_library_media_owned_by_someone_else(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $channel->users()->attach($this->user->id, ['role' => 'owner', 'subscribed_at' => now()]);

        $foreign = MediaFactory::new()->create(['user_id' => User::factory()->create()->id]);

        $this->withToken($this->token)->postJson("/api/channels/{$channel->id}/media", [
            'logo_media_id' => $foreign->id,
        ])->assertStatus(200);

        $this->assertNull($channel->profile->fresh()->media()->where('collection_name', 'logo')->first());
    }

    public function test_non_member_cannot_update_media(): void
    {
        Storage::fake(config('statsio.media.disk'));
        $channel = Channel::factory()->withProfile()->create();

        $this->withToken($this->token)->post("/api/channels/{$channel->id}/media", [
            'logo' => UploadedFile::fake()->create('logo.png', 10, 'image/png'),
        ])->assertStatus(403);
    }

    public function test_can_list_subscribers(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $channel->users()->attach($this->user->id, ['role' => 'subscriber', 'subscribed_at' => now()]);

        $response = $this->withToken($this->token)->getJson("/api/channels/{$channel->id}/subscribers");

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_authenticated_user_can_follow_and_unfollow_channel(): void
    {
        $channel = Channel::factory()->withProfile()->create();

        $follow = $this->withToken($this->token)->postJson("/api/channels/{$channel->id}/follow");
        $follow->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.isFollowing', true)
            ->assertJsonPath('data.followersCount', 1);

        $this->assertDatabaseHas('channel_users', [
            'channel_id' => $channel->id,
            'user_id' => $this->user->id,
        ]);

        $unfollow = $this->withToken($this->token)->postJson("/api/channels/{$channel->id}/follow");
        $unfollow->assertStatus(200)
            ->assertJsonPath('data.isFollowing', false)
            ->assertJsonPath('data.followersCount', 0);
    }

    public function test_unauthenticated_user_cannot_follow_channel(): void
    {
        $channel = Channel::factory()->withProfile()->create();

        $this->postJson("/api/channels/{$channel->id}/follow")->assertStatus(401);
    }

    public function test_follow_returns_404_for_unknown_channel(): void
    {
        $this->withToken($this->token)->postJson('/api/channels/99999/follow')->assertStatus(404);
    }

    public function test_list_channels_reports_is_following_for_authenticated_user(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $channel->users()->attach($this->user->id, ['role' => 'subscriber', 'subscribed_at' => now()]);
        Channel::factory()->withProfile()->create();

        $response = $this->withToken($this->token)->getJson('/api/channels');

        $response->assertStatus(200);
        $following = collect($response->json('data.data'))
            ->firstWhere('id', $channel->id)['profile']['is_following'];
        $this->assertTrue($following);
    }

    public function test_stats_returns_views_subscribers_and_team_size(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $channel->users()->attach($this->user->id, ['role' => 'owner', 'subscribed_at' => now()]);

        $response = $this->withToken($this->token)->getJson("/api/channels/{$channel->id}/stats");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.team_member_count', 1)
            ->assertJsonPath('data.subscribers.total', 1)
            ->assertJsonStructure(['data' => ['views', 'subscribers', 'team_member_count', 'lifetime_views']]);
    }

    public function test_record_view_increments_lifetime_and_daily_counts(): void
    {
        $channel = Channel::factory()->withProfile()->create();

        $this->postJson("/api/channels/{$channel->id}/view")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertSame(1, $channel->profile->fresh()->view_count);
        $this->assertDatabaseHas('channel_daily_views', [
            'channel_id' => $channel->id,
            'views_count' => 1,
        ]);
    }

    public function test_record_view_returns_404_for_unknown_channel(): void
    {
        $this->postJson('/api/channels/99999/view')->assertStatus(404);
    }

    // Le cycle suspend/ban/activate/anonymize a déménagé sous /admin/channels/{id}/...
    // (réservé aux admins de la plateforme) — voir AdminEditorialChannelControllerTest.
    // Ces actions n'ont plus de route sous /api/channels/{id}/... du tout.
    public function test_suspend_route_no_longer_exists_under_public_channel_prefix(): void
    {
        $channel = Channel::factory()->withProfile()->create();
        $channel->users()->attach($this->user->id, ['role' => 'owner', 'subscribed_at' => now()]);

        $this->withToken($this->token)->postJson("/api/channels/{$channel->id}/suspend")
            ->assertStatus(404);
    }
}
