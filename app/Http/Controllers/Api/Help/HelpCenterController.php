<?php

namespace App\Http\Controllers\Api\Help;

use App\Http\Controllers\Controller;
use App\Models\Help\HelpArticle;
use App\Models\Help\HelpArticleFeedback;
use App\Models\Help\HelpCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HelpCenterController extends Controller
{
    /**
     * Accueil du centre d'aide : catégories actives, articles les plus
     * consultés et les plus récemment ajoutés.
     */
    public function home(): JsonResponse
    {
        $categories = HelpCategory::active()
            ->withCount(['articles' => fn ($q) => $q->active()])
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->map(fn (HelpCategory $c) => $this->formatCategory($c));

        $popular = HelpArticle::active()
            ->with('category:id,slug,name')
            ->orderByDesc('views_count')
            ->limit(4)
            ->get()
            ->map(fn (HelpArticle $a) => $this->formatArticleSummary($a));

        $recent = HelpArticle::active()
            ->with('category:id,slug,name')
            ->orderByDesc('created_at')
            ->limit(4)
            ->get()
            ->map(fn (HelpArticle $a) => $this->formatArticleSummary($a));

        return response()->json([
            'success' => true,
            'data' => [
                'categories' => $categories,
                'popular' => $popular,
                'recent' => $recent,
            ],
        ]);
    }

    /**
     * Catégorie + liste de ses articles actifs.
     */
    public function showCategory(string $slug): JsonResponse
    {
        $category = HelpCategory::active()->where('slug', $slug)->firstOrFail();

        $articles = $category->articles()
            ->active()
            ->orderBy('position')
            ->orderBy('title')
            ->get()
            ->map(fn (HelpArticle $a) => $this->formatArticleSummary($a, withCategory: false));

        return response()->json([
            'success' => true,
            'data' => [
                'category' => $this->formatCategory($category),
                'articles' => $articles,
            ],
        ]);
    }

    /**
     * Détail d'un article (incrémente son compteur de vues).
     */
    public function showArticle(string $categorySlug, string $articleSlug): JsonResponse
    {
        $category = HelpCategory::active()->where('slug', $categorySlug)->firstOrFail();

        $article = $category->articles()
            ->active()
            ->where('slug', $articleSlug)
            ->firstOrFail();

        $article->increment('views_count');

        return response()->json([
            'success' => true,
            'data' => [
                'article' => [
                    'id' => $article->id,
                    ...$this->formatArticleSummary($article, withCategory: false),
                    'content_html' => $article->content_html,
                ],
                'category' => $this->formatCategory($category),
            ],
        ]);
    }

    /**
     * Recherche simple (titre / extrait) parmi les articles actifs.
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if ($q === '') {
            return response()->json(['success' => true, 'data' => []]);
        }

        $articles = HelpArticle::active()
            ->with('category:id,slug,name')
            ->where(function ($query) use ($q) {
                $query->where('title', 'ilike', "%{$q}%")
                    ->orWhere('excerpt', 'ilike', "%{$q}%");
            })
            ->orderByDesc('views_count')
            ->limit(20)
            ->get()
            ->map(fn (HelpArticle $a) => $this->formatArticleSummary($a));

        return response()->json(['success' => true, 'data' => $articles]);
    }

    /**
     * Vote "utile" / "pas utile" d'un utilisateur connecté sur un article
     * (un seul vote par utilisateur, modifiable). Le feedback anonyme est géré
     * côté front en local storage et n'atteint jamais cet endpoint.
     */
    public function feedback(Request $request, HelpArticle $article): JsonResponse
    {
        $data = $request->validate([
            'helpful' => ['required', 'boolean'],
        ]);

        HelpArticleFeedback::updateOrCreate(
            ['help_article_id' => $article->id, 'user_id' => $request->user()->id],
            ['helpful' => $data['helpful']],
        );

        return response()->json([
            'success' => true,
            'data' => [
                'helpful_yes_count' => $article->feedback()->where('helpful', true)->count(),
                'helpful_no_count' => $article->feedback()->where('helpful', false)->count(),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function formatCategory(HelpCategory $category): array
    {
        return [
            'slug' => $category->slug,
            'name' => $category->name,
            'icon' => $category->icon,
            'color' => $category->color,
            'description' => $category->description,
            'articles_count' => $category->articles_count ?? $category->articles()->active()->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function formatArticleSummary(HelpArticle $article, bool $withCategory = true): array
    {
        $data = [
            'slug' => $article->slug,
            'title' => $article->title,
            'excerpt' => $article->excerpt,
            'updated_at' => $article->updated_at?->toIso8601String(),
        ];

        if ($withCategory && $article->relationLoaded('category') && $article->category) {
            $data['category'] = [
                'slug' => $article->category->slug,
                'name' => $article->category->name,
            ];
        }

        return $data;
    }
}
