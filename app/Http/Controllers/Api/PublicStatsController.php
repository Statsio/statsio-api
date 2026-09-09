<?php

namespace App\Http\Controllers\Api;

use App\Domain\Content\Actions\PublicPlatformStatsAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PublicStatsController extends Controller
{
    /**
     * Chiffres publics de la plateforme (pages vitrine, écrans d'auth).
     */
    public function index(PublicPlatformStatsAction $action): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $action->execute(),
        ]);
    }
}
