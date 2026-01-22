<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\AnonymizeController;

/*
|--------------------------------------------------------------------------
| Auth routes (Sanctum)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('account')->group(function () {
        Route::post('/anonymize', [AnonymizeController::class, 'anonymize']);
    });
});
