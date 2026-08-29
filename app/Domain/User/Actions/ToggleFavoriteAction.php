<?php

namespace App\Domain\User\Actions;

use App\Models\StudioContent;
use App\Models\User\User;
use App\Models\User\UserFavorite;

class ToggleFavoriteAction
{
    /**
     * Ajoute / retire un contenu studio des favoris de l'utilisateur.
     *
     * @return bool true si le contenu est désormais en favori, false s'il vient d'être retiré
     */
    public function execute(User $user, StudioContent $content): bool
    {
        $existing = UserFavorite::query()
            ->where('user_id', $user->id)
            ->where('favoritable_type', $content->getMorphClass())
            ->where('favoritable_id', $content->getKey())
            ->first();

        if ($existing) {
            $existing->delete();

            return false;
        }

        UserFavorite::create([
            'user_id' => $user->id,
            'favoritable_type' => $content->getMorphClass(),
            'favoritable_id' => $content->getKey(),
        ]);

        return true;
    }
}
