<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\UserController;

/*
|--------------------------------------------------------------------------
| Auth routes (Sanctum)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:api')->group(function () {
    Route::get('/me', [UserController::class, 'me']);

    Route::prefix('account')->group(function () {
        Route::post('/anonymize', [UserController::class, 'anonymize']);
    });
});
