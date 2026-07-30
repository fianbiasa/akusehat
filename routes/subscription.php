<?php

use App\Http\Controllers\PlanController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

// Public per 05-API-Specification.md §14 - no auth required to browse the catalog.
Route::get('plans', [PlanController::class, 'index'])->name('plans.index');

Route::middleware(['auth', 'onboarding.completed'])->prefix('subscription')->name('subscription.')->group(function () {
    Route::get('/', [SubscriptionController::class, 'show'])->name('show');
    Route::post('subscribe', [SubscriptionController::class, 'subscribe'])->name('subscribe');
    Route::post('cancel', [SubscriptionController::class, 'cancel'])->name('cancel');
    Route::get('payments', [SubscriptionController::class, 'payments'])->name('payments');
});
