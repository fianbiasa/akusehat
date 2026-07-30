<?php

use App\Http\Controllers\AchievementController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'onboarding.completed'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('achievements', [AchievementController::class, 'index'])->name('achievements.index');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
require __DIR__.'/onboarding.php';
require __DIR__.'/profile.php';
require __DIR__.'/kb.php';
require __DIR__.'/ai.php';
require __DIR__.'/programs.php';
require __DIR__.'/progress.php';
require __DIR__.'/coach.php';
require __DIR__.'/chat.php';
