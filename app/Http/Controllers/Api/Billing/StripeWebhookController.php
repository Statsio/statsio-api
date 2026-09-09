<?php

namespace App\Http\Controllers\Api\Billing;

use App\Domain\Billing\Actions\HandleStripeWebhookAction;
use App\Http\Controllers\Controller;
use App\Services\Billing\StripeGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;

class StripeWebhookController extends Controller
{
    /**
     * POST /billing/webhook — endpoint public appelé par Stripe. Signature vérifiée en
     * premier (Stripe\Webhook::constructEvent) ; traitement synchrone et idempotent —
     * voir HandleStripeWebhookAction. Renvoie 200 même pour un type d'événement ignoré,
     * pour ne pas faire désactiver le webhook par Stripe après des échecs répétés.
     */
    public function handle(Request $request, StripeGateway $stripe, HandleStripeWebhookAction $action): JsonResponse
    {
        try {
            $event = $stripe->constructWebhookEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
            );
        } catch (SignatureVerificationException $e) {
            return response()->json(['success' => false, 'message' => 'Signature du webhook Stripe invalide.'], 400);
        }

        $action->execute($event);

        return response()->json(['received' => true]);
    }
}
