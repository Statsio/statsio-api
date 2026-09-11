<?php

namespace App\Http\Controllers\Studio;

use App\Domain\Content\Support\PremiumBlockGate;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Classification premium/freemium des blocs du Studio, consommée par le front
 * pour afficher la pastille « Premium » et bloquer l'ajout côté client (le
 * back reste la source de vérité — voir StudioContentController::store/update).
 */
class StudioBlockGateController extends Controller
{
    public function index(PremiumBlockGate $premiumGate): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'premium_block_types' => $premiumGate->premiumTypes(),
                'premium_block_offers' => $premiumGate->requiredOffersByType(),
            ],
        ]);
    }
}
