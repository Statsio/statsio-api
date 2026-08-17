<?php

namespace App\Domain\Channel\Actions;

use App\Domain\Channel\Enums\ChannelBadgeEnum;
use App\Domain\Channel\Enums\ChannelKindEnum;
use App\Domain\Channel\Enums\ChannelStatusEnum;
use App\Models\Channel\Channel;
use App\Models\Channel\ChannelCategory;
use App\Models\StudioContent;
use Illuminate\Support\Collection;

/**
 * Annuaire « v2 » des chaînes (page /chaines) : renvoie une réponse de la même
 * forme que le catalogue articles/statsdata — data / meta / facets / stats /
 * featured — mais dédiée aux chaînes.
 *
 * Le volume de chaînes actives est faible : on charge l'univers filtré une fois
 * puis on facette / trie / pagine en PHP (comme StudioContentController::indexPublic).
 */
class ChannelCatalogAction
{
    /** Fenêtre (jours) servant à dériver le rythme de publication. */
    private const RECENT_WINDOW_DAYS = 30;

    /** Seuils de publications sur la fenêtre pour classer la cadence. */
    private const PACE_DAILY_MIN = 12;

    private const PACE_WEEKLY_MIN = 3;

    /** Poids des publications récentes dans le score de tendance. */
    private const TREND_RECENCY_WEIGHT = 3;

    private const DEFAULT_PER_PAGE = 9;

    private const MAX_PER_PAGE = 60;

