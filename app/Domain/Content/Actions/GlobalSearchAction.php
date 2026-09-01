<?php

namespace App\Domain\Content\Actions;

use App\Domain\Channel\Enums\ChannelBadgeEnum;
use App\Domain\Channel\Enums\ChannelStatusEnum;
use App\Domain\Content\Support\StudioContentListing;
use App\Models\Channel\Channel;
use App\Models\StudioContent;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Recherche globale de la modale du header : contenus publiés (articles /
 * statsdata / sondages) + chaînes actives, groupés par type, quelques
 * résultats par groupe avec le total réel pour un lien « voir tout ».
 */
class GlobalSearchAction
{
    /** Résultats renvoyés par groupe (le total complet reste exposé à part). */
    private const PER_GROUP = 6;

    private const MIN_CHARS = 2;

    private const MAX_CHARS = 80;

    /**
     * @return array{query: string, total: int, groups: list<array<string, mixed>>}
     */
    public function execute(string $rawQuery, ?User $viewer = null): array
    {
        $q = mb_substr(trim($rawQuery), 0, self::MAX_CHARS);

        if (mb_strlen($q) < self::MIN_CHARS) {
            return ['query' => $q, 'total' => 0, 'groups' => []];
        }

        $like = '%'.addcslashes(mb_strtolower($q), '%_\\').'%';

        $groups = [];
        $total = 0;

        foreach (['article' => 'Articles', 'statsdata' => 'StatsData', 'survey' => 'Sondages'] as $type => $label) {
            $group = $this->contentGroup($type, $label, $like, $viewer);
            $total += $group['total'];
            $groups[] = $group;
        }

        $channels = $this->channelGroup($like, $viewer);
        $total += $channels['total'];
        $groups[] = $channels;

        return ['query' => $q, 'total' => $total, 'groups' => $groups];
    }

    /**
     * @return array{type: string, label: string, total: int, items: list<array<string, mixed>>}
     */
    private function contentGroup(string $type, string $label, string $like, ?User $viewer): array
    {
        $base = StudioContent::query()
            ->where('status', 'published')
            ->where('type', $type)
            ->where(function (Builder $q) use ($like) {
                $q->whereRaw('LOWER(title) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', [$like])
                    ->orWhereHas('channel.profile', fn (Builder $p) => $p->whereRaw('LOWER(name) LIKE ?', [$like]))
                    ->orWhereHas('user.profile', function (Builder $p) use ($like) {
                        $p->whereRaw('LOWER(first_name) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(last_name) LIKE ?', [$like]);
                    });
            });

        $count = (clone $base)->count();

        $rows = $base
            ->with(StudioContentListing::eagerLoads())
            ->orderByDesc('views_count')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(self::PER_GROUP)
            ->get();

        $favoriteIds = $this->favoriteIds($viewer, $rows->pluck('id')->all());

        $items = $rows
            ->map(fn (StudioContent $c) => StudioContentListing::make($c, isset($favoriteIds[$c->id])))
            ->values()
            ->all();

        return ['type' => $type, 'label' => $label, 'total' => $count, 'items' => $items];
    }

    /**
     * @return array{type: string, label: string, total: int, items: list<array<string, mixed>>}
     */
    private function channelGroup(string $like, ?User $viewer): array
    {
        $base = Channel::query()
            ->where('status', ChannelStatusEnum::ACTIVE->value)
            ->whereHas('profile', function (Builder $p) use ($like) {
                $p->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(handle) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', [$like]);
            });

        $count = (clone $base)->count();

        $rows = $base
            ->with(['profile.channelCategories', 'channelBadges'])
            ->withCount('subscribers')
            ->when($viewer, fn (Builder $q) => $q->withExists([
                'subscribers as is_following' => fn ($s) => $s->where('users.id', $viewer->id),
            ]))
            ->orderByDesc('subscribers_count')
            ->limit(self::PER_GROUP)
            ->get();

        $items = $rows->map(fn (Channel $channel) => [
            'id' => (string) $channel->id,
            'name' => $channel->profile?->name ?? 'Chaîne',
            'handle' => $channel->profile?->handle,
            'description' => $channel->profile?->description,
            'verified' => in_array(ChannelBadgeEnum::VERIFIED->value, $channel->badges ?? [], true),
            'followers_count' => (int) ($channel->subscribers_count ?? 0),
            'categories' => $channel->profile?->channelCategories->pluck('slug')->all() ?? [],
            'logo_url' => $channel->profile?->logo_url,
            'is_following' => (bool) ($channel->is_following ?? false),
        ])->values()->all();

        return ['type' => 'channel', 'label' => 'Chaînes', 'total' => $count, 'items' => $items];
    }

    /**
     * @param  list<int|string>  $ids
     * @return array<int, true>
     */
    private function favoriteIds(?User $viewer, array $ids): array
    {
        if (! $viewer || $ids === []) {
            return [];
        }

        $morph = (new StudioContent)->getMorphClass();

        return $viewer->favorites()
            ->where('favoritable_type', $morph)
            ->whereIn('favoritable_id', $ids)
            ->pluck('favoritable_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
    }
}
