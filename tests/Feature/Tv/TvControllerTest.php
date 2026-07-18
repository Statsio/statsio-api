<?php

namespace Tests\Feature\Tv;

use App\Models\Tv\TvAudience;
use App\Models\Tv\TvChannel;
use App\Models\Tv\TvChannelFollow;
use App\Models\Tv\TvReviewQuestion;
use App\Models\User\User;
use Database\Factories\TvBroadcastFactory;
use Database\Factories\TvChannelFactory;
use Database\Factories\TvProgramFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TvControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_active_tv_channels(): void
    {
        TvChannel::query()->delete();
        TvChannelFactory::new()->count(3)->create(['is_active' => true]);
        TvChannelFactory::new()->count(2)->create(['is_active' => false]);

        $response = $this->getJson('/api/tv/channels');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json());
    }

    public function test_channels_are_ordered_by_number(): void
    {
        TvChannel::query()->delete();
        TvChannelFactory::new()->create(['number' => 3]);
        TvChannelFactory::new()->create(['number' => 1]);
        TvChannelFactory::new()->create(['number' => 2]);

        $response = $this->getJson('/api/tv/channels');

        $numbers = array_column($response->json(), 'number');
        $this->assertSame([1, 2, 3], $numbers);
    }

    public function test_epg_rejects_invalid_date_format(): void
    {
        $response = $this->getJson('/api/tv/epg?date=not-a-date');

        $response->assertStatus(422);
    }

    public function test_can_get_audiences(): void
    {
        $response = $this->getJson('/api/tv/audiences');

        $response->assertStatus(200);
    }

    public function test_can_get_broadcast_details(): void
    {
        $broadcast = TvBroadcastFactory::new()->create();

        $response = $this->getJson("/api/tv/broadcasts/{$broadcast->id}");

        $response->assertStatus(200)
                 ->assertJsonStructure(['id', 'startAt', 'endAt', 'program']);
    }

    public function test_broadcast_returns_404_for_unknown_id(): void
    {
        $response = $this->getJson('/api/tv/broadcasts/99999');

        $response->assertStatus(404);
    }

    public function test_authenticated_user_can_toggle_broadcast_view(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $broadcast = TvBroadcastFactory::new()->create();

        $response = $this->withToken($token)->postJson("/api/tv/broadcasts/{$broadcast->id}/view", [
            'type' => 'will_watch',
        ]);

        $response->assertStatus(200);
    }

    public function test_toggling_watched_increments_viewers_and_toggling_again_removes_it(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $broadcast = TvBroadcastFactory::new()->create();

        $response = $this->withToken($token)->postJson("/api/tv/broadcasts/{$broadcast->id}/view", ['type' => 'watched']);
        $response->assertStatus(200)->assertJsonPath('userViewType', 'watched')->assertJsonPath('viewers', 1);

        // Même type une deuxième fois : bascule (retire la vue) et décrémente les vues.
        $response = $this->withToken($token)->postJson("/api/tv/broadcasts/{$broadcast->id}/view", ['type' => 'watched']);
        $response->assertStatus(200)->assertJsonPath('userViewType', null)->assertJsonPath('viewers', 0);
    }

    public function test_switching_view_type_from_watched_to_will_watch_decrements_viewers(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $broadcast = TvBroadcastFactory::new()->create();

        $this->withToken($token)->postJson("/api/tv/broadcasts/{$broadcast->id}/view", ['type' => 'watched']);

        $response = $this->withToken($token)->postJson("/api/tv/broadcasts/{$broadcast->id}/view", ['type' => 'will_watch']);

        $response->assertStatus(200)
            ->assertJsonPath('userViewType', 'will_watch')
            ->assertJsonPath('viewers', 0)
            ->assertJsonPath('willWatch', 1);
    }

    public function test_unauthenticated_user_cannot_toggle_view(): void
    {
        $broadcast = TvBroadcastFactory::new()->create();

        $response = $this->postJson("/api/tv/broadcasts/{$broadcast->id}/view", [
            'status' => 'will_watch',
        ]);

        $response->assertStatus(401);
    }

    public function test_can_get_broadcast_reviews(): void
    {
        $broadcast = TvBroadcastFactory::new()->create();

        $response = $this->getJson("/api/tv/broadcasts/{$broadcast->id}/reviews");

        $response->assertStatus(200);
    }

    public function test_epg_returns_schedules_from_database_when_already_stored(): void
    {
        $date = now()->setTimezone('Europe/Paris')->format('Y-m-d');
        $channel = TvChannelFactory::new()->create();
        $program = TvProgramFactory::new()->create(['tv_channel_id' => $channel->slug, 'type' => 'series']);
        $start = \Carbon\Carbon::now('Europe/Paris')->setTime(0, 1, 0);
        $broadcast = TvBroadcastFactory::new()->create([
            'tv_channel_id' => $channel->slug,
            'program_id' => $program->id,
            'start_at' => $start,
            'end_at' => (clone $start)->addHour(),
        ]);
        TvAudience::create(['broadcast_id' => $broadcast->id, 'viewers' => 123]);

        $response = $this->getJson("/api/tv/epg?date={$date}");

        $response->assertStatus(200);
        $schedule = collect($response->json())->firstWhere('channelId', $channel->slug);
        $this->assertNotNull($schedule);
        $this->assertSame($program->title, $schedule['programmes'][0]['title']);
        $this->assertSame(['series'], $schedule['programmes'][0]['genres']);
    }

    public function test_programme_schedule_returns_past_and_upcoming_broadcasts(): void
    {
        $program = TvProgramFactory::new()->create();
        $past = TvBroadcastFactory::new()->create([
            'program_id' => $program->id,
            'start_at' => now()->subDays(2),
            'end_at' => now()->subDays(2)->addHour(),
        ]);
        $current = TvBroadcastFactory::new()->create([
            'program_id' => $program->id,
            'start_at' => now()->subMinutes(10),
            'end_at' => now()->addMinutes(50),
        ]);
        $future = TvBroadcastFactory::new()->create([
            'program_id' => $program->id,
            'start_at' => now()->addDays(2),
            'end_at' => now()->addDays(2)->addHour(),
        ]);

        $response = $this->getJson("/api/tv/broadcasts/{$current->id}/schedule");

        $response->assertStatus(200);
        $this->assertSame($past->id, $response->json('past.0.id'));
        $this->assertSame($future->id, $response->json('upcoming.0.id'));
    }

    public function test_review_questions_only_returns_questions_applying_to_no_category(): void
    {
        // Les 7 questions par défaut sont seedées par la migration : 4 sans catégorie
        // (s'appliquent à tout) + 3 restreintes à des catégories spécifiques.
        $broadcast = TvBroadcastFactory::new()->create();

        $response = $this->getJson("/api/tv/broadcasts/{$broadcast->id}/questions");

        $response->assertStatus(200);
        $this->assertCount(4, $response->json());
    }

    public function test_authenticated_user_can_submit_review_with_scores(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $broadcast = TvBroadcastFactory::new()->create();
        $question = TvReviewQuestion::first();

        $response = $this->withToken($token)->postJson("/api/tv/broadcasts/{$broadcast->id}/review", [
            'rating' => 4,
            'comment' => 'Top programme',
            'scores' => [['question_id' => $question->id, 'score' => 5]],
        ]);

        // avgRating=4.0 est un entier une fois arrondi : json_encode() l'émet sans décimale,
        // donc json_decode() le redonne en int (voir le même cas dans AdminBroadcastControllerTest).
        $response->assertStatus(200)
            ->assertJsonPath('totalCount', 1)
            ->assertJsonPath('avgRating', 4);

        $this->assertDatabaseHas('tv_broadcast_reviews', [
            'user_id' => $user->id,
            'broadcast_id' => $broadcast->id,
            'rating' => 4,
        ]);
        $this->assertDatabaseHas('tv_broadcast_question_scores', [
            'user_id' => $user->id,
            'broadcast_id' => $broadcast->id,
            'question_id' => $question->id,
            'score' => 5,
        ]);
    }

    public function test_channel_detail_returns_stats_and_follow_state(): void
    {
        $channel = TvChannelFactory::new()->create();

        $response = $this->getJson("/api/tv/channels/{$channel->slug}");

        $response->assertStatus(200)
            ->assertJsonPath('slug', $channel->slug)
            ->assertJsonPath('isFollowing', false);
    }

    public function test_channel_popular_programmes_returns_empty_when_no_recent_broadcasts(): void
    {
        $channel = TvChannelFactory::new()->create();

        $response = $this->getJson("/api/tv/channels/{$channel->slug}/popular");

        $response->assertStatus(200)->assertJson([]);
    }

    public function test_channel_popular_programmes_ranks_by_average_viewers(): void
    {
        $channel = TvChannelFactory::new()->create();
        $popular = TvProgramFactory::new()->create(['tv_channel_id' => $channel->slug, 'title' => 'Le plus populaire']);
        $lessPopular = TvProgramFactory::new()->create(['tv_channel_id' => $channel->slug, 'title' => 'Moins populaire']);

        $popularBroadcast = TvBroadcastFactory::new()->create([
            'tv_channel_id' => $channel->slug,
            'program_id' => $popular->id,
            'start_at' => now()->subDays(2),
            'end_at' => now()->subDays(2)->addHour(),
        ]);
        TvAudience::create(['broadcast_id' => $popularBroadcast->id, 'viewers' => 5000]);

        $lessPopularBroadcast = TvBroadcastFactory::new()->create([
            'tv_channel_id' => $channel->slug,
            'program_id' => $lessPopular->id,
            'start_at' => now()->subDays(3),
            'end_at' => now()->subDays(3)->addHour(),
        ]);
        TvAudience::create(['broadcast_id' => $lessPopularBroadcast->id, 'viewers' => 500]);

        $response = $this->getJson("/api/tv/channels/{$channel->slug}/popular");

        $response->assertStatus(200);
        $titles = collect($response->json())->pluck('title')->all();
        $this->assertSame(['Le plus populaire', 'Moins populaire'], $titles);
        $this->assertSame(5000, $response->json('0.score'));
    }

    public function test_authenticated_user_can_follow_and_unfollow_channel(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $channel = TvChannelFactory::new()->create();

        $this->withToken($token)->postJson("/api/tv/channels/{$channel->slug}/follow")
            ->assertStatus(200)
            ->assertJsonPath('isFollowing', true)
            ->assertJsonPath('followersCount', 1);

        $this->assertDatabaseHas('tv_channel_follows', ['channel_id' => $channel->slug, 'user_id' => $user->id]);

        $this->withToken($token)->postJson("/api/tv/channels/{$channel->slug}/follow")
            ->assertStatus(200)
            ->assertJsonPath('isFollowing', false)
            ->assertJsonPath('followersCount', 0);
    }
}
