<?php

use App\Http\Controllers\StudioContentController;
use Illuminate\Support\Facades\Route;

// Public read-only access (no auth required)
Route::get('/studio/content/public', [StudioContentController::class, 'indexPublic']);
Route::get('/studio/content/public/{slug}', [StudioContentController::class, 'showPublic']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/studio/content', [StudioContentController::class, 'index']);
    Route::post('/studio/content', [StudioContentController::class, 'store']);
    Route::get('/studio/content/{slug}', [StudioContentController::class, 'show']);
    Route::match(['put', 'patch'], '/studio/content/{slug}', [StudioContentController::class, 'update']);
    Route::delete('/studio/content/{slug}', [StudioContentController::class, 'destroy']);
});
