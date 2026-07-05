<?php

use App\Http\Controllers\Api\DataIngestion\DataSourceController;
use App\Http\Controllers\Api\DataIngestion\DatasetController;
use App\Http\Controllers\Api\DataIngestion\SourceProvenanceController;
use Illuminate\Support\Facades\Route;

Route::get('/source-provenances', [SourceProvenanceController::class, 'index']);

Route::middleware('auth:api')->group(function () {

    Route::prefix('data-sources')->name('data-sources.')->group(function () {
        Route::post('/upload', [DataSourceController::class, 'upload'])->name('upload');
        Route::get('/public', [DataSourceController::class, 'publicCatalog'])->name('public');
        Route::get('/', [DataSourceController::class, 'index'])->name('index');
        Route::get('/{dataSource}', [DataSourceController::class, 'show'])->name('show');
        Route::patch('/{dataSource}', [DataSourceController::class, 'update'])->name('update');
        Route::post('/{dataSource}/attach', [DataSourceController::class, 'attachPublic'])->name('attach');
        Route::post('/{dataSource}/refresh', [DataSourceController::class, 'refresh'])->name('refresh');
    });

    Route::post('/api-sources', [DataSourceController::class, 'createFromApi'])->name('api-sources.store');

    Route::prefix('datasets')->name('datasets.')->group(function () {
        Route::get('/', [DatasetController::class, 'index'])->name('index');
        Route::get('/{dataset}', [DatasetController::class, 'show'])->name('show');
        Route::get('/{dataset}/preview', [DatasetController::class, 'preview'])->name('preview');
        Route::get('/{dataset}/query', [DatasetController::class, 'query'])->name('query');
        Route::patch('/{dataset}', [DatasetController::class, 'update'])->name('update');
        Route::delete('/{dataset}', [DatasetController::class, 'destroy'])->name('destroy');
    });

});
