<?php

namespace App\Domain\Content\Support;

/**
 * Résout le propriétaire effectif d'un asset (source / média) créé dans le
 * contexte d'un contenu partagé : le propriétaire du contenu, pas le collaborateur.
 */
final class StudioContentAssetOwner
{
    /**
     * @return array{user_id: int, content: \App\Models\StudioContent}|null
     */
    public static function resolveForWrite(
        \App\Models\User\User $actor,
        ?string $contentSlug,
        string $resource = 'studio',
    ): ?array {
        if (! $contentSlug) {
            return null;
        }

        $content = \App\Models\StudioContent::where(function ($q) use ($contentSlug) {
            $q->where('slug', $contentSlug);
            if (is_numeric($contentSlug)) {
                $q->orWhere('id', (int) $contentSlug);
            }
        })->first();

        if (! $content) {
            abort(404, 'Contenu introuvable.');
        }

        $level = \App\Domain\Content\Enums\StudioContentAccessLevelEnum::Write;
        $ok = StudioContentAccess::canAccess($actor, $content, $resource, $level)
            || StudioContentAccess::canAccess($actor, $content, 'sources', $level)
            || StudioContentAccess::canAccess($actor, $content, 'studio', $level);

        abort_unless($ok, 403, 'Vous ne pouvez pas ajouter d\'assets sur ce contenu.');

        return [
            'user_id' => (int) $content->user_id,
            'content' => $content,
        ];
    }
}
