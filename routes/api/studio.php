<?php

use App\Http\Controllers\Api\DataIngestion\DatasetController;
use App\Http\Controllers\Studio\StudioBlockGateController;
use App\Http\Controllers\Studio\StudioBlockResponseController;
use App\Http\Controllers\Studio\StudioContentCollaboratorController;
use App\Http\Controllers\Studio\StudioContentCommentController;
use App\Http\Controllers\Studio\StudioContentInvitationPublicController;
use App\Http\Controllers\StudioContentController;
use Illuminate\Support\Facades\Route;

// Public read-only access (no auth required)
// Classification premium/freemium des blocs (palette du Studio) — voir /offres.
Route::get('/studio/block-gates', [StudioBlockGateController::class, 'index']);

Route::get('/studio/content/public', [StudioContentController::class, 'indexPublic']);
Route::get('/studio/content/public/catalog', [StudioContentController::class, 'catalogPublic']);
Route::get('/studio/content/public/mentions', [StudioContentController::class, 'mentionsPublic']);
Route::get('/studio/content/public/search', [StudioContentController::class, 'searchPublic'])
    ->middleware('throttle:60,1');
Route::get('/studio/content/public/{slug}', [StudioContentController::class, 'showPublic']);
Route::get('/studio/content/public/{slug}/datasets/{dataset}/query', [DatasetController::class, 'queryPublic']);
Route::get('/studio/content/public/{slug}/datasets/{dataset}/download', [DatasetController::class, 'downloadPublic']);

// Aperçu du mini-graphe de la carte de catalogue (premier graphique ou `card_block_id`)
Route::get('/studio/content/public/{slug}/card-preview', [DatasetController::class, 'cardPreviewPublic']);

// Blocs embarquables d'un Statsdata publié (bloc « sd-embed » des articles)
Route::get('/studio/content/public/{slug}/blocks', [StudioContentController::class, 'listPublicBlocks']);
Route::get('/studio/content/public/{slug}/blocks/{blockId}', [StudioContentController::class, 'showPublicBlock'])
    ->where('blockId', '[A-Za-z0-9_-]+');

// Public form/survey block responses (anonyme autorisé, throttle sur l'écriture)
Route::get('/studio/content/public/{slug}/blocks/{blockId}/response', [StudioBlockResponseController::class, 'show']);
Route::post('/studio/content/public/{slug}/blocks/{blockId}/response', [StudioBlockResponseController::class, 'store'])
    ->middleware('throttle:20,1');

// Commentaires lecteurs (lecture publique ; écriture / suppression authentifiées)
Route::get('/studio/content/public/{slug}/comments', [StudioContentCommentController::class, 'index']);

Route::get('/studio/content/access-permissions', [StudioContentCollaboratorController::class, 'permissionsCatalog']);
Route::get('/studio/content/invitations/{token}', [StudioContentInvitationPublicController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/studio/content/public/{slug}/comments', [StudioContentCommentController::class, 'store'])
        ->middleware('throttle:20,1');
    Route::delete('/studio/content/public/{slug}/comments/{commentId}', [StudioContentCommentController::class, 'destroy'])
        ->whereNumber('commentId');

    Route::post('/studio/content/invitations/{token}/accept', [StudioContentInvitationPublicController::class, 'accept']);

    Route::get('/studio/content', [StudioContentController::class, 'index']);
    Route::post('/studio/content', [StudioContentController::class, 'store']);
    Route::get('/studio/content/{slug}/data-sources', [StudioContentController::class, 'dataSources']);
    Route::get('/studio/content/{slug}', [StudioContentController::class, 'show']);
    Route::match(['put', 'patch'], '/studio/content/{slug}', [StudioContentController::class, 'update']);
    Route::delete('/studio/content/{slug}', [StudioContentController::class, 'destroy']);

    // Collaborateurs & invitations (propriétaire uniquement)
    Route::get('/studio/content/{slug}/collaborators', [StudioContentCollaboratorController::class, 'collaborators']);
    Route::patch('/studio/content/{slug}/collaborators/{userId}', [StudioContentCollaboratorController::class, 'updateCollaborator'])
        ->whereNumber('userId');
    Route::delete('/studio/content/{slug}/collaborators/{userId}', [StudioContentCollaboratorController::class, 'removeCollaborator'])
        ->whereNumber('userId');
    Route::post('/studio/content/{slug}/invitations', [StudioContentCollaboratorController::class, 'invite']);
    Route::get('/studio/content/{slug}/invitations', [StudioContentCollaboratorController::class, 'invitations']);
    Route::delete('/studio/content/{slug}/invitations/{invitationId}', [StudioContentCollaboratorController::class, 'revokeInvitation'])
        ->whereNumber('invitationId');

    // Publication versionnée
    Route::post('/studio/content/{slug}/publish', [StudioContentController::class, 'publish']);
    Route::post('/studio/content/{slug}/unpublish', [StudioContentController::class, 'unpublish']);
    Route::get('/studio/content/{slug}/versions', [StudioContentController::class, 'versions']);

    // Dossiers éditoriaux d'un contenu (placement + suggestions)
    Route::get('/studio/content/{slug}/dossiers', [StudioContentController::class, 'dossiers']);
    Route::put('/studio/content/{slug}/dossiers', [StudioContentController::class, 'syncDossiers']);
    Route::get('/studio/content/{slug}/dossier-suggestions', [StudioContentController::class, 'dossierSuggestions']);
    Route::post('/studio/content/{slug}/versions/{version}/restore', [StudioContentController::class, 'restoreVersion'])
        ->where('version', '[0-9]+');
});
