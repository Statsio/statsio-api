<?php

namespace App\Domain\Tv\Actions;

use App\Models\Tv\TvBroadcast;
use App\Models\Tv\TvChannel;
use App\Models\Tv\TvChannelFollow;

class GetChannelDetailAction
{
    /**
     * @return array{
     *   slug: string, displayName: string, number: int, logoUrl: ?string, description: ?string,
     *   avgScore: ?int, weekProgramCount: int, followersCount: int, isFollowing: bool,
     * }
     */
    public function execute(string $slug, ?int $userId): array
    {
        $channel = TvChannel::where('slug', $slug)->where('is_active', true)->firstOrFail();

        $since = now()->subDays(7);

        $avgViewers = TvBroadcast::query()
            ->join('tv_audiences', 'tv_audiences.broadcast_id', '=', 'tv_broadcasts.id')
            ->where('tv_broadcasts.tv_channel_id', $slug)
            ->whereBetween('tv_broadcasts.start_at', [$since, now()])
            ->avg('tv_audiences.viewers');

        $weekProgramCount = TvBroadcast::where('tv_channel_id', $slug)
            ->where('start_at', '>=', $since)
            ->pluck('program_id')
            ->unique()
            ->count();

        $followersCount = TvChannelFollow::where('channel_id', $slug)->count();

        $isFollowing = $userId !== null
            && TvChannelFollow::where('channel_id', $slug)->where('user_id', $userId)->exists();

        return [
            'slug'             => $channel->slug,
            'displayName'      => $channel->display_name,
            'number'           => $channel->number,
            'logoUrl'          => $channel->logo_url,
            'description'      => $channel->description,
            'avgScore'         => $avgViewers !== null ? (int) round($avgViewers) : null,
            'weekProgramCount' => $weekProgramCount,
            'followersCount'   => $followersCount,
            'isFollowing'      => $isFollowing,
        ];
    }
}
