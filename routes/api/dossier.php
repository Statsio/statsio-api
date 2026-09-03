<?php

use App\Http\Controllers\Api\Content\DossierController;
use Illuminate\Support\Facades\Route;

Route::get('/dossiers/pinned', [DossierController::class, 'pinned']);
Route::middleware('auth:sanctum')->get('/dossiers', [DossierController::class, 'index']);
