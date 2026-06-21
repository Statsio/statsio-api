<?php

namespace App\Http\Controllers;

use App\Models\StudioContent;
use App\Models\DataIngestion\Dataset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StudioContentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $contents = StudioContent::where('user_id', $request->user()->id)
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $contents->map(fn ($c) => $this->format($c)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'    => 'required|string|max:255',
            'sections' => 'nullable|array',
            'blocks'   => 'nullable|array',
        ]);

        $content = StudioContent::create([
            'user_id'  => $request->user()->id,
            'title'    => $data['title'],
            'slug'     => $this->generateUniqueSlug($data['title']),
            'sections' => $data['sections'] ?? [],
            'blocks'   => $data['blocks'] ?? [],
        ]);

        return response()->json([
            'success' => true,
            'data'    => $this->format($content),
        ], 201);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $content = $this->findBySlug($request->user()->id, $slug);

        return response()->json([
            'success' => true,
            'data'    => $this->format($content),
        ]);
    }

    public function indexPublic(): JsonResponse
    {
        $contents = StudioContent::with('user.profile')
            ->where('status', 'published')
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $contents->map(fn ($c) => $this->format($c)),
        ]);
    }

    public function showPublic(string $slug): JsonResponse
    {
        $content = StudioContent::with('user.profile')
            ->where('status', 'published')
            ->where(function ($q) use ($slug) {
                $q->where('slug', $slug);
                if (is_numeric($slug)) {
                    $q->orWhere('id', (int) $slug);
                }
            })
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data'    => $this->format($content),
        ]);
    }

    public function update(Request $request, string $slug): JsonResponse
    {
        $content = $this->findBySlug($request->user()->id, $slug);

        $data = $request->validate([
            'title'       => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string|max:2000',
            'status'      => 'sometimes|string|in:draft,published',
            'pages'       => 'sometimes|nullable|array',
            'sections'    => 'sometimes|nullable|array',
            'blocks'      => 'sometimes|nullable|array',
        ]);

        $content->update($data);

        return response()->json([
            'success' => true,
            'data'    => $this->format($content->fresh()),
        ]);
    }

    public function destroy(Request $request, string $slug): JsonResponse
    {
        $content = $this->findBySlug($request->user()->id, $slug);
        $content->delete();

        return response()->json(['success' => true, 'message' => 'Contenu supprimé.']);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────────

    private function findBySlug(int $userId, string $slug): StudioContent
    {
        return StudioContent::where('user_id', $userId)
            ->where(function ($q) use ($slug) {
                $q->where('slug', $slug);
                if (is_numeric($slug)) {
                    $q->orWhere('id', (int) $slug);
                }
            })
            ->firstOrFail();
    }

    private function generateUniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'statsdata';
        $slug = $base;
        $i    = 2;
        while (StudioContent::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }
        return $slug;
    }

    private function format(StudioContent $content): array
    {
        $profile    = $content->user?->profile;
        $authorName = trim(($profile?->first_name ?? '') . ' ' . ($profile?->last_name ?? ''));

        $blocks     = $content->blocks ?? [];
        $datasetIds = array_values(array_unique(array_filter(
            array_map(fn ($b) => $b['datasetId'] ?? null, $blocks)
        )));

        $datasets = [];
        if (!empty($datasetIds)) {
            $datasets = Dataset::whereIn('id', $datasetIds)
                ->where('user_id', $content->user_id)
                ->get(['id', 'name', 'row_count'])
                ->map(fn ($d) => ['id' => $d->id, 'name' => $d->name, 'row_count' => $d->row_count])
                ->toArray();
        }

        return [
            'id'          => (string) $content->id,
            'title'       => $content->title,
            'description' => $content->description,
            'status'      => $content->status ?? 'draft',
            'slug'        => $content->slug,
            'author'      => ['name' => $authorName ?: 'Anonyme'],
            'datasets'    => $datasets,
            'pages'       => $content->pages ?? [],
            'sections'    => $content->sections ?? [],
            'blocks'      => $content->blocks ?? [],
            'created_at'  => $content->created_at->toIso8601String(),
            'updated_at'  => $content->updated_at->toIso8601String(),
        ];
    }
}
