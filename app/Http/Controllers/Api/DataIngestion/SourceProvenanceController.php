<?php

namespace App\Http\Controllers\Api\DataIngestion;

use App\Http\Controllers\Controller;
use App\Models\DataIngestion\SourceProvenance;
use Illuminate\Http\JsonResponse;

class SourceProvenanceController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => SourceProvenance::orderBy('position')->get(['id', 'slug', 'name']),
        ]);
    }
}
