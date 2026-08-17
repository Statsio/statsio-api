<?php

use App\Http\Controllers\Api\Admin\AdminBroadcastController;
use App\Http\Controllers\Api\Admin\AdminCategoryController;
use App\Http\Controllers\Api\Admin\AdminChannelController;
use App\Http\Controllers\Api\Admin\AdminContactMessageController;
use App\Http\Controllers\Api\Admin\AdminEditorialChannelController;
use App\Http\Controllers\Api\Admin\AdminProgramController;
use App\Http\Controllers\Api\Admin\AdminReviewQuestionController;
use App\Http\Controllers\Api\Admin\AdminSourceProvenanceController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    // Users
    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('/users/{id}', [AdminUserController::class, 'show'])->name('users.show');
    Route::patch('/users/{id}', [AdminUserController::class, 'update'])->name('users.update');
    Route::delete('/users/{id}', [AdminUserController::class, 'destroy'])->name('users.destroy');
    Route::post('/users/{id}/restore', [AdminUserController::class, 'restore'])->name('users.restore');

    // Channels — logo upload route must come before {id} to avoid conflict
    Route::get('/tv/channels', [AdminChannelController::class, 'index'])->name('tv.channels.index');
    Route::post('/tv/channels', [AdminChannelController::class, 'store'])->name('tv.channels.store');
    Route::get('/tv/channels/{id}', [AdminChannelController::class, 'show'])->name('tv.channels.show');
    Route::patch('/tv/channels/{id}', [AdminChannelController::class, 'update'])->name('tv.channels.update');
    Route::post('/tv/channels/{id}/logo', [AdminChannelController::class, 'uploadLogo'])->name('tv.channels.logo');
    Route::delete('/tv/channels/{id}', [AdminChannelController::class, 'destroy'])->name('tv.channels.destroy');

    // Chaînes éditoriales (Channel/ChannelProfile — distinct des chaînes TV ci-dessus)
    Route::get('/channels', [AdminEditorialChannelController::class, 'index'])->name('channels.index');
    Route::get('/channels/{id}', [AdminEditorialChannelController::class, 'show'])->name('channels.show');
    Route::patch('/channels/{id}', [AdminEditorialChannelController::class, 'update'])->name('channels.update');
    Route::post('/channels/{id}/suspend', [AdminEditorialChannelController::class, 'suspend'])->name('channels.suspend');
    Route::post('/channels/{id}/ban', [AdminEditorialChannelController::class, 'ban'])->name('channels.ban');
    Route::post('/channels/{id}/activate', [AdminEditorialChannelController::class, 'activate'])->name('channels.activate');
    Route::post('/channels/{id}/anonymize', [AdminEditorialChannelController::class, 'anonymize'])->name('channels.anonymize');
    Route::post('/channels/{id}/badges', [AdminEditorialChannelController::class, 'syncBadges'])->name('channels.badges.sync');
    Route::delete('/channels/{id}', [AdminEditorialChannelController::class, 'destroy'])->name('channels.destroy');

    // Categories CRUD
    Route::get('/tv/categories', [AdminCategoryController::class, 'index'])->name('tv.categories.index');
    Route::post('/tv/categories', [AdminCategoryController::class, 'store'])->name('tv.categories.store');
    Route::patch('/tv/categories/{id}', [AdminCategoryController::class, 'update'])->name('tv.categories.update');
    Route::delete('/tv/categories/{id}', [AdminCategoryController::class, 'destroy'])->name('tv.categories.destroy');

    // Source provenances CRUD
    Route::get('/source-provenances', [AdminSourceProvenanceController::class, 'index'])->name('source-provenances.index');
    Route::post('/source-provenances', [AdminSourceProvenanceController::class, 'store'])->name('source-provenances.store');
    Route::patch('/source-provenances/{id}', [AdminSourceProvenanceController::class, 'update'])->name('source-provenances.update');
    Route::delete('/source-provenances/{id}', [AdminSourceProvenanceController::class, 'destroy'])->name('source-provenances.destroy');

    // Programs
    Route::get('/tv/programs', [AdminProgramController::class, 'index'])->name('tv.programs.index');
    Route::get('/tv/programs/{id}', [AdminProgramController::class, 'show'])->name('tv.programs.show');
    Route::patch('/tv/programs/{id}', [AdminProgramController::class, 'update'])->name('tv.programs.update');
    Route::delete('/tv/programs/{id}', [AdminProgramController::class, 'destroy'])->name('tv.programs.destroy');

    // Review Questions
    Route::get('/tv/review-questions', [AdminReviewQuestionController::class, 'index'])->name('tv.review-questions.index');
    Route::post('/tv/review-questions', [AdminReviewQuestionController::class, 'store'])->name('tv.review-questions.store');
    Route::patch('/tv/review-questions/{id}', [AdminReviewQuestionController::class, 'update'])->name('tv.review-questions.update');
    Route::delete('/tv/review-questions/{id}', [AdminReviewQuestionController::class, 'destroy'])->name('tv.review-questions.destroy');

    // Contact messages
    Route::get('/contact-messages', [AdminContactMessageController::class, 'index'])->name('contact-messages.index');
    Route::get('/contact-messages/{id}', [AdminContactMessageController::class, 'show'])->name('contact-messages.show');
    Route::patch('/contact-messages/{id}', [AdminContactMessageController::class, 'update'])->name('contact-messages.update');
    Route::delete('/contact-messages/{id}', [AdminContactMessageController::class, 'destroy'])->name('contact-messages.destroy');

    // Broadcasts
    Route::get('/tv/broadcasts', [AdminBroadcastController::class, 'index'])->name('tv.broadcasts.index');
    Route::get('/tv/broadcasts/{id}', [AdminBroadcastController::class, 'show'])->name('tv.broadcasts.show');
    Route::patch('/tv/broadcasts/{id}', [AdminBroadcastController::class, 'update'])->name('tv.broadcasts.update');
    Route::patch('/tv/broadcasts/{id}/audience', [AdminBroadcastController::class, 'updateAudience'])->name('tv.broadcasts.audience');
    Route::delete('/tv/broadcasts/{id}', [AdminBroadcastController::class, 'destroy'])->name('tv.broadcasts.destroy');
});
