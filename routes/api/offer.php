<?php

use App\Http\Controllers\Api\OfferController;
use Illuminate\Support\Facades\Route;

// Page publique /offres (freemium / premium) — pas d'auth requise.
Route::get('/offers', [OfferController::class, 'index']);
