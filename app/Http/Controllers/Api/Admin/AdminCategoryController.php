<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tv\TvCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            TvCategory::withCount('programs')->orderBy('name')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'  => ['required', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);

        $data['slug'] = Str::slug($data['name']);

        $category = TvCategory::create($data);

        return response()->json($category, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $category = TvCategory::findOrFail($id);

        $data = $request->validate([
            'name'  => ['sometimes', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);

        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $category->update($data);

        return response()->json($category);
    }

    public function destroy(int $id): JsonResponse
    {
        $category = TvCategory::withCount('programs')->findOrFail($id);

        if ($category->programs_count > 0) {
            return response()->json([
                'message' => "Impossible de supprimer : {$category->programs_count} programme(s) utilisent cette catégorie.",
            ], 422);
        }

        $category->delete();

        return response()->json(null, 204);
    }
}
