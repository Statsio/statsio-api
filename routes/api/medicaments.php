<?php

use App\Http\Controllers\Api\MedicamentsController;
use Illuminate\Support\Facades\Route;

// Public read-only access (no auth required) — relais vers medicaments-api.giygas.dev
Route::get('/medicaments/search', [MedicamentsController::class, 'search']);
Route::get('/medicaments/generiques', [MedicamentsController::class, 'generiques']);
Route::get('/medicaments/{cis}', [MedicamentsController::class, 'show'])->where('cis', '\d+');
