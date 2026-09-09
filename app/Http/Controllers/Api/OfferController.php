<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\OfferComparisonRow;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Offres publiques (page /offres) — gérées depuis le back-office Filament
 * (ressources « Offres » et « Comparatif des offres »).
 */
class OfferController extends Controller
{
    private const CACHE_TTL = 300;

    public function index(): JsonResponse
    {
        $payload = Cache::remember(Offer::CACHE_KEY, self::CACHE_TTL, function () {
            return [
                'offers' => Offer::active()->get(),
                'comparison_rows' => OfferComparisonRow::ordered()->get(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }
}
