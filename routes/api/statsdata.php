<?php

use App\Http\Controllers\Api\StatsData\StatsDataDocumentController;
use App\Http\Controllers\Api\StatsData\StatsDataDocumentShareController;
use App\Http\Controllers\Api\StatsData\StatsDataQueryController;
use App\Http\Controllers\Api\StatsData\StatsDataSourceController;
use Illuminate\Support\Facades\Route;

Route::get('statsdata/public', [StatsDataDocumentController::class, 'publicIndex'])->name('statsdata.public.index');
Route::get('statsdata/public/{slug}', [StatsDataDocumentController::class, 'publicShow'])->name('statsdata.public.show');

Route::middleware('auth:api')->prefix('statsdata')->name('statsdata.')->group(function () {
    Route::get('/', [StatsDataDocumentController::class, 'index'])->name('index');
    Route::post('/', [StatsDataDocumentController::class, 'store'])->name('store');
    Route::get('/trashed', [StatsDataDocumentController::class, 'trashed'])->name('trashed');
    Route::get('/{id}', [StatsDataDocumentController::class, 'show'])->name('show');
    Route::patch('/{id}', [StatsDataDocumentController::class, 'update'])->name('update');
    Route::delete('/{id}', [StatsDataDocumentController::class, 'destroy'])->name('destroy');
    Route::post('/{id}/restore', [StatsDataDocumentController::class, 'restore'])->name('restore');
    Route::delete('/{id}/force', [StatsDataDocumentController::class, 'forceDelete'])->name('force-delete');

    Route::get('/{id}/shares', [StatsDataDocumentShareController::class, 'index'])->name('shares.index');
    Route::put('/{id}/shares', [StatsDataDocumentShareController::class, 'upsert'])->name('shares.upsert');
    Route::delete('/{id}/shares/{userId}', [StatsDataDocumentShareController::class, 'destroy'])->name('shares.destroy');

    Route::post('/{documentId}/query', [StatsDataQueryController::class, 'execute'])->name('query');

    Route::prefix('{documentId}/sources')->name('sources.')->group(function () {
        Route::get('/', [StatsDataSourceController::class, 'index'])->name('index');
        Route::post('/probe', [StatsDataSourceController::class, 'probe'])->name('probe');
        Route::post('/', [StatsDataSourceController::class, 'store'])->name('store');
        Route::get('/{sourceId}', [StatsDataSourceController::class, 'show'])->name('show');
        Route::get('/{sourceId}/mapping-suggestions', [StatsDataSourceController::class, 'mappingSuggestions'])->name('mapping-suggestions');
        Route::post('/{sourceId}/search-external', [StatsDataSourceController::class, 'searchExternal'])->name('search-external');
        Route::patch('/{sourceId}', [StatsDataSourceController::class, 'update'])->name('update');
        Route::delete('/{sourceId}', [StatsDataSourceController::class, 'destroy'])->name('destroy');
        Route::post('/{sourceId}/refresh', [StatsDataSourceController::class, 'refreshNormalized'])->name('refresh');
    });
});
