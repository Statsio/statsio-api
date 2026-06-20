<?php

namespace App\Http\Controllers;

use App\Models\StudioContent;
use App\Models\DataIngestion\Dataset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'title' => 'required|string|max:255',
            'sections' => 'nullable|array',
            'blocks' => 'nullable|array',
        ]);

        $content = StudioContent::create([
            'user_id' => $request->user()->id,
            'title' => $data['title'],
            'sections' => $data['sections'] ?? [],
            'blocks' => $data['blocks'] ?? [],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->format($content),
        ], 201);
    }

    public function show(Request $request, StudioContent $content): JsonResponse
    {
        if ($content->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Non autorisé.'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $this->format($content),
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
            'data' => $contents->map(fn ($c) => $this->format($c)),
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
            'data' => $this->format($content),
        ]);
    }

    public function update(Request $request, StudioContent $content): JsonResponse
    {
        if ($content->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Non autorisé.'], 403);
        }

        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string|max:2000',
            'status' => 'sometimes|string|in:draft,published',
            'pages' => 'sometimes|nullable|array',
            'sections' => 'sometimes|nullable|array',
            'blocks' => 'sometimes|nullable|array',
        ]);

        $content->update($data);

        return response()->json([
            'success' => true,
            'data' => $this->format($content->fresh()),
        ]);
    }

    public function destroy(Request $request, StudioContent $content): JsonResponse
    {
        if ($content->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Non autorisé.'], 403);
        }

        $content->delete();

        return response()->json(['success' => true, 'message' => 'Contenu supprimé.']);
    }

    private function format(StudioContent $content): array
    {
        $profile    = $content->user?->profile;
        $authorName = trim(($profile?->first_name ?? '') . ' ' . ($profile?->last_name ?? ''));

        // Collect unique dataset IDs referenced in blocks
        $blocks     = $content->blocks ?? [];
        $datasetIds = array_values(array_unique(array_filter(
            array_map(fn($b) => $b['datasetId'] ?? null, $blocks)
        )));
        $datasets = [];
        if (!empty($datasetIds)) {
            $datasets = Dataset::whereIn('id', $datasetIds)
                ->where('user_id', $content->user_id)
                ->get(['id', 'name', 'row_count'])
                ->map(fn($d) => ['id' => $d->id, 'name' => $d->name, 'row_count' => $d->row_count])
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
