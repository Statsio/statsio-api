<?php

use App\Http\Controllers\Api\Content\ContentCategoryController;
use Illuminate\Support\Facades\Route;

Route::get('/content-categories', [ContentCategoryController::class, 'index']);
