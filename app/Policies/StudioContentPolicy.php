<?php

namespace App\Policies;

use App\Domain\Content\Enums\StudioContentAccessLevelEnum;
use App\Domain\Content\Support\StudioContentAccess;
use App\Models\StudioContent;
use App\Models\User\User;

class StudioContentPolicy
{
    /**
     * Peut éditer le contenu dans le Studio : propriétaire, owner/admin de la
     * chaîne (si publié en chaîne), ou collaborateur avec studio.write.
     */
    public function update(User $user, StudioContent $content): bool
    {
        return StudioContentAccess::canAccess(
            $user,
            $content,
            'studio',
            StudioContentAccessLevelEnum::Write,
        );
    }

    public function view(User $user, StudioContent $content): bool
    {
        return StudioContentAccess::canView($user, $content);
    }

    public function delete(User $user, StudioContent $content): bool
    {
        return StudioContentAccess::isOwner($user, $content);
    }

    public function manageAccess(User $user, StudioContent $content): bool
    {
        return StudioContentAccess::isOwner($user, $content);
    }
}
