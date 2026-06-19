<?php

namespace App\Http\Controllers;

use App\Models\StudioContent;
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

    public function update(Request $request, StudioContent $content): JsonResponse
    {
        if ($content->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Non autorisé.'], 403);
        }

        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
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
        return [
            'id' => (string) $content->id,
            'title' => $content->title,
            'slug' => $content->slug,
            'sections' => $content->sections ?? [],
            'blocks' => $content->blocks ?? [],
            'created_at' => $content->created_at->toIso8601String(),
            'updated_at' => $content->updated_at->toIso8601String(),
        ];
    }
}
