<?php

use App\Http\Controllers\AiSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'onboarding.completed'])->prefix('ai')->name('ai.')->group(function () {
    Route::get('settings', [AiSettingsController::class, 'edit'])->name('settings.edit');
    Route::post('settings', [AiSettingsController::class, 'store'])->name('settings.store');
    Route::patch('settings/{setting}', [AiSettingsController::class, 'update'])->name('settings.update');
    Route::delete('settings/{setting}', [AiSettingsController::class, 'destroy'])->name('settings.destroy');
    Route::post('settings/{setting}/set-default', [AiSettingsController::class, 'setDefault'])->name('settings.set-default');
    // Fires a real request to the configured provider each call.
    Route::post('settings/{setting}/test', [AiSettingsController::class, 'test'])
        ->middleware('throttle:10,1')
        ->name('settings.test');
});
