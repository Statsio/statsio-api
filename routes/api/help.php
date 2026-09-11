<?php

use App\Http\Controllers\Api\Help\HelpCenterController;
use Illuminate\Support\Facades\Route;

Route::get('/help/home', [HelpCenterController::class, 'home']);
Route::get('/help/search', [HelpCenterController::class, 'search']);
Route::get('/help/categories/{slug}', [HelpCenterController::class, 'showCategory']);
Route::get('/help/categories/{categorySlug}/articles/{articleSlug}', [HelpCenterController::class, 'showArticle']);
Route::middleware('auth:sanctum')->post('/help/articles/{article}/feedback', [HelpCenterController::class, 'feedback']);
