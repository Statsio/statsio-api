<?php

namespace App\Http\Controllers\Api\Billing;

use App\Domain\Billing\Actions\CreateCheckoutSessionAction;
use App\Domain\Billing\Actions\CreatePortalSessionAction;
use App\Domain\Billing\Exceptions\StripeNotConfiguredException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    /**
     * POST /billing/checkout-session — démarre une session Stripe Checkout (abonnement
     * mensuel) pour l'offre Premium active et renvoie l'URL hébergée vers laquelle rediriger.
     */
    public function checkoutSession(Request $request, CreateCheckoutSessionAction $action): JsonResponse
    {
        try {
            $url = $action->execute($request->user());
        } catch (StripeNotConfiguredException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 503);
        }

        return response()->json(['success' => true, 'data' => ['url' => $url]]);
    }

    /**
     * POST /billing/portal-session — ouvre le portail client Stripe (moyen de paiement,
     * factures, annulation) et renvoie l'URL hébergée vers laquelle rediriger.
     */
    public function portalSession(Request $request, CreatePortalSessionAction $action): JsonResponse
    {
        try {
            $url = $action->execute($request->user());
        } catch (StripeNotConfiguredException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 503);
        }

        return response()->json(['success' => true, 'data' => ['url' => $url]]);
    }
}
