<?php

use App\Http\Controllers\Api\User\AccountSearchController;
use App\Http\Controllers\Api\User\ContentHistoryController;
use App\Http\Controllers\Api\User\FavoriteController;
use App\Http\Controllers\Api\User\ProfileReferenceDataController;
use App\Http\Controllers\Api\User\SubscriptionController;
use App\Http\Controllers\Api\User\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/reference-data/profile', [ProfileReferenceDataController::class, 'index']);

/*
|--------------------------------------------------------------------------
| Auth routes (Sanctum)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:api')->group(function () {
    Route::get('/me', [UserController::class, 'me']);
    Route::match(['put', 'patch'], '/me', [UserController::class, 'update']);
    Route::post('/me/avatar', [UserController::class, 'updateAvatar']);
    Route::delete('/me/avatar', [UserController::class, 'deleteAvatar']);

    // Espace compte : favoris, historique, abonnements, recherche transverse.
    Route::get('/me/favorites', [FavoriteController::class, 'index']);
    Route::post('/me/favorites', [FavoriteController::class, 'toggle']);
    Route::delete('/me/favorites/{id}', [FavoriteController::class, 'destroy']);

    Route::get('/me/history', [ContentHistoryController::class, 'index']);
    Route::get('/me/history/in-progress', [ContentHistoryController::class, 'inProgress']);
    Route::post('/me/history', [ContentHistoryController::class, 'store']);
    Route::delete('/me/history', [ContentHistoryController::class, 'clear']);

    Route::get('/me/subscriptions', [SubscriptionController::class, 'index']);

    Route::get('/me/search', [AccountSearchController::class, 'index']);

    Route::prefix('account')->group(function () {
        Route::post('/anonymize', [UserController::class, 'anonymize']);
    });
});
