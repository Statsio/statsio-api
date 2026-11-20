<?php

namespace App\Domain\Content\Actions;

use App\Domain\Channel\Actions\ChannelCatalogAction;
use App\Domain\Content\Enums\SubBrandEnum;
use App\Models\Content\ContentCategory;
use App\Models\Content\Dossier;
use App\Models\StudioContent;
use Illuminate\Support\Collection;

/**
 * Catalogue public des dossiers éditoriaux (page /dossiers).
 * Le volume de dossiers actifs est faible : on charge l'univers une fois (dossiers
 * + contenus publiés rangés dedans, 2 requêtes), puis recherche / facettes / tri /
 * pagination en PHP — même approche que {@see ChannelCatalogAction}.
 *
 * Réponse alignée sur les autres catalogues : data / meta / facets / stats / featured.
 * Chaque dossier porte sa composition par type et ses derniers contenus rangés
 * (mosaïque + sommaire des cartes « classeur »).
 */
class PublicDossierCatalogAction
{
    private const DEFAULT_PER_PAGE = 12;

    private const MAX_PER_PAGE = 48;

    /** Derniers contenus exposés par dossier (sommaire de la carte à la une). */
    private const RECENT_LIMIT = 5;

    /** @var list<string> */
    public const CONTENT_TYPES = ['statsdata', 'article', 'survey'];

    /**
     * @param  array{q?: ?string, category?: ?string, has?: ?string, sort?: ?string, sub_brand?: ?string, per_page?: mixed}  $filters
     * @return array<string, mixed>
     */
    public function execute(array $filters): array
    {
        $q = $this->normalize(trim((string) ($filters['q'] ?? '')));
        $category = $this->cleanSlug($filters['category'] ?? null);
        $has = in_array($filters['has'] ?? null, self::CONTENT_TYPES, true) ? $filters['has'] : null;
        $sort = in_array($filters['sort'] ?? null, ['maj', 'count', 'az'], true) ? $filters['sort'] : 'maj';
        $subBrand = SubBrandEnum::sanitize($filters['sub_brand'] ?? null);
        $perPage = max(1, min(self::MAX_PER_PAGE, (int) ($filters['per_page'] ?? self::DEFAULT_PER_PAGE)));

        /** @var Collection<int, Dossier> $dossiers */
        $dossiers = Dossier::active()
            ->forSubBrand($subBrand)
            ->with('contentCategories:id,slug,name')
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        $contents = $this->publishedContents($dossiers->pluck('id')->all());

        $items = $dossiers
            ->map(fn (Dossier $d) => $this->formatItem($d, $contents->get($d->id, collect())))
            ->values();

        // Facettes calculées sur l'univers filtré par la recherche seule (catégories),
        // puis par recherche + catégorie (types) : chaque facette ignore son propre filtre.
        $searched = $q === ''
            ? $items
            : $items->filter(fn (array $i) => str_contains($i['_haystack'], $q))->values();

        $inCategory = $category === null
            ? $searched
            : $searched->filter(fn (array $i) => in_array($category, $i['_category_slugs'], true))->values();

        $facets = [
            'categories' => $this->categoryFacets($searched, $subBrand),
            'types' => $this->typeFacets($inCategory),
        ];

        $filtered = $has === null
            ? $inCategory
            : $inCategory->filter(fn (array $i) => $i['counts'][$has] > 0)->values();

        $sorted = $this->sort($filtered, $sort);
        $total = $sorted->count();

        $anyFilter = $q !== '' || $category !== null || $has !== null;
        $featured = ! $anyFilter && $sorted->isNotEmpty() ? $this->stripInternal($sorted->first()) : null;

        $page = $sorted
            ->when($featured !== null, fn (Collection $c) => $c->slice(1))
            ->take($perPage - ($featured !== null ? 1 : 0))
            ->map(fn (array $i) => $this->stripInternal($i))
            ->values()
            ->all();

        $shown = count($page) + ($featured !== null ? 1 : 0);

        return [
            'data' => $page,
            'featured' => $featured,
            'meta' => [
                'total' => $total,
                'shown' => $shown,
                'per_page' => $perPage,
                'has_more' => $total > $shown,
            ],
            'facets' => $facets,
            'stats' => $this->heroStats($items),
        ];
    }

