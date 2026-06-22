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
            ->orderBy('title');

        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->whereRaw('title ilike ?', [$search]);
        }

        if ($request->filled('channel')) {
            $query->where('tv_channel_id', $request->channel);
        }

        if ($request->filled('type')) {
            $query->whereRaw('type ilike ?', ['%' . $request->type . '%']);
        }

        $programs = $query->paginate(25);

        return response()->json($programs);
    }

    public function show(int $id): JsonResponse
    {
        $program = TvProgram::withCount('broadcasts')->findOrFail($id);

        $program->load(['broadcasts' => function ($q) {
            $q->orderByDesc('start_at')->limit(25)->with('audience');
        }]);

        return response()->json($program);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $program = TvProgram::findOrFail($id);

        $data = $request->validate([
            'title'       => ['sometimes', 'string', 'max:255'],
            'type'        => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        $program->update($data);

        return response()->json($program);
    }

    public function destroy(int $id): JsonResponse
    {
        $program = TvProgram::findOrFail($id);

        // Cascades broadcasts + audiences
        $program->broadcasts()->each(function ($broadcast) {
            $broadcast->audience()->delete();
            $broadcast->userViews()->delete();
            $broadcast->delete();
        });

        $program->delete();

        return response()->json(null, 204);
    }
}
