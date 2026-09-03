<?php

namespace App\Http\Controllers\Api\Content;

use App\Http\Controllers\Controller;
use App\Models\Content\ContentCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $categories = ContentCategory::query()
            ->forSubBrand($request->query('sub_brand'))
            ->orderBy('position')
            ->get(['id', 'slug', 'name', 'sub_brand']);

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }
}
