<?php

use App\Http\Controllers\Programs\ChecklistItemController;
use App\Http\Controllers\Programs\DailyTaskController;
use App\Http\Controllers\Programs\MealPlanController;
use App\Http\Controllers\Programs\ProgramCatalogController;
use App\Http\Controllers\Programs\ProgramGoalController;
use App\Http\Controllers\Programs\ReminderController;
use App\Http\Controllers\Programs\UserProgramController;
use App\Http\Controllers\Programs\WeeklyPlanController;
use App\Http\Controllers\Programs\WorkoutPlanController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'onboarding.completed'])->group(function () {
    Route::get('programs/catalog', [ProgramCatalogController::class, 'index'])->name('programs.catalog');

    Route::get('user-programs', [UserProgramController::class, 'index'])->name('user-programs.index');
    Route::post('user-programs', [UserProgramController::class, 'store'])->middleware('plan.program_limit')->name('user-programs.store');
    Route::get('user-programs/{userProgram}', [UserProgramController::class, 'show'])->name('user-programs.show');
    Route::patch('user-programs/{userProgram}', [UserProgramController::class, 'update'])->name('user-programs.update');
    Route::post('user-programs/{userProgram}/regenerate', [UserProgramController::class, 'regenerate'])->name('user-programs.regenerate');
    Route::get('user-programs/{userProgram}/generate/status', [UserProgramController::class, 'generateStatus'])->name('user-programs.generate-status');

    Route::get('user-programs/{userProgram}/goals', [ProgramGoalController::class, 'index'])->name('user-programs.goals.index');
    Route::post('user-programs/{userProgram}/goals', [ProgramGoalController::class, 'store'])->name('user-programs.goals.store');

    Route::get('user-programs/{userProgram}/weekly-plans', [WeeklyPlanController::class, 'index'])->name('user-programs.weekly-plans.index');
    Route::get('user-programs/{userProgram}/weekly-plans/{week}', [WeeklyPlanController::class, 'show'])->name('user-programs.weekly-plans.show');

    Route::get('user-programs/{userProgram}/daily-tasks', [DailyTaskController::class, 'index'])->name('user-programs.daily-tasks.index');
    Route::patch('daily-tasks/{dailyTask}', [DailyTaskController::class, 'update'])->name('daily-tasks.update');

    Route::get('user-programs/{userProgram}/meal-plans', [MealPlanController::class, 'index'])->name('user-programs.meal-plans.index');
    Route::get('meal-plans/{mealPlan}', [MealPlanController::class, 'show'])->name('meal-plans.show');
    Route::patch('meal-plans/{mealPlan}', [MealPlanController::class, 'update'])->name('meal-plans.update');

    Route::get('user-programs/{userProgram}/workout-plans', [WorkoutPlanController::class, 'index'])->name('user-programs.workout-plans.index');
    Route::get('workout-plans/{workoutPlan}', [WorkoutPlanController::class, 'show'])->name('workout-plans.show');
    Route::patch('workout-plans/{workoutPlan}', [WorkoutPlanController::class, 'update'])->name('workout-plans.update');

    Route::get('user-programs/{userProgram}/checklist', [ChecklistItemController::class, 'index'])->name('user-programs.checklist.index');
    Route::patch('checklist-items/{checklistItem}', [ChecklistItemController::class, 'update'])->name('checklist-items.update');

    Route::get('reminders', [ReminderController::class, 'index'])->name('reminders.index');
    Route::post('reminders', [ReminderController::class, 'store'])->name('reminders.store');
    Route::patch('reminders/{reminder}', [ReminderController::class, 'update'])->name('reminders.update');
    Route::delete('reminders/{reminder}', [ReminderController::class, 'destroy'])->name('reminders.destroy');
});
