<?php

use App\Http\Controllers\Api\Support\ContactMessageController;
use Illuminate\Support\Facades\Route;

Route::post('/contact', [ContactMessageController::class, 'store'])->name('contact.store');
