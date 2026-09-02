<?php

namespace App\Domain\User\Actions;

use App\Domain\Channel\Enums\ChannelUserRoleEnum;
use App\Domain\User\Support\StudioContentSummary;
use App\Models\Channel\ChannelUser;
use App\Models\StudioContent;
use App\Models\User\User;
use App\Models\User\UserContentView;
use App\Models\User\UserFavorite;

class SearchAccountAction
{
    private const PER_SECTION = 8;

    /**
     * Recherche transverse dans l'espace compte : favoris, historique, mes contenus.
     *
     * @return array{favorites:array,history:array,contents:array}
     */
    public function execute(User $user, string $query): array
    {
        $query = trim($query);

        if (mb_strlen($query) < 2) {
            return ['favorites' => [], 'history' => [], 'contents' => []];
        }

        // LOWER(...) LIKE plutôt qu'ILIKE (spécifique Postgres) : marche aussi sur sqlite (tests).
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], mb_strtolower($query)).'%';
        $studioMorph = (new StudioContent)->getMorphClass();

        // ── Favoris ────────────────────────────────────────────────────────────
        $favoriteIds = UserFavorite::query()
            ->where('user_id', $user->id)
            ->where('favoritable_type', $studioMorph)
            ->pluck('favoritable_id');

        $favorites = StudioContent::query()
            ->with(StudioContentSummary::eagerLoads())
            ->whereIn('id', $favoriteIds)
            ->whereRaw('LOWER(title) LIKE ?', [$like])
            ->limit(self::PER_SECTION)
            ->get()
            ->map(fn (StudioContent $c) => StudioContentSummary::make($c))
            ->all();

        // ── Historique ─────────────────────────────────────────────────────────
        $history = UserContentView::query()
            ->with(array_map(fn ($r) => "content.$r", StudioContentSummary::eagerLoads()))
            ->where('user_id', $user->id)
            ->whereHas('content', fn ($q) => $q->whereRaw('LOWER(title) LIKE ?', [$like]))
            ->orderByDesc('last_viewed_at')
            ->limit(self::PER_SECTION)
            ->get()
            ->map(fn (UserContentView $v) => StudioContentSummary::make($v->content))
            ->all();

        // ── Mes contenus (perso + chaînes où je suis dans l'équipe) ────────────
        $teamChannelIds = ChannelUser::query()
            ->where('user_id', $user->id)
            ->whereIn('role', array_map(
                fn (ChannelUserRoleEnum $r) => $r->value,
                ChannelUserRoleEnum::getManagementRoles(),
            ))
            ->pluck('channel_id');

        $contents = StudioContent::query()
            ->with(StudioContentSummary::eagerLoads())
            ->whereRaw('LOWER(title) LIKE ?', [$like])
            ->where(fn ($q) => $q
                ->where('user_id', $user->id)
                ->orWhere(fn ($sub) => $sub
                    ->where('published_as', 'channel')
                    ->whereIn('channel_id', $teamChannelIds)))
            ->orderByDesc('updated_at')
            ->limit(self::PER_SECTION)
            ->get()
            ->map(fn (StudioContent $c) => StudioContentSummary::make($c) + [
                'status' => $c->status ?? 'draft',
            ])
            ->all();

        return compact('favorites', 'history', 'contents');
    }
}