    /**
     * Contenus publiés rangés dans les dossiers, du plus récent au plus ancien,
     * groupés par dossier. Titre = version publiée ; vignette = média `thumbnail`.
     *
     * @param  list<int>  $dossierIds
     * @return Collection<int, Collection<int, StudioContent>>
     */
    private function publishedContents(array $dossierIds): Collection
    {
        if ($dossierIds === []) {
            return collect();
        }

        return StudioContent::query()
            ->join('dossier_studio_content as p', 'p.studio_content_id', '=', 'studio_contents.id')
            ->whereIn('p.dossier_id', $dossierIds)
            ->where('studio_contents.status', 'published')
            ->select([
                'studio_contents.id',
                'studio_contents.slug',
                'studio_contents.type',
                'studio_contents.title',
                'studio_contents.updated_at',
                'studio_contents.published_version_id',
                'p.dossier_id',
            ])
            ->with(['publishedVersion:id,title', 'media'])
            ->orderByDesc('studio_contents.updated_at')
            ->orderByDesc('studio_contents.id')
            ->get()
            ->groupBy('dossier_id');
    }

    /**
     * @param  Collection<int, StudioContent>  $contents  publiés, du plus récent au plus ancien
     * @return array<string, mixed>
     */
    private function formatItem(Dossier $dossier, Collection $contents): array
    {
        $primaryCategory = $dossier->contentCategories->first();

        $counts = array_fill_keys(self::CONTENT_TYPES, 0);
        $titles = [];
        foreach ($contents as $content) {
            $type = $this->contentType($content);
            $counts[$type]++;
            $titles[] = $this->contentTitle($content);
        }

        $keywords = array_values(array_filter((array) ($dossier->keywords ?? []), 'is_string'));
        $lastContentAt = $contents->first()?->updated_at?->toIso8601String();

        return [
            'id' => $dossier->id,
            'slug' => $dossier->slug,
            'name' => $dossier->name,
            'description' => $dossier->description,
            'image_url' => $dossier->image_url,
            'icon' => $dossier->icon,
            'is_pinned' => (bool) $dossier->is_pinned,
            'sub_brand' => $dossier->sub_brand?->value ?? SubBrandEnum::All->value,
            'category' => $primaryCategory
                ? ['slug' => $primaryCategory->slug, 'label' => $primaryCategory->name]
                : null,
            'content_count' => $contents->count(),
            'counts' => $counts,
            'recent' => $contents
                ->take(self::RECENT_LIMIT)
                ->map(fn (StudioContent $c) => [
                    'id' => (string) $c->id,
                    'slug' => $c->slug,
                    'type' => $this->contentType($c),
                    'title' => $this->contentTitle($c),
                    'thumbnail_url' => $c->getFirstMediaUrl('thumbnail') ?: null,
                    'updated_at' => $c->updated_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'updated_at' => $lastContentAt,
            '_category_slugs' => $dossier->contentCategories->pluck('slug')->all(),
            // Recherche : nom, description, mots-clés et titres des contenus rangés.
            '_haystack' => $this->normalize(implode(' ', [
                $dossier->name,
                $dossier->description ?? '',
                ...$keywords,
                ...$titles,
            ])),
        ];
    }

    private function contentType(StudioContent $content): string
    {
        return in_array($content->type, self::CONTENT_TYPES, true) ? $content->type : 'statsdata';
    }

    private function contentTitle(StudioContent $content): string
    {
        return (string) ($content->publishedVersion?->title ?? $content->title);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return list<array{value: string, label: string, count: int}>
     */
    private function categoryFacets(Collection $items, ?string $subBrand): array
    {
        // Sur une sous-marque, on n'expose que les catégories qui lui sont
        // rattachées (ou « toutes marques ») : un dossier « all » tagué d'une
        // catégorie Statsio ne doit pas faire apparaître cette catégorie sur
        // TVStats / Medistats.
        $allowed = $subBrand === null
            ? null
            : ContentCategory::forSubBrand($subBrand)->pluck('slug')->flip();

        $labels = [];
        $counts = [];
        foreach ($items as $item) {
            if ($item['category'] === null) {
                continue;
            }
            $slug = $item['category']['slug'];
            if ($allowed !== null && ! $allowed->has($slug)) {
                continue;
            }
            $labels[$slug] = $item['category']['label'];
            $counts[$slug] = ($counts[$slug] ?? 0) + 1;
        }
        arsort($counts);

        $facets = [['value' => '', 'label' => 'Toutes', 'count' => $items->count()]];
        foreach ($counts as $slug => $count) {
            $facets[] = ['value' => $slug, 'label' => $labels[$slug], 'count' => $count];
        }

        return $facets;
    }

    /**
     * Nombre de dossiers contenant au moins un contenu de chaque type.
     *
     * @param  Collection<int, array<string, mixed>>  $items
     * @return list<array{value: string, count: int}>
     */
    private function typeFacets(Collection $items): array
    {
        return array_map(
            fn (string $type) => [
                'value' => $type,
                'count' => $items->filter(fn (array $i) => $i['counts'][$type] > 0)->count(),
            ],
            self::CONTENT_TYPES,
        );
    }

    /**
     * `maj` (activité) : épinglés d'abord, puis dernier contenu rangé.
     *
     * @param  Collection<int, array<string, mixed>>  $items
     * @return Collection<int, array<string, mixed>>
     */
    private function sort(Collection $items, string $sort): Collection
    {
        return match ($sort) {
            'count' => $items->sortByDesc('content_count')->values(),
            'az' => $items->sortBy(fn (array $i) => $this->normalize($i['name']))->values(),
            default => $items->sortBy([
                fn (array $a, array $b) => $b['is_pinned'] <=> $a['is_pinned'],
                fn (array $a, array $b) => ($b['updated_at'] ?? '') <=> ($a['updated_at'] ?? ''),
            ])->values(),
        };
    }

    /**
     * Les contenus sont comptés par rangement : un contenu présent dans deux
     * dossiers compte deux fois (le hero le précise).
     *
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array{dossiers: int, contents: int, categories: int, by_type: array<string, int>, updated_this_week: int, last_updated_at: ?string}
     */
    private function heroStats(Collection $items): array
    {
        $categories = [];
        $byType = array_fill_keys(self::CONTENT_TYPES, 0);
        $lastUpdated = null;
        $contents = 0;
        $weekAgo = now()->subWeek()->toIso8601String();
        $updatedThisWeek = 0;

        foreach ($items as $item) {
            $contents += $item['content_count'];
            foreach (self::CONTENT_TYPES as $type) {
                $byType[$type] += $item['counts'][$type];
            }
            foreach ($item['_category_slugs'] as $slug) {
                $categories[$slug] = true;
            }
            $updatedAt = $item['updated_at'];
            if ($updatedAt !== null) {
                if ($updatedAt >= $weekAgo) {
                    $updatedThisWeek++;
                }
                if ($lastUpdated === null || $updatedAt > $lastUpdated) {
                    $lastUpdated = $updatedAt;
                }
            }
        }

        return [
            'dossiers' => $items->count(),
            'contents' => $contents,
            'categories' => count($categories),
            'by_type' => $byType,
            'updated_this_week' => $updatedThisWeek,
            'last_updated_at' => $lastUpdated,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function stripInternal(array $item): array
    {
        unset($item['_category_slugs'], $item['_haystack']);

        return $item;
    }

    private function normalize(string $value): string
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return mb_strtolower($ascii !== false && $ascii !== '' ? $ascii : $value);
    }

    private function cleanSlug(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value !== '' && preg_match('/^[a-z0-9-]{1,60}$/', $value) ? $value : null;
    }
}
