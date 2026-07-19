<?php

use App\Http\Controllers\Api\Channel\ChannelController;
use App\Http\Controllers\Api\Channel\ChannelValidationController;
use Illuminate\Support\Facades\Route;

Route::prefix('channels')->name('channels.')->group(function () {
    // Routes statiques en premier (avant les routes avec paramètres)
    Route::get('check-handle/{handle}', [ChannelValidationController::class, 'checkHandle'])->name('check-handle');
    Route::get('categories', [ChannelController::class, 'categories'])->name('categories');
    Route::get('/', [ChannelController::class, 'index'])->name('index');

    // Route "my" AVANT {id} pour éviter le conflit de routing
    Route::middleware('auth:api')->group(function () {
        Route::get('my', [ChannelController::class, 'myChannels'])->name('my');
        Route::post('/', [ChannelController::class, 'create'])->name('create');
        Route::put('{id}', [ChannelController::class, 'update'])->name('update');
        Route::post('{id}/media', [ChannelController::class, 'updateMedia'])->name('update-media');
        Route::delete('{id}', [ChannelController::class, 'destroy'])->name('destroy');
        Route::get('{id}/members', [ChannelController::class, 'members'])->name('members');
        Route::get('{id}/subscribers', [ChannelController::class, 'subscribers'])->name('subscribers');
        Route::post('{id}/follow', [ChannelController::class, 'toggleFollow'])->name('follow');
        Route::get('{id}/stats', [ChannelController::class, 'stats'])->name('stats');
        Route::post('{id}/suspend', [ChannelController::class, 'suspend'])->name('suspend');
        Route::post('{id}/ban', [ChannelController::class, 'ban'])->name('ban');
        Route::post('{id}/activate', [ChannelController::class, 'activate'])->name('activate');
        Route::post('{id}/anonymize', [ChannelController::class, 'anonymize'])->name('anonymize');
    });

    // Enregistrement d'une vue publique (public, throttlé)
    Route::post('{id}/view', [ChannelController::class, 'recordView'])
        ->name('record-view')
        ->middleware('throttle:30,1');

    // Route paramétrique en dernier
    Route::get('{id}', [ChannelController::class, 'show'])->name('show');
});
