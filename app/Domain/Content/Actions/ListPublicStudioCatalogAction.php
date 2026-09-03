<?php

namespace App\Domain\Content\Actions;

use App\Domain\Content\Enums\SubBrandEnum;
use App\Domain\Content\Enums\SurveyKindEnum;
use App\Domain\Content\Support\ContentDatasetSources;
use App\Domain\Content\Support\StudioContentBlocks;
use App\Domain\Content\Support\StudioContentListing;
use App\Domain\Content\Support\SurveyListingAggregates;
use App\Models\DataIngestion\Dataset;
use App\Models\StudioContent;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Catalogue public paginé (listings articles / statsdata / sondages).
 * Filtrage, tri, facettes et stats agrégées : responsabilité backend.
 * Le front ne reçoit que des cartes légères (pas de blocs).
 */
class ListPublicStudioCatalogAction
{
    private const STATS_TTL = 300;

    private const FORMAT_LABELS = [
        'enquete' => 'Enquête',
        'decryptage' => 'Décryptage',
        'dossier' => 'Dossier',
        'breve' => 'Brève',
    ];

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>, facets: array<string, mixed>, stats: array<string, mixed>, featured: ?array<string, mixed>}
     */
    public function execute(Request $request): array
    {
        $type = $this->sanitizeType($request->query('type'));
        $channelId = $request->query('channel_id') ? (int) $request->query('channel_id') : null;
        $allowlist = $this->sanitizeCategories($request->query('categories'));
        $subBrand = $this->sanitizeSubBrand($request->query('sub_brand'));
        $category = $this->sanitizeSingleCategory($request->query('category'));
        $format = $this->sanitizeFormat($request->query('format'));
        $search = $this->sanitizeSearch($request->query('q'));
        $hasData = filter_var($request->query('has_data'), FILTER_VALIDATE_BOOLEAN);
        $sort = $this->sanitizeSort($request->query('sort'));
        $perPage = max(1, min(48, (int) ($request->query('per_page') ?? 9)));

        $isSurvey = $type === 'survey';
        $surveyKind = $isSurvey ? $this->sanitizeSurveyKind($request->query('survey_kind')) : null;
        $surveyStatus = $isSurvey ? $this->sanitizeSurveyStatus($request->query('status')) : null;
        $notParticipated = $isSurvey && filter_var($request->query('not_participated'), FILTER_VALIDATE_BOOLEAN);

        $viewer = $request->user('sanctum');
        $respondentToken = $this->sanitizeToken($request->query('respondent_token'));

        $base = $this->publishedQuery($type, $channelId, $allowlist, $subBrand);
        $searched = $this->applySearch(clone $base, $search);

        $filtered = clone $searched;
        if ($category) {
            $filtered->whereJsonContains('categories', $category);
        }
        if ($format) {
            $aliases = StudioContentListing::FORMAT_ALIASES[$format] ?? [$format];
            $filtered->where(function (Builder $q) use ($aliases) {
                foreach ($aliases as $alias) {
                    $q->orWhereJsonContains('categories', $alias);
                }
            });
        }
        if ($hasData) {
            $this->constrainHasData($filtered);
        }
        if ($surveyKind) {
            $filtered->where('survey_kind', $surveyKind);
        }
        if ($surveyStatus === 'ouvert') {
            $filtered->where(fn (Builder $q) => $q->whereNull('response_deadline')->orWhere('response_deadline', '>=', now()));
        } elseif ($surveyStatus === 'clos') {
            $filtered->whereNotNull('response_deadline')->where('response_deadline', '<', now());
        }
        if ($notParticipated) {
            $participatedAll = SurveyListingAggregates::participatedContentIds(
                $viewer?->id,
                $respondentToken,
                (clone $filtered)->pluck('id')->map(fn ($id) => (int) $id)->all(),
            );
            if ($participatedAll !== []) {
                $filtered->whereNotIn('id', array_keys($participatedAll));
            }
        }
        if ($sort === 'votes') {
            $filtered->withCount('blockResponses');
        }

        $hasUserFilters = $search !== '' || $category !== null || $format !== null || $hasData
            || $surveyKind !== null || $surveyStatus !== null || $notParticipated;

        // Sans filtre : les contenus « à la une » (choisis par l'admin) sont épinglés
        // en tête, dans l'ordre de `featured_priority` (plus petit = premier).
        if (! $hasUserFilters) {
            $filtered->orderByDesc('is_featured')
                ->orderByRaw('COALESCE(featured_priority, 2147483647) asc');
        }
        $this->applySort($filtered, $sort);

        $total = (clone $filtered)->count();
        $pageItems = $filtered
            ->with(StudioContentListing::eagerLoads())
            ->limit($perPage)
            ->get();

        $favoriteIds = $this->favoriteIds($viewer, $pageItems->pluck('id')->all());
        $freshnessByContent = $isSurvey ? [] : $this->freshnessByContent($pageItems);

        $surveyAggregates = $isSurvey ? new SurveyListingAggregates($pageItems) : null;
        $participatedIds = $isSurvey
            ? SurveyListingAggregates::participatedContentIds(
                $viewer?->id,
                $respondentToken,
                $pageItems->pluck('id')->map(fn ($id) => (int) $id)->all(),
            )
            : [];

        $data = $pageItems
            ->map(fn (StudioContent $c) => StudioContentListing::make(
                $c,
                isset($favoriteIds[$c->id]),
                $surveyAggregates?->for($c),
                isset($participatedIds[(int) $c->id]),
                $freshnessByContent[(int) $c->id] ?? null,
            ))
            ->values()
            ->all();

        // La grande card mise en avant = le contenu « à la une » de plus haute
        // priorité. Rien n'est promu automatiquement : l'admin décide.
        $featured = (! $hasUserFilters && isset($data[0]) && ($data[0]['is_featured'] ?? false))
            ? $data[0]
            : null;

        return [
            'data' => $data,
            'meta' => [
                'total' => $total,
                'shown' => count($data),
                'per_page' => $perPage,
                'has_more' => $total > count($data),
            ],
            'facets' => [
                'categories' => $this->categoryFacets($searched, $allowlist),
                'formats' => $this->formatFacets($searched),
                'survey_kinds' => $isSurvey ? $this->surveyKindFacets($searched) : [],
            ],
            'stats' => $this->heroStats($type, $channelId, $allowlist, $subBrand),
            'featured' => $featured,
        ];
    }

    private function publishedQuery(?string $type, ?int $channelId, array $allowlist, ?string $subBrand = null): Builder
    {
        return StudioContent::query()
            ->where('status', 'published')
            ->when($type, fn (Builder $q) => $q->where('type', $type))
            ->when($channelId, fn (Builder $q) => $q->where('channel_id', $channelId)->where('published_as', 'channel'))
            ->when($subBrand, fn (Builder $q) => $q->where('sub_brand', $subBrand))
            ->when($allowlist, fn (Builder $q) => $q->where(function (Builder $sub) use ($allowlist) {
                foreach ($allowlist as $category) {
                    $sub->orWhereJsonContains('categories', $category);
                }
            }));
    }

    private function applySearch(Builder $query, string $search): Builder
    {
        if ($search === '') {
            return $query;
        }

        $like = '%'.addcslashes(mb_strtolower($search), '%_\\').'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->whereRaw('LOWER(title) LIKE ?', [$like])
                ->orWhereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', [$like])
                ->orWhereHas('channel.profile', fn (Builder $p) => $p->whereRaw('LOWER(name) LIKE ?', [$like]))
                ->orWhereHas('user.profile', function (Builder $p) use ($like) {
                    $p->whereRaw('LOWER(first_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', [$like]);
                });
        });
    }

    private function constrainHasData(Builder $query): void
    {
        $query->where(function (Builder $q) {
            $q->where('blocks', 'like', '%datasetId%')
                ->orWhere('pages', 'like', '%datasetId%')
                ->orWhere('sections', 'like', '%datasetId%');
        });
    }

    private function applySort(Builder $query, string $sort): void
    {
        if ($sort === 'recent') {
            $query->orderByDesc('updated_at')->orderByDesc('id');

            return;
        }

        if ($sort === 'votes') {
            // « Les plus suivis » (sondages) : nombre de réponses, puis fraîcheur.
            $query->orderByDesc('block_responses_count')->orderByDesc('updated_at')->orderByDesc('id');

            return;
        }

        // « Tendance » et « plus lus » : vues, puis fraîcheur (pas d'historique de vues par jour).
        $query->orderByDesc('views_count')->orderByDesc('updated_at')->orderByDesc('id');
    }

    /**
     * @return list<array{value: string, label: string, count: int}>
     */
    private function categoryFacets(Builder $searched, array $allowlist): array
    {
        $rows = (clone $searched)->get(['categories']);
        $counts = [];
        foreach ($rows as $row) {
            foreach ($row->categories ?? [] as $category) {
                if (! is_string($category) || $category === '') {
                    continue;
                }
                if (StudioContentListing::extractFormat([$category])) {
                    continue;
                }
                if ($allowlist && ! in_array($category, $allowlist, true)) {
                    continue;
                }
                $counts[$category] = ($counts[$category] ?? 0) + 1;
            }
        }
        arsort($counts);

        $facets = [[
            'value' => '',
            'label' => 'Toutes',
            'count' => $rows->count(),
        ]];
        foreach ($counts as $value => $count) {
            $facets[] = ['value' => $value, 'label' => $value, 'count' => $count];
        }

        return $facets;
    }

    /**
     * @return list<array{value: string, label: string, count: int}>
     */
    private function surveyKindFacets(Builder $searched): array
    {
        $counts = (clone $searched)
            ->selectRaw('survey_kind, COUNT(*) as aggregate')
            ->groupBy('survey_kind')
            ->pluck('aggregate', 'survey_kind');

        $total = 0;
        $facets = [];
        foreach (SurveyKindEnum::cases() as $case) {
            $count = (int) ($counts[$case->value] ?? 0);
            $total += $count;
            $facets[] = ['value' => $case->value, 'label' => $case->label(), 'count' => $count];
        }

        return [
            ['value' => '', 'label' => 'Tous', 'count' => $total],
            ...$facets,
        ];
    }

    /**
     * @return list<array{value: string, label: string, count: int}>
     */
    private function formatFacets(Builder $searched): array
    {
        $rows = (clone $searched)->get(['categories']);
        $counts = array_fill_keys(StudioContentListing::FORMATS, 0);
        foreach ($rows as $row) {
            $format = StudioContentListing::extractFormat($row->categories ?? []);
            if ($format) {
                $counts[$format]++;
            }
        }

        $facets = [[
            'value' => '',
            'label' => 'Tous',
            'count' => $rows->count(),
        ]];
        foreach (self::FORMAT_LABELS as $value => $label) {
            $facets[] = ['value' => $value, 'label' => $label, 'count' => $counts[$value]];
        }

        return $facets;
    }

    /**
     * @return array{published: int, channels: int, charts: int, last_published_at: ?string}
     */
    private function heroStats(?string $type, ?int $channelId, array $allowlist, ?string $subBrand = null): array
    {
        $cacheKey = 'studio.public.catalog.stats.'.md5(json_encode([$type, $channelId, $allowlist, $subBrand]));

        return Cache::remember($cacheKey, self::STATS_TTL, function () use ($type, $channelId, $allowlist, $subBrand) {
            $query = $this->publishedQuery($type, $channelId, $allowlist, $subBrand);
            $published = (clone $query)->count();
            $channels = (clone $query)->where('published_as', 'channel')->whereNotNull('channel_id')->distinct()->count('channel_id');
            $last = (clone $query)->orderByDesc('updated_at')->value('updated_at');

            // Pour les sondages, la métrique « graphiques » n'a pas de sens : on renvoie
            // le nombre de pétitions actives (le front l'affiche sous ce libellé).
            if ($type === 'survey') {
                $charts = (clone $query)->where('survey_kind', SurveyKindEnum::Petition->value)->count();
            } else {
                $charts = 0;
                foreach ((clone $query)->get(['blocks', 'pages', 'sections']) as $content) {
                    $charts += StudioContentBlocks::chartCount(StudioContentBlocks::all($content));
                }
            }

            return [
                'published' => $published,
                'channels' => $channels,
                'charts' => $charts,
                'last_published_at' => $last?->toIso8601String(),
            ];
        });
    }

    /**
     * Fraîcheur de la source « principale » de chaque contenu (badge des cartes).
     * Un seul lot de datasets pour toute la page — pas de N+1.
     *
     * @param  Collection<int, StudioContent>  $contents
     * @return array<int, array<string, mixed>|null>
     */
    private function freshnessByContent(Collection $contents): array
    {
        $idsByContent = [];
        $allIds = [];
        foreach ($contents as $content) {
            $content->applyPublishedPayload();
            $ids = ContentDatasetSources::extractDatasetIds($content);
            $idsByContent[(int) $content->id] = $ids;
            foreach ($ids as $id) {
                $allIds[$id] = true;
            }
        }

        if ($allIds === []) {
            return [];
        }

        $datasetsById = Dataset::whereIn('id', array_keys($allIds))
            ->with('dataSource')
            ->get(['id', 'data_source_id'])
            ->keyBy(fn ($d) => (string) $d->id);

        $result = [];
        foreach ($idsByContent as $contentId => $ids) {
            $payloads = [];
            foreach ($ids as $id) {
                $dataset = $datasetsById->get((string) $id);
                if ($dataset) {
                    $payloads[] = ContentDatasetSources::freshnessPayload($dataset);
                }
            }
            $result[$contentId] = ContentDatasetSources::pickPrimaryFreshness($payloads);
        }

        return $result;
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

    private function sanitizeType(mixed $raw): ?string
    {
        if (! is_string($raw) || ! in_array($raw, ['article', 'statsdata', 'survey'], true)) {
            return null;
        }

        return $raw;
    }

    private function sanitizeSearch(mixed $raw): string
    {
        if (! is_string($raw)) {
            return '';
        }

        return mb_substr(trim($raw), 0, 80);
    }

    private function sanitizeSort(mixed $raw): string
    {
        return in_array($raw, ['trend', 'recent', 'views', 'votes'], true) ? $raw : 'trend';
    }

    private function sanitizeSurveyKind(mixed $raw): ?string
    {
        return is_string($raw) && in_array($raw, SurveyKindEnum::values(), true) ? $raw : null;
    }

    /** Sous-marque concrète (`statsio|tvstats|medistats`) pour cadrer un listing. */
    private function sanitizeSubBrand(mixed $raw): ?string
    {
        return is_string($raw) && in_array($raw, SubBrandEnum::contentValues(), true) ? $raw : null;
    }

    private function sanitizeSurveyStatus(mixed $raw): ?string
    {
        return in_array($raw, ['ouvert', 'clos'], true) ? $raw : null;
    }

    private function sanitizeToken(mixed $raw): ?string
    {
        return is_string($raw) && $raw !== '' ? mb_substr($raw, 0, 100) : null;
    }

    private function sanitizeFormat(mixed $raw): ?string
    {
        if (! is_string($raw) || ! in_array($raw, StudioContentListing::FORMATS, true)) {
            return null;
        }

        return $raw;
    }

    private function sanitizeSingleCategory(mixed $raw): ?string
    {
        if (! is_string($raw) || $raw === '') {
            return null;
        }
        if (! preg_match('/^[\p{L}\p{N}\s_-]{1,50}$/u', $raw)) {
            return null;
        }

        return $raw;
    }

    /**
     * @return list<string>
     */
    private function sanitizeCategories(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        return collect($raw)
            ->filter(fn ($c) => is_string($c) && preg_match('/^[\p{L}\p{N}\s_-]{1,50}$/u', $c))
            ->unique()
            ->sort()
            ->values()
            ->take(8)
            ->all();
    }
}
