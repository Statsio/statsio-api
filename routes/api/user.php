<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\UserController;
use App\Http\Controllers\Api\User\ProfileReferenceDataController;

Route::get('/reference-data/profile', [ProfileReferenceDataController::class, 'index']);

/*
|--------------------------------------------------------------------------
| Auth routes (Sanctum)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:api')->group(function () {
    Route::get('/me', [UserController::class, 'me']);
    Route::match(['put', 'patch'], '/me', [UserController::class, 'update']);

    Route::prefix('account')->group(function () {
        Route::post('/anonymize', [UserController::class, 'anonymize']);
    });
});
