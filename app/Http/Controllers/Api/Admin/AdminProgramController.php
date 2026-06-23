<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tv\TvProgram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminProgramController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = TvProgram::query()
            ->withCount('broadcasts')
            ->with('categories:id,name,slug,color')
            ->orderBy('title');

        if ($request->filled('search')) {
            $query->whereRaw('title ilike ?', ['%' . $request->search . '%']);
        }

        if ($request->filled('channel')) {
            $query->where('tv_channel_id', $request->channel);
        }

        if ($request->filled('type')) {
            $query->whereRaw('type ilike ?', ['%' . $request->type . '%']);
        }

        if ($request->filled('pick')) {
            $query->where('is_tvstats_pick', true);
        }

        $programs = $query->paginate(25);

        return response()->json($programs);
    }

    public function show(int $id): JsonResponse
    {
        $program = TvProgram::withCount('broadcasts')
            ->with('categories:id,name,slug,color')
            ->findOrFail($id);

        $program->load(['broadcasts' => function ($q) {
            $q->orderByDesc('start_at')->limit(25)->with('audience');
        }]);

        return response()->json($program);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $program = TvProgram::findOrFail($id);

        $data = $request->validate([
            'title'           => ['sometimes', 'string', 'max:255'],
            'type'            => ['nullable', 'string', 'max:100'],
            'description'     => ['nullable', 'string', 'max:5000'],
            'image_url'       => ['nullable', 'max:500'],
            'youtube_url'     => ['nullable', 'max:500'],
            'is_tvstats_pick' => ['sometimes', 'boolean'],
            'category_ids'    => ['sometimes', 'array'],
            'category_ids.*'  => ['integer', 'exists:tv_categories,id'],
        ]);

        $categoryIds = $data['category_ids'] ?? null;
        unset($data['category_ids']);

        $program->update($data);

        if ($categoryIds !== null) {
            $program->categories()->sync($categoryIds);
        }

        $program->load('categories:id,name,slug,color');

        return response()->json($program);
    }

    public function destroy(int $id): JsonResponse
    {
        $program = TvProgram::findOrFail($id);

        $program->broadcasts()->each(function ($broadcast) {
            $broadcast->audience()->delete();
            $broadcast->userViews()->delete();
            $broadcast->delete();
        });

        $program->delete();

        return response()->json(null, 204);
    }
}
