<?php

namespace App\Domain\Channel\Actions;

use App\Domain\Channel\Enums\ChannelUserRoleEnum;
use App\Models\Channel\Channel;
use App\Models\Channel\ChannelUser;

class ToggleChannelFollowAction
{
    /**
     * Bascule l'abonnement de l'utilisateur à la chaîne (pivot channel_users.subscribed_at).
     *
     * @return array{isFollowing: bool, followersCount: int}
     */
    public function execute(Channel $channel, int $userId): array
    {
        $pivot = ChannelUser::firstOrCreate(
            ['channel_id' => $channel->id, 'user_id' => $userId],
            ['role' => ChannelUserRoleEnum::SUBSCRIBER->value],
        );

        if ($pivot->isSubscribed()) {
            $pivot->unsubscribe();
            $isFollowing = false;
        } else {
            $pivot->subscribe();
            $isFollowing = true;
        }

        return [
            'isFollowing' => $isFollowing,
            'followersCount' => $channel->getSubscriberCount(),
        ];
    }
}
