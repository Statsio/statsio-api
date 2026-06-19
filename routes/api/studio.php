<?php

use App\Http\Controllers\StudioContentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/studio/content', [StudioContentController::class, 'index']);
    Route::post('/studio/content', [StudioContentController::class, 'store']);
    Route::get('/studio/content/{content}', [StudioContentController::class, 'show']);
    Route::match(['put', 'patch'], '/studio/content/{content}', [StudioContentController::class, 'update']);
    Route::delete('/studio/content/{content}', [StudioContentController::class, 'destroy']);
});
