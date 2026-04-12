<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\GoogleAuthController;
use App\Http\Controllers\Api\Auth\RefreshTokenController;
use App\Http\Controllers\Api\User\UserController;

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
    Route::post('/google', [GoogleAuthController::class, 'authenticate']);
    Route::post('/refresh', [RefreshTokenController::class, 'refresh']);

    // Auth protégée
    Route::middleware('auth:api')->group(function () {
        Route::post('/logout', [LogoutController::class, 'logout']);
        Route::get('/me', [UserController::class, 'me']);
    });
});
