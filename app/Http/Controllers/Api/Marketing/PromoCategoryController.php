<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\PromoCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Catégories de promotion publiques (flash du bandeau promo) — gérées depuis
 * le back-office Filament (ressource « Catégories de promotion »).
 */
class PromoCategoryController extends Controller
{
    private const CACHE_TTL = 300;

    public function index(): JsonResponse
    {
        $categories = Cache::remember(
            PromoCategory::CACHE_KEY,
            self::CACHE_TTL,
            fn () => PromoCategory::active()->get(),
        );

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }
}
