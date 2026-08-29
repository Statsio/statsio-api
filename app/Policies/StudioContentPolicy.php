<?php

namespace App\Policies;

use App\Models\Channel\ChannelUser;
use App\Models\StudioContent;
use App\Models\User\User;

class StudioContentPolicy
{
    /**
     * Peut éditer le contenu : le propriétaire, ou un owner/admin de la chaîne
     * lorsque le contenu est publié au nom d'une chaîne.
     *
     * Extrait de StudioContentController::canEditContent() pour être partagé avec
     * les endpoints de l'assistant IA.
     */
    public function update(User $user, StudioContent $content): bool
    {
        if ($content->user_id === $user->id) {
            return true;
        }

        if ($content->published_as === 'channel' && $content->channel_id) {
            return ChannelUser::where('channel_id', $content->channel_id)
                ->where('user_id', $user->id)
                ->whereIn('role', ['owner', 'admin'])
                ->exists();
        }

        return false;
    }
}
