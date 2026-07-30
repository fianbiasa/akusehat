<?php

use App\Http\Controllers\ConversationController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'onboarding.completed', 'permission:chat.send'])->group(function () {
    Route::get('conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::post('conversations', [ConversationController::class, 'store'])->name('conversations.store');
    Route::get('conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
    Route::patch('conversations/{conversation}/read', [MessageController::class, 'markRead'])->name('conversations.read');

    Route::get('conversations/{conversation}/messages', [MessageController::class, 'index'])->name('conversations.messages.index');

    // Every message to an ai_assistant conversation triggers a real,
    // synchronous AI call (see MessageController's own docblock) -
    // throttled to bound cost-abuse via rapid chat spam.
    Route::post('conversations/{conversation}/messages', [MessageController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('conversations.messages.store');
});

Route::middleware(['auth'])->group(function () {
    Route::get('reviews', [ReviewController::class, 'index'])->name('reviews.index');
    Route::post('user-programs/{userProgram}/review', [ReviewController::class, 'store'])->name('user-programs.review');
});
