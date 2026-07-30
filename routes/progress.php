<?php

use App\Http\Controllers\Progress\BodyFatLogController;
use App\Http\Controllers\Progress\HealthScoreController;
use App\Http\Controllers\Progress\ProgressPageController;
use App\Http\Controllers\Progress\ProgressPhotoController;
use App\Http\Controllers\Progress\SleepLogController;
use App\Http\Controllers\Progress\WaistLogController;
use App\Http\Controllers\Progress\WaterIntakeLogController;
use App\Http\Controllers\Progress\WeightLogController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'onboarding.completed'])->prefix('progress')->name('progress.')->group(function () {
    Route::get('/', [ProgressPageController::class, 'index'])->name('index');

    Route::get('weight', [WeightLogController::class, 'index'])->name('weight.index');
    Route::post('weight', [WeightLogController::class, 'store'])->name('weight.store');

    Route::get('waist', [WaistLogController::class, 'index'])->name('waist.index');
    Route::post('waist', [WaistLogController::class, 'store'])->name('waist.store');

    Route::get('body-fat', [BodyFatLogController::class, 'index'])->name('body-fat.index');
    Route::post('body-fat', [BodyFatLogController::class, 'store'])->name('body-fat.store');

    Route::get('sleep', [SleepLogController::class, 'index'])->name('sleep.index');
    Route::post('sleep', [SleepLogController::class, 'store'])->name('sleep.store');

    Route::get('water', [WaterIntakeLogController::class, 'index'])->name('water.index');
    Route::post('water', [WaterIntakeLogController::class, 'store'])->name('water.store');

    Route::get('photos', [ProgressPhotoController::class, 'index'])->name('photos.index');
    Route::post('photos', [ProgressPhotoController::class, 'store'])->name('photos.store');
    Route::patch('photos/{photo}', [ProgressPhotoController::class, 'update'])->name('photos.update');
    Route::delete('photos/{photo}', [ProgressPhotoController::class, 'destroy'])->name('photos.destroy');

    Route::get('health-score', [HealthScoreController::class, 'index'])->name('health-score.index');
    Route::get('health-score/today', [HealthScoreController::class, 'today'])->name('health-score.today');
});

Route::middleware(['auth', 'signed'])->get('progress/photos/{photo}/file', [ProgressPhotoController::class, 'show'])->name('progress.photos.show');
