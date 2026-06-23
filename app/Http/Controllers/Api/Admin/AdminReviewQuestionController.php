<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tv\TvReviewQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminReviewQuestionController extends Controller
{
    public function index(): JsonResponse
    {
        $questions = TvReviewQuestion::orderBy('sort_order')->orderBy('id')->get();

        return response()->json($questions);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'label'          => ['required', 'string', 'max:255'],
            'description'    => ['nullable', 'string', 'max:500'],
            'category_slugs' => ['nullable', 'array'],
            'category_slugs.*' => ['string', 'max:100'],
            'is_active'      => ['boolean'],
            'sort_order'     => ['integer', 'min:0'],
        ]);

        $question = TvReviewQuestion::create($data);

        return response()->json($question, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $question = TvReviewQuestion::findOrFail($id);

        $data = $request->validate([
            'label'          => ['sometimes', 'string', 'max:255'],
            'description'    => ['nullable', 'string', 'max:500'],
            'category_slugs' => ['nullable', 'array'],
            'category_slugs.*' => ['string', 'max:100'],
            'is_active'      => ['boolean'],
            'sort_order'     => ['integer', 'min:0'],
        ]);

        $question->update($data);

        return response()->json($question->fresh());
    }

    public function destroy(int $id): JsonResponse
    {
        $question = TvReviewQuestion::findOrFail($id);
        $question->delete();

        return response()->json(null, 204);
    }
}
