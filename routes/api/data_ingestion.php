<?php

use App\Http\Controllers\Api\DataIngestion\DataSourceController;
use App\Http\Controllers\Api\DataIngestion\DatasetController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function () {

    Route::prefix('data-sources')->name('data-sources.')->group(function () {
        Route::post('/upload', [DataSourceController::class, 'upload'])->name('upload');
        Route::get('/', [DataSourceController::class, 'index'])->name('index');
        Route::get('/{dataSource}', [DataSourceController::class, 'show'])->name('show');
    });

    Route::prefix('datasets')->name('datasets.')->group(function () {
        Route::get('/', [DatasetController::class, 'index'])->name('index');
        Route::get('/{dataset}', [DatasetController::class, 'show'])->name('show');
        Route::get('/{dataset}/preview', [DatasetController::class, 'preview'])->name('preview');
        Route::get('/{dataset}/query', [DatasetController::class, 'query'])->name('query');
        Route::patch('/{dataset}', [DatasetController::class, 'update'])->name('update');
        Route::delete('/{dataset}', [DatasetController::class, 'destroy'])->name('destroy');
    });

});
