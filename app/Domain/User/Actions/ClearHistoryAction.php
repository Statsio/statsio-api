<?php

namespace App\Domain\User\Actions;

use App\Models\User\User;
use App\Models\User\UserContentView;

class ClearHistoryAction
{
    /**
     * Efface tout l'historique de consultation de l'utilisateur.
     *
     * @return int nombre de lignes supprimées
     */
    public function execute(User $user): int
    {
        return UserContentView::where('user_id', $user->id)->delete();
    }
}
