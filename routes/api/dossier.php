<?php

use App\Http\Controllers\Api\Content\DossierController;
use Illuminate\Support\Facades\Route;

Route::get('/dossiers/pinned', [DossierController::class, 'pinned']);
Route::get('/dossiers/catalog', [DossierController::class, 'catalog']);
Route::get('/dossiers/public/{slug}', [DossierController::class, 'showPublic']);
Route::middleware('auth:sanctum')->get('/dossiers', [DossierController::class, 'index']);
