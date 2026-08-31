<?php

use App\Http\Controllers\Api\Identity\IdentityVerificationController;
use Illuminate\Support\Facades\Route;

// Vérification d'identité (Didit) — sondages « à identité vérifiée ».
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/identity/verification/start', [IdentityVerificationController::class, 'start'])
        ->middleware('throttle:10,1');
    Route::get('/identity/verification/status', [IdentityVerificationController::class, 'status']);
});

// Webhook appelé par Didit (public, signature HMAC vérifiée dans le contrôleur).
Route::post('/identity/verification/webhook', [IdentityVerificationController::class, 'webhook'])
    ->middleware('throttle:120,1');
