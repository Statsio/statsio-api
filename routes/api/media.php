<?php

use App\Http\Controllers\Api\Media\MediaController;
use Illuminate\Support\Facades\Route;

Route::prefix('media')->name('media.')->group(function () {
    Route::post('/upload', [MediaController::class, 'upload'])->name('upload');
    Route::post('/upload-multiple', [MediaController::class, 'uploadMultiple'])->name('upload.multiple');
    Route::get('/{media}', [MediaController::class, 'show'])->name('show');
    Route::delete('/{media}', [MediaController::class, 'destroy'])->name('destroy');
});
