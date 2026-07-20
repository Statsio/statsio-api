<?php

use App\Http\Controllers\Api\Media\MediaController;
use Illuminate\Support\Facades\Route;

Route::prefix('media')->name('media.')->group(function () {
    Route::get('/{media}/file', [MediaController::class, 'file'])->name('file');
    Route::get('/{media}', [MediaController::class, 'show'])->name('show');

    Route::middleware('auth:api')->group(function () {
        Route::post('/upload', [MediaController::class, 'upload'])->name('upload');
        Route::post('/upload-multiple', [MediaController::class, 'uploadMultiple'])->name('upload.multiple');
        Route::delete('/{media}', [MediaController::class, 'destroy'])->name('destroy');
    });
});
