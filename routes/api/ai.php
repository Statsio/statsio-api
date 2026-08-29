<?php

use App\Http\Controllers\Api\Ai\StudioAgentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('ai/studio')->group(function () {
    Route::get('/contents/{content}/conversations', [StudioAgentController::class, 'listConversations']);
    Route::post('/contents/{content}/conversations', [StudioAgentController::class, 'createConversation']);
    Route::get('/contents/{content}/conversations/{conversation}', [StudioAgentController::class, 'showConversation']);

    Route::delete('/conversations/{conversation}', [StudioAgentController::class, 'deleteConversation']);
    Route::post('/conversations/{conversation}/messages', [StudioAgentController::class, 'sendMessage'])
        ->middleware('throttle:20,1');

    Route::get('/runs/{run}', [StudioAgentController::class, 'showRun']);
});
