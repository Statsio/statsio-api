<?php

use App\Http\Controllers\Api\Auth\EmailVerificationController;
use App\Http\Controllers\Api\Auth\GoogleAuthController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\PasswordResetController;
use App\Http\Controllers\Api\Auth\RefreshTokenController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\User\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth routes (Sanctum)
|--------------------------------------------------------------------------
*/

// regroupe toutes les routes d'authentification sous le préfixe /auth
Route::prefix('auth')->group(function () {
    // publique — limitées à 10 tentatives par minute par IP
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/login', [LoginController::class, 'login'])->name('login');
        Route::post('/register', [RegisterController::class, 'register']);
        Route::post('/google', [GoogleAuthController::class, 'authenticate']);
        Route::post('/refresh', [RefreshTokenController::class, 'refresh']);
        Route::post('/verify-email', [EmailVerificationController::class, 'verify']);
        Route::post('/verify-email/resend', [EmailVerificationController::class, 'resend']);
        Route::post('/forgot-password', [PasswordResetController::class, 'forgot']);
        Route::post('/reset-password', [PasswordResetController::class, 'reset']);
    });

    // Auth protégée
    Route::middleware('auth:api')->group(function () {
        Route::post('/logout', [LogoutController::class, 'logout']);
        Route::get('/me', [UserController::class, 'me']);
    });
});
