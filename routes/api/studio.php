<?php

use App\Http\Controllers\Api\DataIngestion\DatasetController;
use App\Http\Controllers\Studio\StudioBlockResponseController;
use App\Http\Controllers\StudioContentController;
use Illuminate\Support\Facades\Route;

// Public read-only access (no auth required)
Route::get('/studio/content/public', [StudioContentController::class, 'indexPublic']);
Route::get('/studio/content/public/{slug}', [StudioContentController::class, 'showPublic']);
Route::get('/studio/content/public/{slug}/datasets/{dataset}/query', [DatasetController::class, 'queryPublic']);

// Public form/survey block responses (anonyme autorisé, throttle sur l'écriture)
Route::get('/studio/content/public/{slug}/blocks/{blockId}/response', [StudioBlockResponseController::class, 'show']);
Route::post('/studio/content/public/{slug}/blocks/{blockId}/response', [StudioBlockResponseController::class, 'store'])
    ->middleware('throttle:20,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/studio/content', [StudioContentController::class, 'index']);
    Route::post('/studio/content', [StudioContentController::class, 'store']);
    Route::get('/studio/content/{slug}', [StudioContentController::class, 'show']);
    Route::match(['put', 'patch'], '/studio/content/{slug}', [StudioContentController::class, 'update']);
    Route::delete('/studio/content/{slug}', [StudioContentController::class, 'destroy']);
});
