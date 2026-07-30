<?php

use App\Http\Controllers\AchievementController;
use App\Http\Controllers\Profile\BodyMeasurementController;
use App\Http\Controllers\Profile\HealthProfileController;
use App\Http\Controllers\Profile\LifestyleProfileController;
use App\Http\Controllers\Profile\UserAllergyController;
use App\Http\Controllers\Profile\UserDiseaseController;
use App\Http\Controllers\Profile\UserMedicationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'onboarding.completed'])->prefix('profile')->name('profile.')->group(function () {
    Route::get('health', [HealthProfileController::class, 'edit'])->name('edit');
    Route::patch('health', [HealthProfileController::class, 'update'])->name('health.update');
    Route::patch('lifestyle', [LifestyleProfileController::class, 'update'])->name('lifestyle.update');

    Route::post('diseases', [UserDiseaseController::class, 'store'])->name('diseases.store');
    Route::delete('diseases/{disease}', [UserDiseaseController::class, 'destroy'])->name('diseases.destroy');

    Route::post('allergies', [UserAllergyController::class, 'store'])->name('allergies.store');
    Route::delete('allergies/{allergy}', [UserAllergyController::class, 'destroy'])->name('allergies.destroy');

    Route::post('medications', [UserMedicationController::class, 'store'])->name('medications.store');
    Route::patch('medications/{medication}', [UserMedicationController::class, 'update'])->name('medications.update');
    Route::delete('medications/{medication}', [UserMedicationController::class, 'destroy'])->name('medications.destroy');

    Route::post('measurements', [BodyMeasurementController::class, 'store'])->name('measurements.store');

    Route::get('achievements', [AchievementController::class, 'mine'])->name('achievements.mine');
});
