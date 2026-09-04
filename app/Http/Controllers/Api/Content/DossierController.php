<?php

namespace App\Http\Controllers\Api\Content;

use App\Domain\Content\Actions\PublicDossierCatalogAction;
use App\Domain\Content\Support\StudioContentListing;
use App\Http\Controllers\Controller;
use App\Models\Content\Dossier;
use App\Models\StudioContent;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DossierController extends Controller
{
    /**
     * Catalogue des dossiers éditoriaux actifs (sélecteur de la modale de
     * publication + onglet Propriétés).
     */
    public function index(): JsonResponse
    {
        $dossiers = Dossier::active()
            ->with('contentCategories:id,slug')
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->map(fn (Dossier $d) => [
                'id' => $d->id,
                'slug' => $d->slug,
                'name' => $d->name,
                'description' => $d->description,
                'image_url' => $d->image_url,
                'icon' => $d->icon,
                'category_slugs' => $d->contentCategories->pluck('slug')->values(),
            ]);

        return response()->json(['success' => true, 'data' => $dossiers]);
    }

    /**
     * Dossiers épinglés affichés en badges dans la barre de navigation du header.
     * Endpoint public (le header est rendu côté serveur sur les pages publiques).
     */
    public function pinned(): JsonResponse
    {
        $dossiers = Dossier::active()
            ->pinned()
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->map(fn (Dossier $d) => [
                'id' => $d->id,
                'slug' => $d->slug,
                'name' => $d->name,
                'icon' => $d->icon,
            ]);

        return response()->json(['success' => true, 'data' => $dossiers]);
    }

    /**
     * Catalogue public paginé de la page /dossiers : recherche, facette catégorie,
     * tri, dossier à la une et stats agrégées.
     */
    public function catalog(Request $request, PublicDossierCatalogAction $action): JsonResponse
    {
        $payload = $action->execute([
            'q' => $request->query('q'),
            'category' => $request->query('category'),
            'sort' => $request->query('sort'),
            'sub_brand' => $request->query('sub_brand'),
            'per_page' => $request->query('per_page'),
        ]);

        return response()->json(['success' => true, 'data' => $payload]);
    }

    /**
     * Page publique d'un dossier : métadonnées + fil des contenus publiés qui y
     * sont rangés (cartes légères), compteurs par type et dossiers voisins.
     */
    public function showPublic(Request $request, string $slug): JsonResponse
    {
        $dossier = Dossier::active()
            ->with('contentCategories:id,slug,name')
            ->where('slug', $slug)
            ->firstOrFail();

        /** @var Collection<int, StudioContent> $contents */
        $contents = $dossier->studioContents()
            ->where('status', 'published')
            ->with(StudioContentListing::eagerLoads())
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        $viewer = $request->user('sanctum');
        $favoriteIds = $this->favoriteIds($viewer, $contents->pluck('id')->all());

        $items = $contents
            ->map(fn (StudioContent $c) => StudioContentListing::make($c, isset($favoriteIds[$c->id])))
            ->values()
            ->all();

        $counts = [
            'all' => count($items),
            'article' => 0,
            'statsdata' => 0,
            'survey' => 0,
        ];
        $publishers = [];
        foreach ($items as $item) {
            $type = $item['type'] ?? 'statsdata';
            if (isset($counts[$type])) {
                $counts[$type]++;
            }
            $publishers[$item['publisher']['name']] = true;
        }

        $primaryCategory = $dossier->contentCategories->first();
        $lastUpdatedAt = $contents->first()?->updated_at?->toIso8601String();

        return response()->json([
            'success' => true,
            'data' => [
                'dossier' => [
                    'id' => $dossier->id,
                    'slug' => $dossier->slug,
                    'name' => $dossier->name,
                    'description' => $dossier->description,
                    'image_url' => $dossier->image_url,
                    'icon' => $dossier->icon,
                    'category' => $primaryCategory
                        ? ['slug' => $primaryCategory->slug, 'label' => $primaryCategory->name]
                        : null,
                    'opened_at' => $dossier->created_at?->toIso8601String(),
                    'updated_at' => $lastUpdatedAt,
                    'content_count' => count($items),
                    'contributors_count' => count($publishers),
                ],
                'items' => $items,
                'counts' => $counts,
                'related' => $this->relatedDossiers($dossier),
            ],
        ]);
    }

    /**
     * Autres dossiers actifs partageant au moins une catégorie de contenu.
     *
     * @return list<array<string, mixed>>
     */
    private function relatedDossiers(Dossier $dossier): array
    {
        $categoryIds = $dossier->contentCategories->pluck('id');
        if ($categoryIds->isEmpty()) {
            return [];
        }

        $related = Dossier::active()
            ->where('id', '!=', $dossier->id)
            ->whereHas('contentCategories', fn ($q) => $q->whereIn('content_categories.id', $categoryIds))
            ->withCount(['studioContents as content_count' => fn ($q) => $q->where('status', 'published')])
            ->orderBy('position')
            ->orderBy('name')
            ->limit(4)
            ->get();

        return $related
            ->map(fn (Dossier $d) => [
                'slug' => $d->slug,
                'name' => $d->name,
                'image_url' => $d->image_url,
                'icon' => $d->icon,
                'content_count' => (int) $d->content_count,
            ])
            ->all();
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
