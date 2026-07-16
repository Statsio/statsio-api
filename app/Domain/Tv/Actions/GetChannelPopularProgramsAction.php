<?php

namespace App\Domain\Tv\Actions;

use App\Models\Tv\TvBroadcast;
use App\Models\Tv\TvBroadcastReview;
use App\Models\Tv\TvProgram;

class GetChannelPopularProgramsAction
{
    /**
     * Programmes ranked by average audience score (viewers) over a trailing window.
     *
     * @return array<array{
     *   broadcastId: int, programId: int, title: string, category: ?string, categoryColor: ?string,
     *   imageUrl: ?string, score: int, rating: ?float,
     * }>
     */
    public function execute(string $slug, int $days = 30, int $limit = 6): array
    {
        $since = now()->subDays($days);

        $rows = TvBroadcast::query()
            ->join('tv_audiences', 'tv_audiences.broadcast_id', '=', 'tv_broadcasts.id')
            ->where('tv_broadcasts.tv_channel_id', $slug)
            ->whereBetween('tv_broadcasts.start_at', [$since, now()])
            ->groupBy('tv_broadcasts.program_id')
            ->orderByDesc('avg_viewers')
            ->limit($limit)
            ->selectRaw('tv_broadcasts.program_id as program_id')
            ->selectRaw('AVG(tv_audiences.viewers) as avg_viewers')
            ->selectRaw('MAX(tv_broadcasts.id) as latest_broadcast_id')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $programIds = $rows->pluck('program_id');

        $programs = TvProgram::with('categories')->whereIn('id', $programIds)->get()->keyBy('id');

        $ratings = TvBroadcastReview::whereIn('programme_id', $programIds)
            ->whereNotNull('rating')
            ->selectRaw('programme_id, AVG(rating) as avg_rating')
            ->groupBy('programme_id')
            ->pluck('avg_rating', 'programme_id');

        return $rows
            ->map(function ($row) use ($programs, $ratings) {
                $program = $programs->get($row->program_id);
                if (!$program) {
                    return null;
                }

                $category = $program->categories->first();
                $rating   = $ratings[$row->program_id] ?? null;

                return [
                    'broadcastId'   => (int) $row->latest_broadcast_id,
                    'programId'     => (int) $row->program_id,
                    'title'         => $program->title,
                    'category'      => $category->name ?? $program->type,
                    'categoryColor' => $category->color ?? null,
                    'imageUrl'      => $program->image_url,
                    'score'         => (int) round($row->avg_viewers),
                    'rating'        => $rating !== null ? round((float) $rating, 1) : null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
