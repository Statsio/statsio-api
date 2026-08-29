<?php

namespace App\Domain\User\Actions;

use App\Domain\User\Support\StudioContentSummary;
use App\Models\StudioContent;
use App\Models\User\User;
use App\Models\User\UserFavorite;

class ListFavoritesAction
{
    /**
     * Favoris de l'utilisateur (contenus studio uniquement pour l'instant),
     * du plus récemment ajouté au plus ancien.
     *
     * @return array<int,array<string,mixed>>
     */
    public function execute(User $user): array
    {
        $favorites = UserFavorite::query()
            ->where('user_id', $user->id)
            ->where('favoritable_type', (new StudioContent)->getMorphClass())
            ->orderByDesc('created_at')
            ->get();

        if ($favorites->isEmpty()) {
            return [];
        }

        $contents = StudioContent::query()
            ->with(StudioContentSummary::eagerLoads())
            ->whereIn('id', $favorites->pluck('favoritable_id'))
            ->get()
            ->keyBy('id');

        return $favorites
            ->map(function (UserFavorite $favorite) use ($contents) {
                $content = $contents->get($favorite->favoritable_id);

                if (! $content) {
                    return null;
                }

                return StudioContentSummary::make($content) + [
                    'favorited_at' => $favorite->created_at->toIso8601String(),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
