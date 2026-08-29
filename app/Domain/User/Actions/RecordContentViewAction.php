<?php

namespace App\Domain\User\Actions;

use App\Models\StudioContent;
use App\Models\User\User;
use App\Models\User\UserContentView;

class RecordContentViewAction
{
    /**
     * Enregistre (ou met à jour) la consultation d'un contenu par l'utilisateur.
     * Upsert sur la paire (user, contenu) : on incrémente le compteur et on
     * rafraîchit last_viewed_at plutôt que d'empiler des lignes.
     */
    public function execute(User $user, StudioContent $content, ?int $progress = null): UserContentView
    {
        $view = UserContentView::firstOrNew([
            'user_id' => $user->id,
            'studio_content_id' => $content->id,
        ]);

        $view->last_viewed_at = now();
        $view->view_count = ($view->exists ? $view->view_count : 0) + 1;

        if ($progress !== null) {
            // On ne fait jamais reculer la progression.
            $view->progress = max((int) $view->progress, min(100, max(0, $progress)));
        }

        $view->save();

        return $view;
    }
}
