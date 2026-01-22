<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\LogoutController;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Auth routes (Sanctum)
|--------------------------------------------------------------------------
*/

// regroupe toutes les routes d'authentification sous le préfixe /auth
Route::prefix('auth')->group(function () {
    // publique
    Route::post('/login', [LoginController::class, 'login'])->name('login');
    Route::post('/register', [RegisterController::class, 'register']);

    // Auth protégée
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [LogoutController::class, 'logout']);

        Route::get('/me', function (Request $request) {
            return $request->user();
        });
    });
});
