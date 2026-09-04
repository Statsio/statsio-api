<?php

namespace App\Domain\Content\Actions;

use App\Domain\Channel\Actions\ChannelCatalogAction;
use App\Domain\Content\Enums\SubBrandEnum;
use App\Models\Content\ContentCategory;
use App\Models\Content\Dossier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Catalogue public des dossiers éditoriaux (page /dossiers).
 * Le volume de dossiers actifs est faible : on charge l'univers une fois, puis
 * recherche / facettes / tri / pagination en PHP — même approche que
 * {@see ChannelCatalogAction}.
 *
 * Réponse alignée sur les autres catalogues : data / meta / facets / stats / featured.
 */
class PublicDossierCatalogAction
{
    private const DEFAULT_PER_PAGE = 12;

    private const MAX_PER_PAGE = 48;

    /**
     * @param  array{q?: ?string, category?: ?string, sort?: ?string, sub_brand?: ?string, per_page?: mixed}  $filters
     * @return array<string, mixed>
     */
    public function execute(array $filters): array
    {
        $q = $this->normalize(trim((string) ($filters['q'] ?? '')));
        $category = $this->cleanSlug($filters['category'] ?? null);
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

        $aggregates = $this->contentAggregates($dossiers->pluck('id')->all());

        $items = $dossiers
            ->map(fn (Dossier $d) => $this->formatItem($d, $aggregates))
            ->values();

        // Facettes catégories : calculées sur l'univers filtré par la recherche seule.
        $searched = $q === ''
            ? $items
            : $items->filter(fn (array $i) => str_contains($this->normalize(
                $i['name'].' '.($i['description'] ?? '').' '.implode(' ', $i['_keywords'])
            ), $q))->values();

        $facets = ['categories' => $this->categoryFacets($searched, $subBrand)];

        $filtered = $searched
            ->when($category !== null, fn (Collection $c) => $c->filter(
                fn (array $i) => in_array($category, $i['_category_slugs'], true)
            ))
            ->values();

        $sorted = $this->sort($filtered, $sort);
        $total = $sorted->count();

        $anyFilter = $q !== '' || $category !== null;
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
     * Un seul agrégat groupé sur le pivot : nombre de contenus publiés + date de
     * dernière parution, par dossier.
     *
     * @param  list<int>  $dossierIds
     */
    private function contentAggregates(array $dossierIds): Collection
    {
        if ($dossierIds === []) {
            return collect();
        }

        return DB::table('dossier_studio_content as p')
            ->join('studio_contents as c', 'c.id', '=', 'p.studio_content_id')
            ->whereIn('p.dossier_id', $dossierIds)
            ->where('c.status', 'published')
            ->groupBy('p.dossier_id')
            ->selectRaw('p.dossier_id')
            ->selectRaw('COUNT(*) as content_count')
            ->selectRaw('MAX(c.updated_at) as last_content_at')
            ->get()
            ->keyBy('dossier_id');
    }

    /**
     * @return array<string, mixed>
     */
    private function formatItem(Dossier $dossier, Collection $aggregates): array
    {
        $agg = $aggregates->get($dossier->id);
        $primaryCategory = $dossier->contentCategories->first();

        return [
            'id' => $dossier->id,
            'slug' => $dossier->slug,
            'name' => $dossier->name,
            'description' => $dossier->description,
            'image_url' => $dossier->image_url,
            'icon' => $dossier->icon,
            'category' => $primaryCategory
                ? ['slug' => $primaryCategory->slug, 'label' => $primaryCategory->name]
                : null,
            'content_count' => (int) ($agg?->content_count ?? 0),
            'updated_at' => $agg?->last_content_at ? (string) $agg->last_content_at : null,
            '_category_slugs' => $dossier->contentCategories->pluck('slug')->all(),
            '_keywords' => array_values(array_filter((array) ($dossier->keywords ?? []), 'is_string')),
        ];
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
     * @param  Collection<int, array<string, mixed>>  $items
     * @return Collection<int, array<string, mixed>>
     */
    private function sort(Collection $items, string $sort): Collection
    {
        return match ($sort) {
            'count' => $items->sortByDesc('content_count')->values(),
            'az' => $items->sortBy(fn (array $i) => $this->normalize($i['name']))->values(),
            default => $items->sortByDesc(fn (array $i) => $i['updated_at'] ?? '')->values(),
        };
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array{dossiers: int, contents: int, categories: int, last_updated_at: ?string}
     */
    private function heroStats(Collection $items): array
    {
        $categories = [];
        $lastUpdated = null;
        $contents = 0;
        foreach ($items as $item) {
            $contents += $item['content_count'];
            foreach ($item['_category_slugs'] as $slug) {
                $categories[$slug] = true;
            }
            if ($item['updated_at'] !== null && ($lastUpdated === null || $item['updated_at'] > $lastUpdated)) {
                $lastUpdated = $item['updated_at'];
            }
        }

        return [
            'dossiers' => $items->count(),
            'contents' => $contents,
            'categories' => count($categories),
            'last_updated_at' => $lastUpdated,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function stripInternal(array $item): array
    {
        unset($item['_category_slugs'], $item['_keywords']);

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
