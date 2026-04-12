<?php

use App\Http\Controllers\Api\StatsData\StatsDataDocumentController;
use App\Http\Controllers\Api\StatsData\StatsDataSourceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->prefix('statsdata')->name('statsdata.')->group(function () {
    Route::post('/', [StatsDataDocumentController::class, 'store'])->name('store');
    Route::get('/{id}', [StatsDataDocumentController::class, 'show'])->name('show');
    Route::patch('/{id}', [StatsDataDocumentController::class, 'update'])->name('update');
    Route::delete('/{id}', [StatsDataDocumentController::class, 'destroy'])->name('destroy');

    Route::prefix('{documentId}/sources')->name('sources.')->group(function () {
        Route::get('/', [StatsDataSourceController::class, 'index'])->name('index');
        Route::post('/probe', [StatsDataSourceController::class, 'probe'])->name('probe');
        Route::post('/', [StatsDataSourceController::class, 'store'])->name('store');
        Route::get('/{sourceId}', [StatsDataSourceController::class, 'show'])->name('show');
        Route::patch('/{sourceId}', [StatsDataSourceController::class, 'update'])->name('update');
        Route::delete('/{sourceId}', [StatsDataSourceController::class, 'destroy'])->name('destroy');
    });
});
