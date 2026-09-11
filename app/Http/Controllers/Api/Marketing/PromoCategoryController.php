<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Domain\Content\Enums\SubBrandEnum;
use App\Http\Controllers\Controller;
use App\Models\Marketing\PromoCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Catégories de promotion publiques (flash du bandeau promo) — gérées depuis
 * le back-office Filament (ressource « Catégories de promotion »).
 */
class PromoCategoryController extends Controller
{
    private const CACHE_TTL = 300;

    public function index(Request $request): JsonResponse
    {
        $subBrand = SubBrandEnum::sanitize($request->query('sub_brand'));

        $categories = Cache::remember(
            PromoCategory::CACHE_KEY.'.'.($subBrand ?? SubBrandEnum::All->value),
            self::CACHE_TTL,
            fn () => PromoCategory::active()->forSubBrand($subBrand)->get(),
        );

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }
}
