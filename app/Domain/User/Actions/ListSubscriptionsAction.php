<?php

namespace App\Domain\User\Actions;

use App\Models\Channel\Channel;
use App\Models\User\User;

class ListSubscriptionsAction
{
    /**
     * Chaînes éditoriales suivies par l'utilisateur (channel_users.subscribed_at non null),
     * de l'abonnement le plus récent au plus ancien.
     *
     * @return array<int,array<string,mixed>>
     */
    public function execute(User $user): array
    {
        return $user->subscribedChannels()
            ->with('profile')
            ->get()
            ->sortByDesc(fn (Channel $channel) => $channel->pivot->subscribed_at)
            ->map(fn (Channel $channel) => [
                'id' => $channel->id,
                'name' => $channel->profile?->name,
                'handle' => $channel->profile?->handle,
                'description' => $channel->profile?->description,
                'logo_url' => $channel->profile?->logo_url,
                'custom_color_primary' => $channel->profile?->custom_color_primary,
                'custom_color_secondary' => $channel->profile?->custom_color_secondary,
                'subscriber_count' => $channel->profile?->subscriber_count ?? 0,
                'subscribed_at' => $channel->pivot->subscribed_at,
            ])
            ->values()
            ->all();
    }
}
