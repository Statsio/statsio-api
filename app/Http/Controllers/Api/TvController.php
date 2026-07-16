<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\Tv\Actions\GetChannelDetailAction;
use App\Domain\Tv\Actions\GetChannelPopularProgramsAction;
use App\Domain\Tv\Actions\GetChannelSchedulesAction;
use App\Domain\Tv\Actions\ToggleBroadcastViewAction;
use App\Domain\Tv\Actions\ToggleChannelFollowAction;
use App\Domain\Tv\Data\CncAudiencesData;
use App\Models\Tv\TvBroadcast;
use App\Models\Tv\TvBroadcastReview;
use App\Models\Tv\TvBroadcastScore;
use App\Models\Tv\TvChannel;
use App\Models\Tv\TvReviewQuestion;
use App\Models\Tv\TvUserView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TvController extends Controller
{
    public function channels(): JsonResponse
    {
        $channels = TvChannel::where('is_active', true)
            ->orderBy('number')
            ->get(['id', 'slug', 'number', 'display_name', 'epg_channel_id', 'logo_url']);

        return response()->json($channels);
    }

    public function epg(
        Request $request,
        GetChannelSchedulesAction $getSchedules,
    ): JsonResponse {
        $date = $request->input('date', now()->setTimezone('Europe/Paris')->format('Y-m-d'));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return response()->json(['error' => 'Invalid date format. Use Y-m-d.'], 422);
        }

        try {
            return response()->json($getSchedules->execute($date));
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }
    }

    public function audiences(): JsonResponse
    {
        return response()->json(CncAudiencesData::get());
    }

    public function broadcast(Request $request, int $id): JsonResponse
    {
        $broadcast = TvBroadcast::with(['program.categories', 'audience'])->findOrFail($id);

        $tz         = new \DateTimeZone('Europe/Paris');
        $startParis = $broadcast->start_at->setTimezone($tz);
        $endParis   = $broadcast->end_at->setTimezone($tz);

        $viewers   = $broadcast->audience?->viewers ?? 0;
        $willWatch = TvUserView::where('broadcast_id', $id)->where('type', 'will_watch')->count();

        $userViewType    = null;
        $userHasReviewed = false;
        if ($request->user()) {
            $userView        = TvUserView::where('user_id', $request->user()->id)->where('broadcast_id', $id)->first();
            $userViewType    = $userView?->type;
            $userHasReviewed = TvBroadcastReview::where('user_id', $request->user()->id)->where('broadcast_id', $id)->exists();
        }

        // Aggregate question scores across ALL broadcasts of same programme
        $programmeId = $broadcast->program_id;
        $broadcastIds = TvBroadcast::where('program_id', $programmeId)->pluck('id');

        $scoresAgg = DB::table('tv_broadcast_question_scores as s')
            ->join('tv_review_questions as q', 'q.id', '=', 's.question_id')
            ->whereIn('s.broadcast_id', $broadcastIds)
            ->where('q.is_active', true)
            ->groupBy('q.id', 'q.label', 'q.sort_order')
            ->orderBy('q.sort_order')
            ->select('q.id as question_id', 'q.label', DB::raw('ROUND(CAST(AVG(s.score) AS DECIMAL(10,1)), 1) as avg_score'), DB::raw('count(*) as vote_count'))
            ->get()
            ->map(fn($r) => [
                'questionId' => $r->question_id,
                'label'      => $r->label,
                'avgScore'   => (float) $r->avg_score,
                'voteCount'  => (int) $r->vote_count,
            ])
            ->values();

        $categories = $broadcast->program->categories->map(fn($c) => [
            'id'    => $c->id,
            'name'  => $c->name,
            'slug'  => $c->slug,
            'color' => $c->color,
        ])->values();

        return response()->json([
            'id'            => $broadcast->id,
            'channelId'     => $broadcast->tv_channel_id,
            'broadcastType' => $broadcast->broadcast_type,
            'startAt'       => $startParis->format('c'),
            'endAt'         => $endParis->format('c'),
            'startTime'     => $startParis->format('H:i'),
            'endTime'       => $endParis->format('H:i'),
            'date'          => $startParis->format('Y-m-d'),
            'durationMin'   => max(1, (int) round(($broadcast->end_at->timestamp - $broadcast->start_at->timestamp) / 60)),
            'program'       => [
                'id'           => $broadcast->program->id,
                'title'        => $broadcast->program->title,
                'type'         => $broadcast->program->type,
                'description'  => $broadcast->program->description,
                'imageUrl'     => $broadcast->program->image_url,
                'youtubeUrl'   => $broadcast->program->youtube_url,
                'isTvstatsPick' => (bool) $broadcast->program->is_tvstats_pick,
                'categories'   => $categories,
            ],
            'audience' => [
                'viewers'           => $viewers,
                'willWatch'         => $willWatch,
                'pda'               => $broadcast->audience?->pda,
                'rank'              => $broadcast->audience?->rank,
                'mediametrieViewers' => $broadcast->audience?->mediametrie_viewers,
            ],
            'scores'         => $scoresAgg,
            'userViewType'   => $userViewType,
            'userHasReviewed' => $userHasReviewed,
        ]);
    }

    /** GET /tv/broadcasts/{id}/schedule — past + upcoming broadcasts for same programme. */
    public function programmeSchedule(int $id): JsonResponse
    {
        $broadcast = TvBroadcast::findOrFail($id);
        $tz        = new \DateTimeZone('Europe/Paris');
        $now       = now();

        $past = TvBroadcast::where('program_id', $broadcast->program_id)
            ->where('id', '!=', $id)
            ->where('end_at', '<', $now)
            ->orderByDesc('start_at')
            ->limit(5)
            ->get();

        $upcoming = TvBroadcast::with('audience')
            ->where('program_id', $broadcast->program_id)
            ->where('id', '!=', $id)
            ->where('start_at', '>=', $now)
            ->orderBy('start_at')
            ->limit(3)
            ->get();

        $format = function (TvBroadcast $b) use ($tz) {
            $start = $b->start_at->setTimezone($tz);
            $end   = $b->end_at->setTimezone($tz);
            return [
                'id'            => $b->id,
                'channelId'     => $b->tv_channel_id,
                'broadcastType' => $b->broadcast_type,
                'startAt'       => $start->format('c'),
                'startTime'     => $start->format('H:i'),
                'endTime'       => $end->format('H:i'),
                'date'          => $start->format('Y-m-d'),
                'viewers'       => $b->audience?->viewers ?? 0,
            ];
        };

        return response()->json([
            'past'     => $past->map($format)->values(),
            'upcoming' => $upcoming->map($format)->values(),
        ]);
    }

    /** GET /tv/broadcasts/{id}/reviews — all reviews for same programme. */
    public function reviews(int $id): JsonResponse
    {
        $broadcast = TvBroadcast::findOrFail($id);

        $reviews = TvBroadcastReview::with('user:id,email')
            ->where('programme_id', $broadcast->program_id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(function ($r) {
                $user    = $r->user;
                $initials = $user
                    ? strtoupper(substr($user->email, 0, 1))
                    : '?';

                return [
                    'id'        => $r->id,
                    'rating'    => $r->rating,
                    'comment'   => $r->comment,
                    'initials'  => $initials,
                    'createdAt' => $r->created_at?->format('c'),
                ];
            })
            ->values();

        // Global average rating across all reviews of the programme
        $avg = TvBroadcastReview::where('programme_id', $broadcast->program_id)
            ->whereNotNull('rating')
            ->avg('rating');

        return response()->json([
            'reviews'    => $reviews,
            'totalCount' => $reviews->count(),
            'avgRating'  => $avg ? round((float) $avg, 1) : null,
        ]);
    }

    /** GET /tv/broadcasts/{id}/questions — active questions for programme categories. */
    public function reviewQuestions(int $id): JsonResponse
    {
        $broadcast = TvBroadcast::with('program.categories')->findOrFail($id);
        $categorySlugs = $broadcast->program->categories->pluck('slug')->toArray();

        $questions = TvReviewQuestion::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->filter(fn($q) => $q->appliesTo($categorySlugs))
            ->map(fn($q) => ['id' => $q->id, 'label' => $q->label, 'description' => $q->description])
            ->values();

        return response()->json($questions);
    }

    /** POST /tv/broadcasts/{id}/review — submit review + question scores. */
    public function submitReview(Request $request, int $id): JsonResponse
    {
        $broadcast = TvBroadcast::with('program')->findOrFail($id);

        $data = $request->validate([
            'rating'             => ['nullable', 'integer', 'min:1', 'max:5'],
            'comment'            => ['nullable', 'string', 'max:1000'],
            'scores'             => ['nullable', 'array'],
            'scores.*.question_id' => ['required_with:scores', 'integer', 'exists:tv_review_questions,id'],
            'scores.*.score'     => ['required_with:scores', 'integer', 'min:1', 'max:5'],
        ]);

        $userId = $request->user()->id;

        DB::transaction(function () use ($data, $broadcast, $userId) {
            // Upsert review
            TvBroadcastReview::updateOrCreate(
                ['user_id' => $userId, 'broadcast_id' => $broadcast->id],
                [
                    'programme_id' => $broadcast->program_id,
                    'rating'       => $data['rating'] ?? null,
                    'comment'      => $data['comment'] ?? null,
                ],
            );

            // Upsert scores
            foreach ($data['scores'] ?? [] as $scoreItem) {
                TvBroadcastScore::updateOrCreate(
                    [
                        'user_id'     => $userId,
                        'broadcast_id' => $broadcast->id,
                        'question_id'  => $scoreItem['question_id'],
                    ],
                    ['score' => $scoreItem['score']],
                );
            }
        });

        // Return updated reviews + scores
        return $this->reviews($id);
    }

    public function toggleView(
        Request $request,
        int $id,
        ToggleBroadcastViewAction $action,
    ): JsonResponse {
        $request->validate(['type' => 'required|in:watched,will_watch']);

        $broadcast = TvBroadcast::findOrFail($id);
        $newType   = $action->execute($request->user(), $broadcast, $request->input('type'));

        $viewers   = $broadcast->fresh()->audience?->viewers ?? 0;
        $willWatch = TvUserView::where('broadcast_id', $id)->where('type', 'will_watch')->count();

        return response()->json([
            'userViewType' => $newType,
            'viewers'      => $viewers,
            'willWatch'    => $willWatch,
        ]);
    }

    /** GET /tv/channels/{slug} — channel banner: stats + follow state. */
    public function channelDetail(Request $request, string $slug, GetChannelDetailAction $action): JsonResponse
    {
        return response()->json($action->execute($slug, $request->user()?->id));
    }

    /** GET /tv/channels/{slug}/popular — programmes ranked by average audience score. */
    public function channelPopularProgrammes(string $slug, GetChannelPopularProgramsAction $action): JsonResponse
    {
        return response()->json($action->execute($slug));
    }

    /** POST /tv/channels/{slug}/follow — toggle follow for the authenticated user. */
    public function toggleChannelFollow(Request $request, string $slug, ToggleChannelFollowAction $action): JsonResponse
    {
        return response()->json($action->execute($slug, $request->user()->id));
    }
}
