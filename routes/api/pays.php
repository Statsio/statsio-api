<?php

use App\Http\Controllers\Api\PaysController;
use Illuminate\Support\Facades\Route;

// Public read-only access (no auth required) — relais vers WHO GHO OData + référentiel géographique statique
Route::get('/pays', [PaysController::class, 'index']);
Route::get('/pays/{iso3}', [PaysController::class, 'show'])->where('iso3', '[A-Za-z]{3}');
