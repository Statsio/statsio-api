<?php

use App\Http\Controllers\Api\Content\ContentCategoryController;
use App\Http\Controllers\Api\Marketing\PromoCategoryController;
use App\Http\Controllers\Api\PublicStatsController;
use Illuminate\Support\Facades\Route;

Route::get('/content-categories', [ContentCategoryController::class, 'index']);

// Catégories de promotion affichées en flash dans le bandeau promo (AppPromoBanner.vue)
Route::get('/promo-categories', [PromoCategoryController::class, 'index']);

// Chiffres publics de la plateforme (pages vitrine + écrans d'authentification)
Route::get('/public-stats', [PublicStatsController::class, 'index']);
