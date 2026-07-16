<?php

use App\Http\Controllers\Api\MaladiesController;
use Illuminate\Support\Facades\Route;

// Public read-only access (no auth required) — relais vers ICD-11 (id.who.int) + WHO GHO + UMLS
Route::get('/maladies/search', [MaladiesController::class, 'search']);
Route::get('/maladies/populaires', [MaladiesController::class, 'populaires']);
Route::get('/maladies/{id}', [MaladiesController::class, 'show'])->where('id', '\d+');
