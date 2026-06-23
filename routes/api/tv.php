<?php

use App\Http\Controllers\Api\TvController;
use Illuminate\Support\Facades\Route;

// Public TV routes (no auth required)
Route::prefix('tv')->name('tv.')->group(function () {
    Route::get('/channels', [TvController::class, 'channels'])->name('channels');
    Route::get('/epg', [TvController::class, 'epg'])->name('epg');
    Route::get('/audiences', [TvController::class, 'audiences'])->name('audiences');
    Route::get('/broadcasts/{id}', [TvController::class, 'broadcast'])->name('broadcast');
    Route::get('/broadcasts/{id}/schedule', [TvController::class, 'programmeSchedule'])->name('broadcast.schedule');
    Route::get('/broadcasts/{id}/reviews', [TvController::class, 'reviews'])->name('broadcast.reviews');
    Route::get('/broadcasts/{id}/questions', [TvController::class, 'reviewQuestions'])->name('broadcast.questions');
});

// Auth-protected TV routes
Route::middleware('auth:api')->prefix('tv')->name('tv.')->group(function () {
    Route::post('/broadcasts/{id}/view', [TvController::class, 'toggleView'])->name('broadcast.view');
    Route::post('/broadcasts/{id}/review', [TvController::class, 'submitReview'])->name('broadcast.review');
});
