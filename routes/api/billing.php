<?php

use App\Http\Controllers\Api\Billing\BillingController;
use App\Http\Controllers\Api\Billing\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/billing/checkout-session', [BillingController::class, 'checkoutSession']);
    Route::post('/billing/portal-session', [BillingController::class, 'portalSession']);
});

// Appelé par Stripe (public, signature vérifiée dans le contrôleur — voir StripeGateway::constructWebhookEvent()).
Route::post('/billing/webhook', [StripeWebhookController::class, 'handle'])
    ->middleware('throttle:120,1');
