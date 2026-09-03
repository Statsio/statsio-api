<?php

namespace App\Domain\User\Support;

use App\Models\StudioContent;

/**
 * Représentation légère et partagée d'un contenu studio pour les listes du compte
 * (favoris, historique, recherche). Volontairement plus mince que
 * StudioContentController::format (pas de blocs, pas de datasets).
 */
class StudioContentSummary
{
    /**
     * @return array<string,mixed>
     */
    public static function make(StudioContent $content): array
    {
        $isChannel = $content->published_as === 'channel' && $content->channel;
        $profile = $content->user?->profile;
        $authorName = trim(($profile?->first_name ?? '').' '.($profile?->last_name ?? '')) ?: 'Anonyme';

        return [
            'id' => (string) $content->id,
            'slug' => $content->slug,
            'title' => $content->title,
            'type' => $content->type ?? 'statsdata',
            'thumbnail_url' => $content->getFirstMediaUrl('thumbnail') ?: null,
            'channel' => $isChannel ? [
                'id' => $content->channel->id,
                'name' => $content->channel->profile?->name,
                'handle' => $content->channel->profile?->handle,
                'logo_url' => $content->channel->profile?->logo_url,
                'custom_color_primary' => $content->channel->profile?->custom_color_primary,
                'custom_color_secondary' => $content->channel->profile?->custom_color_secondary,
            ] : null,
            'author' => $isChannel ? null : ['name' => $authorName],
        ];
    }

    /** Relations à charger avant d'appeler make() sur une collection. */
    public static function eagerLoads(): array
    {
        return ['user.profile', 'channel.profile', 'media'];
    }
}
