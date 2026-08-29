<?php

namespace App\Domain\User\Actions;

use App\Domain\Channel\Enums\ChannelUserRoleEnum;
use App\Models\StudioContent;
use App\Models\User\User;

class MeAction
{
    /**
     * Retourne l'utilisateur avec ses relations + les compteurs affichés dans la
     * carte profil de l'espace compte (sidebar).
     */
    public function execute(User $user): User
    {
        $user->load([
            'profile.gender',
            'profile.ageRange',
            'profile.socioProfessionalCategory',
            'profile.educationLevel',
            'profile.employmentStatus',
            'profile.maritalStatus',
        ]);

        $teamChannelIds = $user->channels()
            ->wherePivotIn('role', array_map(
                fn (ChannelUserRoleEnum $r) => $r->value,
                ChannelUserRoleEnum::getManagementRoles(),
            ))
            ->pluck('channels.id');

        $user->setAttribute('counts', [
            'subscriptions' => $user->subscribedChannels()->count(),
            'favorites' => $user->favorites()
                ->where('favoritable_type', (new StudioContent)->getMorphClass())
                ->count(),
            'channels' => $user->channels()
                ->wherePivotIn('role', [
                    ChannelUserRoleEnum::OWNER->value,
                    ChannelUserRoleEnum::ADMIN->value,
                ])
                ->count(),
            'contents' => StudioContent::query()
                ->where('user_id', $user->id)
                ->orWhere(fn ($q) => $q
                    ->where('published_as', 'channel')
                    ->whereIn('channel_id', $teamChannelIds))
                ->count(),
        ]);

        return $user;
    }
}
