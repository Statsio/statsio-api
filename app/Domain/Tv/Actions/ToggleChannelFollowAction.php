<?php

namespace App\Domain\Tv\Actions;

use App\Models\Tv\TvChannel;
use App\Models\Tv\TvChannelFollow;

class ToggleChannelFollowAction
{
    /**
     * @return array{isFollowing: bool, followersCount: int}
     */
    public function execute(string $slug, int $userId): array
    {
        TvChannel::where('slug', $slug)->where('is_active', true)->firstOrFail();

        $existing = TvChannelFollow::where('channel_id', $slug)->where('user_id', $userId)->first();

        if ($existing) {
            $existing->delete();
            $isFollowing = false;
        } else {
            TvChannelFollow::create(['channel_id' => $slug, 'user_id' => $userId]);
            $isFollowing = true;
        }

        return [
            'isFollowing'    => $isFollowing,
            'followersCount' => TvChannelFollow::where('channel_id', $slug)->count(),
        ];
    }
}
