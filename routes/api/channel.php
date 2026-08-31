<?php

use App\Http\Controllers\Api\Channel\ChannelController;
use App\Http\Controllers\Api\Channel\ChannelInvitationPublicController;
use App\Http\Controllers\Api\Channel\ChannelOrganizationController;
use App\Http\Controllers\Api\Channel\ChannelValidationController;
use Illuminate\Support\Facades\Route;

Route::prefix('channels')->name('channels.')->group(function () {
    // Routes statiques en premier (avant les routes avec paramètres)
    Route::get('check-handle/{handle}', [ChannelValidationController::class, 'checkHandle'])->name('check-handle');
    Route::get('categories', [ChannelController::class, 'categories'])->name('categories');
    Route::get('catalog', [ChannelController::class, 'catalog'])->name('catalog');
    Route::get('permissions', [ChannelController::class, 'permissionsCatalog'])->name('permissions');
    // Lecture publique d'une invitation par token — AVANT {id} pour éviter que "invitations" soit capturé comme un id.
    Route::get('invitations/{token}', [ChannelInvitationPublicController::class, 'show'])->name('invitations.show');
    Route::get('/', [ChannelController::class, 'index'])->name('index');

    // Route "my" AVANT {id} pour éviter le conflit de routing
    Route::middleware('auth:api')->group(function () {
        Route::get('my', [ChannelController::class, 'myChannels'])->name('my');
        Route::post('invitations/{token}/accept', [ChannelInvitationPublicController::class, 'accept'])->name('invitations.accept');
        Route::post('/', [ChannelController::class, 'create'])->name('create');
        Route::put('{id}', [ChannelController::class, 'update'])->name('update');
        Route::post('{id}/media', [ChannelController::class, 'updateMedia'])->name('update-media');
        Route::delete('{id}', [ChannelController::class, 'destroy'])->name('destroy');
        Route::get('{id}/members', [ChannelController::class, 'members'])->name('members');
        Route::post('{id}/invitations', [ChannelController::class, 'inviteMembers'])->name('invitations.store');
        Route::get('{id}/invitations', [ChannelController::class, 'invitations'])->name('invitations.index');
        Route::delete('{id}/invitations/{invitationId}', [ChannelController::class, 'revokeInvitation'])->name('invitations.revoke');
        Route::get('{id}/subscribers', [ChannelController::class, 'subscribers'])->name('subscribers');
        Route::post('{id}/follow', [ChannelController::class, 'toggleFollow'])->name('follow');
        Route::get('{id}/stats', [ChannelController::class, 'stats'])->name('stats');
        Route::get('{id}/data-sources', [ChannelController::class, 'dataSources'])->name('data-sources');
        Route::put('{id}/featured', [ChannelController::class, 'updateFeaturedContent'])->name('featured.update');
        Route::get('{id}/organization/joinable', [ChannelOrganizationController::class, 'joinable'])->name('organization.joinable');
        Route::post('{id}/organization', [ChannelOrganizationController::class, 'store'])->name('organization.store');
        Route::put('{id}/organization', [ChannelOrganizationController::class, 'update'])->name('organization.update');
        Route::delete('{id}/organization', [ChannelOrganizationController::class, 'destroy'])->name('organization.destroy');
    });

    // Enregistrement d'une vue publique (public, throttlé)
    Route::post('{id}/view', [ChannelController::class, 'recordView'])
        ->name('record-view')
        ->middleware('throttle:30,1');

    // Lecture publique du contenu mis en avant (déjà inclus dans show(), exposé aussi seul)
    Route::get('{id}/featured', [ChannelController::class, 'getFeaturedContent'])->name('featured.show');

    // Route paramétrique en dernier
    Route::get('{id}', [ChannelController::class, 'show'])->name('show');
});
