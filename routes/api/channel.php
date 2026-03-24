<?php

use App\Http\Controllers\Api\Channel\ChannelController;
use Illuminate\Support\Facades\Route;

Route::prefix('channels')->name('channels.')->group(function () {
    Route::get('/', [ChannelController::class, 'index'])->name('index');
    Route::post('/', [ChannelController::class, 'create'])->name('create');
    Route::get('/{id}', [ChannelController::class, 'show'])->name('show');
    Route::put('/{id}', [ChannelController::class, 'update'])->name('update');
    Route::delete('/{id}', [ChannelController::class, 'destroy'])->name('destroy');
    
    // Gestion du statut
    Route::post('/{id}/suspend', [ChannelController::class, 'suspend'])->name('suspend');
    Route::post('/{id}/ban', [ChannelController::class, 'ban'])->name('ban');
    Route::post('/{id}/activate', [ChannelController::class, 'activate'])->name('activate');
    Route::post('/{id}/anonymize', [ChannelController::class, 'anonymize'])->name('anonymize');
});
