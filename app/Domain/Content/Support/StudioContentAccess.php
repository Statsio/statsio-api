<?php

namespace App\Domain\Content\Support;

use App\Domain\Content\Enums\StudioContentAccessLevelEnum;
use App\Domain\Content\Enums\StudioContentAccessResourceEnum;
use App\Models\Channel\ChannelUser;
use App\Models\Studio\StudioContentCollaborator;
use App\Models\StudioContent;
use App\Models\User\User;

/**
 * Résolution d'accès contenu : propriétaire, collaborateur, ou owner/admin de chaîne.
 */
final class StudioContentAccess
{
    public static function isOwner(User $user, StudioContent $content): bool
    {
        return (int) $content->user_id === (int) $user->id;
    }

    public static function isChannelAdmin(User $user, StudioContent $content): bool
    {
        if ($content->published_as !== 'channel' || ! $content->channel_id) {
            return false;
        }

        return ChannelUser::where('channel_id', $content->channel_id)
            ->where('user_id', $user->id)
            ->whereIn('role', ['owner', 'admin'])
            ->exists();
    }

    public static function collaborator(User $user, StudioContent $content): ?StudioContentCollaborator
    {
        if ($content->relationLoaded('collaborators')) {
            return $content->collaborators->firstWhere('user_id', $user->id);
        }

        return StudioContentCollaborator::where('studio_content_id', $content->id)
            ->where('user_id', $user->id)
            ->first();
    }

    public static function canView(User $user, StudioContent $content): bool
    {
        if (self::isOwner($user, $content) || self::isChannelAdmin($user, $content)) {
            return true;
        }

        $collab = self::collaborator($user, $content);

        return $collab?->hasAnyAccess() ?? false;
    }

    public static function canAccess(
        User $user,
        StudioContent $content,
        string $resource,
        StudioContentAccessLevelEnum $needed,
    ): bool {
        if (self::isOwner($user, $content) || self::isChannelAdmin($user, $content)) {
            return true;
        }

        $collab = self::collaborator($user, $content);

        return $collab?->canAccess($resource, $needed) ?? false;
    }

    /**
     * Permissions effectives pour le front (owner / channel admin = write partout).
     *
     * @return array{is_owner: bool, permissions: array<string, string>}
     */
    public static function payload(User $user, StudioContent $content): array
    {
        $isOwner = self::isOwner($user, $content);
        $fullWrite = $isOwner || self::isChannelAdmin($user, $content);

        if ($fullWrite) {
            $permissions = [];
            foreach (StudioContentAccessResourceEnum::values() as $resource) {
                $permissions[$resource] = StudioContentAccessLevelEnum::Write->value;
            }

            return ['is_owner' => $isOwner, 'permissions' => $permissions];
        }

        $collab = self::collaborator($user, $content);

        return [
            'is_owner' => false,
            'permissions' => $collab?->normalizedPermissions() ?? self::emptyPermissions(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function emptyPermissions(): array
    {
        $out = [];
        foreach (StudioContentAccessResourceEnum::values() as $resource) {
            $out[$resource] = StudioContentAccessLevelEnum::None->value;
        }

        return $out;
    }

    /**
     * Normalise et valide une map ressource → niveau depuis une requête.
     *
     * @param  array<string, mixed>  $requested
     * @return array<string, string>
     */
    public static function normalizePermissions(array $requested): array
    {
        $out = self::emptyPermissions();
        $validLevels = StudioContentAccessLevelEnum::values();

        foreach (StudioContentAccessResourceEnum::values() as $resource) {
            if (! array_key_exists($resource, $requested)) {
                continue;
            }
            $level = (string) $requested[$resource];
            if (in_array($level, $validLevels, true)) {
                $out[$resource] = $level;
            }
        }

        return $out;
    }

    public static function hasAnyNonNone(array $permissions): bool
    {
        foreach ($permissions as $level) {
            if ($level !== StudioContentAccessLevelEnum::None->value) {
                return true;
            }
        }

        return false;
    }
}
