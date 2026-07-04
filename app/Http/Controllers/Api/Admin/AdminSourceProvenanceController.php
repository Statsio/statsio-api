<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataIngestion\SourceProvenance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminSourceProvenanceController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            SourceProvenance::withCount('dataSources')->orderBy('position')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $data['slug'] = Str::slug($data['name']);
        $data['position'] = (int) SourceProvenance::max('position') + 1;

        $provenance = SourceProvenance::create($data);

        return response()->json($provenance, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $provenance = SourceProvenance::findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
        ]);

        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $provenance->update($data);

        return response()->json($provenance);
    }

    public function destroy(int $id): JsonResponse
    {
        $provenance = SourceProvenance::withCount('dataSources')->findOrFail($id);

        if ($provenance->data_sources_count > 0) {
            return response()->json([
                'message' => "Impossible de supprimer : {$provenance->data_sources_count} source(s) utilisent cette provenance.",
            ], 422);
        }

        $provenance->delete();

        return response()->json(null, 204);
    }
}
