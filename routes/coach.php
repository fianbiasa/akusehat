<?php

use App\Http\Controllers\Coach\CoachDashboardController;
use App\Http\Controllers\Coach\CoachMemberController;
use App\Http\Controllers\Coach\CoachNoteController;
use App\Http\Controllers\Coach\CoachProfileController;
use App\Http\Controllers\Coach\CoachRecommendationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('coach')->name('coach.')->group(function () {
    Route::middleware('permission:member.view')->group(function () {
        Route::get('dashboard', [CoachDashboardController::class, 'index'])->name('dashboard');
        Route::get('members', [CoachMemberController::class, 'index'])->name('members.index');
        Route::get('members/{member}', [CoachMemberController::class, 'show'])->name('members.show');
        Route::get('members/{member}/conversation', [CoachMemberController::class, 'conversation'])->name('members.conversation');
    });

    Route::middleware('permission:note.manage')->group(function () {
        Route::get('members/{member}/notes', [CoachNoteController::class, 'index'])->name('members.notes.index');
        Route::post('members/{member}/notes', [CoachNoteController::class, 'store'])->name('members.notes.store');
        Route::patch('notes/{note}', [CoachNoteController::class, 'update'])->name('notes.update');
    });

    Route::middleware('permission:program.review')->group(function () {
        Route::get('members/{member}/recommendations', [CoachRecommendationController::class, 'index'])->name('members.recommendations.index');
        Route::post('recommendations/{recommendation}/approve', [CoachRecommendationController::class, 'approve'])->name('recommendations.approve');
        Route::post('recommendations/{recommendation}/reject', [CoachRecommendationController::class, 'reject'])->name('recommendations.reject');
    });

    Route::get('profile', [CoachProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [CoachProfileController::class, 'update'])->name('profile.update');
});
