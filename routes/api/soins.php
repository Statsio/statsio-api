<?php

use App\Http\Controllers\Api\SoinsController;
use Illuminate\Support\Facades\Route;

// Public read-only access (no auth required) — relais vers WHO GHO OData + référentiel géographique statique
Route::get('/soins', [SoinsController::class, 'index']);
