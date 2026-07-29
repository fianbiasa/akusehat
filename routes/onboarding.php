<?php

use App\Http\Controllers\OnboardingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('onboarding')->name('onboarding.')->group(function () {
    Route::get('/', [OnboardingController::class, 'wizard'])->name('index');
    Route::get('questions', [OnboardingController::class, 'questions'])->name('questions');
    Route::post('sessions', [OnboardingController::class, 'start'])->name('sessions.start');
    Route::get('sessions/current', [OnboardingController::class, 'current'])->name('sessions.current');
    Route::post('sessions/{session}/answers', [OnboardingController::class, 'answer'])->name('sessions.answers');
    Route::post('sessions/{session}/complete', [OnboardingController::class, 'complete'])->name('sessions.complete');
});