    /**
     * @param  array{q?: ?string, kind?: ?string, category?: ?string, pace?: ?string, sort?: ?string, verified?: mixed, followed?: mixed, per_page?: mixed}  $filters
     */
    public function getCatalog(array $filters, ?int $userId = null): array
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $kind = $this->cleanEnum($filters['kind'] ?? null);
        $category = $this->cleanEnum($filters['category'] ?? null);
        $pace = $this->cleanEnum($filters['pace'] ?? null);
        $sort = in_array($filters['sort'] ?? null, ['trend', 'recent', 'followers'], true) ? $filters['sort'] : 'trend';
        $verified = filter_var($filters['verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $followed = filter_var($filters['followed'] ?? false, FILTER_VALIDATE_BOOLEAN) && $userId !== null;
        $perPage = max(1, min(self::MAX_PER_PAGE, (int) ($filters['per_page'] ?? self::DEFAULT_PER_PAGE)));

        // Filtres « structurels » (recherche plein-texte, vérifiées, abonnements) : appliqués
        // en base. Les filtres à facettes (type / thème / rythme) sont appliqués en PHP pour
        // pouvoir calculer des compteurs de facettes cohérents.
        $universe = $this->baseQuery($userId)
            ->when($q !== '', fn ($query) => $this->applySearch($query, $q))
            ->when($verified, fn ($query) => $query->whereHas('channelBadges', fn ($b) => $b->where('slug', ChannelBadgeEnum::VERIFIED->value)))
            ->when($followed, fn ($query) => $query->whereHas('subscribers', fn ($s) => $s->where('users.id', $userId)))
            ->get();

        $aggregates = $this->publicationAggregates($universe->pluck('id')->all());
        $items = $universe->map(fn (Channel $channel) => $this->formatItem($channel, $aggregates))->values();

        $facets = [
            'kinds' => $this->facet($items, 'kind', $this->matcher($category, $pace, null), fn ($i) => [$i['kind']]),
            'themes' => $this->facet($items, 'theme', $this->matcher(null, $pace, $kind), fn ($i) => $i['categories']),
            'paces' => $this->facet($items, 'pace', $this->matcher($category, null, $kind), fn ($i) => [$i['pace']]),
        ];

        $filtered = $items
            ->filter($this->matcher($category, $pace, $kind))
            ->values();

        $sorted = $this->sort($filtered, $sort);
        $total = $sorted->count();
        $data = $sorted->take($perPage)
            ->map(fn (array $item) => collect($item)->except('_recent')->all())
            ->values()
            ->all();

        $anyFilter = $q !== '' || $kind || $category || $pace || $verified || $followed;

        return [
            'data' => $data,
            'meta' => [
                'total' => $total,
                'shown' => count($data),
                'per_page' => $perPage,
                'has_more' => $total > count($data),
            ],
            'facets' => $facets,
            'stats' => $this->heroStats(),
            'featured' => $anyFilter ? null : $this->featured($universe, $aggregates),
        ];
    }

    private function baseQuery(?int $userId)
    {
        return Channel::query()
            ->where('status', ChannelStatusEnum::ACTIVE->value)
            ->whereHas('profile')
            ->with(['profile.channelCategories', 'channelBadges'])
            ->withCount('subscribers')
            ->when($userId, fn ($q) => $q->withExists(['subscribers as is_following' => fn ($s) => $s->where('users.id', $userId)]));
    }

    private function applySearch($query, string $search)
    {
        $like = '%'.mb_strtolower($search).'%';

        return $query->whereHas('profile', function ($q) use ($like) {
            $q->whereRaw('LOWER(name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(handle) LIKE ?', [$like])
                ->orWhereRaw('LOWER(description) LIKE ?', [$like]);
        });
    }

    /**
     * Un seul agrégat groupé sur studio_contents : compte des publications,
     * des statsdata, des publications récentes et date de dernière parution.
     *
     * @param  list<int>  $channelIds
     */
    private function publicationAggregates(array $channelIds): Collection
    {
        if (empty($channelIds)) {
            return collect();
        }

        $since = now()->subDays(self::RECENT_WINDOW_DAYS);

        return StudioContent::query()
            ->whereIn('channel_id', $channelIds)
            ->where('published_as', 'channel')
            ->where('status', 'published')
            ->selectRaw('channel_id')
            ->selectRaw('COUNT(*) as publications_count')
            ->selectRaw("SUM(CASE WHEN type = 'statsdata' THEN 1 ELSE 0 END) as statsdata_count")
            ->selectRaw('SUM(CASE WHEN updated_at >= ? THEN 1 ELSE 0 END) as recent_count', [$since])
            ->selectRaw('MAX(updated_at) as last_published_at')
            ->groupBy('channel_id')
            ->get()
            ->keyBy('channel_id');
    }

    private function formatItem(Channel $channel, Collection $aggregates): array
    {
        $profile = $channel->profile;
        $agg = $aggregates->get($channel->id);

        $publications = (int) ($agg->publications_count ?? 0);
        $statsdata = (int) ($agg->statsdata_count ?? 0);
        $recent = (int) ($agg->recent_count ?? 0);
        $lastPublishedAt = $agg->last_published_at ?? null;

        return [
            'id' => $channel->id,
            'name' => $profile->name,
            'handle' => $profile->handle,
            'description' => $profile->description,
            'kind' => $profile->kind?->value ?? 'independant',
            'verified' => in_array(ChannelBadgeEnum::VERIFIED->value, $channel->badges ?? [], true),
            'categories' => $profile->channelCategories->pluck('slug')->all(),
            'tags' => $profile->tags ?? [],
            'followers_count' => (int) ($channel->subscribers_count ?? 0),
            'publications_count' => $publications,
            'statsdata_count' => $statsdata,
            'view_count' => (int) ($profile->view_count ?? 0),
            'last_published_at' => $lastPublishedAt ? (string) $lastPublishedAt : null,
            'pace' => $this->pace($recent),
            'is_following' => (bool) ($channel->is_following ?? false),
            'logo_url' => $profile->logo_url,
            'banner_url' => $profile->banner_url,
            'custom_color_primary' => $profile->custom_color_primary,
            'custom_color_secondary' => $profile->custom_color_secondary,
            '_recent' => $recent,
        ];
    }

    private function pace(int $recentCount): string
    {
        return match (true) {
            $recentCount >= self::PACE_DAILY_MIN => 'jour',
            $recentCount >= self::PACE_WEEKLY_MIN => 'semaine',
            default => 'mois',
        };
    }

    /**
     * Prédicat de filtrage réutilisable — chaque dimension passée à null est ignorée
     * (sert à exclure la dimension courante lors du calcul de sa propre facette).
     */
    private function matcher(?string $category, ?string $pace, ?string $kind): \Closure
    {
        return function (array $item) use ($category, $pace, $kind): bool {
            if ($kind && $item['kind'] !== $kind) {
                return false;
            }
            if ($pace && $item['pace'] !== $pace) {
                return false;
            }
            if ($category && ! in_array($category, $item['categories'], true)) {
                return false;
            }

            return true;
        };
    }

    /**
     * @param  Collection<int, array>  $items
     */
    private function facet(Collection $items, string $dimension, \Closure $predicate, \Closure $keys): array
    {
        $scoped = $items->filter($predicate);
        $counts = [];
        foreach ($scoped as $item) {
            foreach ($keys($item) as $key) {
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        $all = ['value' => '', 'label' => 'Tous', 'count' => $scoped->count()];

        if ($dimension === 'kind') {
            $options = array_map(fn ($k) => [
                'value' => $k->value,
                'label' => $k->label(),
                'count' => $counts[$k->value] ?? 0,
            ], ChannelKindEnum::cases());

            return [$all, ...$options];
        }

        if ($dimension === 'pace') {
            $labels = ['jour' => 'Quotidien', 'semaine' => 'Hebdo', 'mois' => 'Mensuel'];
            $options = array_map(fn ($value, $label) => [
                'value' => $value,
                'label' => $label,
                'count' => $counts[$value] ?? 0,
            ], array_keys($labels), array_values($labels));

            return [$all, ...$options];
        }

        // themes → catégories de chaîne réelles
        $options = ChannelCategory::orderBy('position')
            ->get(['slug', 'label'])
            ->map(fn ($c) => [
                'value' => $c->slug,
                'label' => $c->label,
                'count' => $counts[$c->slug] ?? 0,
            ])
            ->filter(fn ($o) => $o['count'] > 0)
            ->values()
            ->all();

        return [$all, ...$options];
    }

    /**
     * @param  Collection<int, array>  $items
     * @return Collection<int, array>
     */
    private function sort(Collection $items, string $sort): Collection
    {
        return match ($sort) {
            'recent' => $items->sortByDesc(fn ($i) => $i['last_published_at'] ?? '')->values(),
            'followers' => $items->sortByDesc(fn ($i) => $i['followers_count'])->values(),
            default => $items->sortByDesc(fn ($i) => $i['followers_count'] + $i['_recent'] * self::TREND_RECENCY_WEIGHT)->values(),
        };
    }

    private function featured(Collection $universe, Collection $aggregates): ?array
    {
        if ($universe->isEmpty()) {
            return null;
        }

        $channel = $universe->firstWhere(fn (Channel $c) => (bool) $c->profile->is_featured)
            ?? $universe->sortByDesc(function (Channel $c) use ($aggregates) {
                $agg = $aggregates->get($c->id);

                return (int) ($c->subscribers_count ?? 0) + (int) ($agg->recent_count ?? 0) * self::TREND_RECENCY_WEIGHT;
            })->first();

        $item = $this->formatItem($channel, $aggregates);

        $posts = StudioContent::query()
            ->where('channel_id', $channel->id)
            ->where('published_as', 'channel')
            ->where('status', 'published')
            ->orderByDesc('updated_at')
            ->limit(3)
            ->get(['title', 'type', 'updated_at'])
            ->map(fn ($c) => [
                'title' => $c->title,
                'type' => $c->type,
                'updated_at' => $c->updated_at?->toIso8601String(),
            ])
            ->all();

        return [
            'id' => $item['id'],
            'name' => $item['name'],
            'handle' => $item['handle'],
            'initials' => $this->initials($item['name']),
            'verified' => $item['verified'],
            'description' => $item['description'],
            'kind' => $item['kind'],
            'pace' => $item['pace'],
            'logo_url' => $item['logo_url'],
            'custom_color_primary' => $item['custom_color_primary'],
            'custom_color_secondary' => $item['custom_color_secondary'],
            'is_following' => $item['is_following'],
            'stats' => [
                ['label' => 'Abonnés', 'value' => $item['followers_count']],
                ['label' => 'Publications', 'value' => $item['publications_count']],
                ['label' => 'Statsdata', 'value' => $item['statsdata_count']],
                ['label' => 'Vues', 'value' => $item['view_count']],
            ],
            'posts' => $posts,
        ];
    }

    private function heroStats(): array
    {
        $active = Channel::where('status', ChannelStatusEnum::ACTIVE->value)->whereHas('profile')->count();
        $verified = Channel::where('status', ChannelStatusEnum::ACTIVE->value)
            ->whereHas('channelBadges', fn ($b) => $b->where('slug', ChannelBadgeEnum::VERIFIED->value))
            ->count();
        $publicationsMonth = StudioContent::where('published_as', 'channel')
            ->where('status', 'published')
            ->where('updated_at', '>=', now()->subDays(self::RECENT_WINDOW_DAYS))
            ->count();
        $lastChannelAt = Channel::where('status', ChannelStatusEnum::ACTIVE->value)->max('created_at');

        return [
            'active' => $active,
            'verified' => $verified,
            'publications_month' => $publicationsMonth,
            'last_channel_at' => $lastChannelAt ? (string) $lastChannelAt : null,
        ];
    }

    private function initials(string $name): string
    {
        $words = preg_split('/\s+/', trim($name)) ?: [];
        $letters = array_map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)), array_slice($words, 0, 2));

        return implode('', $letters) ?: '?';
    }

    private function cleanEnum(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value !== '' && preg_match('/^[a-z0-9_-]{1,40}$/', $value) ? $value : null;
    }
}
