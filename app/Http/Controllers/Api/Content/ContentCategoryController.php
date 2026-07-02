<?php

namespace App\Http\Controllers\Api\Content;

use App\Http\Controllers\Controller;
use App\Models\Content\ContentCategory;
use Illuminate\Http\JsonResponse;

class ContentCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = ContentCategory::orderBy('position')->get(['id', 'slug', 'name']);

        return response()->json([
            'success' => true,
            'data'    => $categories,
        ]);
    }
}
