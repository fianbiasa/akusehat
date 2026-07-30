<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AiProviderController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\AppSettingController;
use App\Http\Controllers\Admin\CoachAssignmentController;
use App\Http\Controllers\Admin\OnboardingQuestionController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\RuleEngineController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::middleware('permission:users.manage')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    Route::middleware('permission:roles.manage')->group(function () {
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::patch('roles/{role}/permissions', [RoleController::class, 'updatePermissions'])->name('roles.permissions.update');
    });

    Route::middleware('permission:rule_engine.manage')->prefix('rule-engine')->name('rule-engine.')->group(function () {
        Route::get('rules', [RuleEngineController::class, 'index'])->name('rules.index');
        Route::post('rules', [RuleEngineController::class, 'store'])->name('rules.store');
        Route::patch('rules/{rule}', [RuleEngineController::class, 'update'])->name('rules.update');
        Route::delete('rules/{rule}', [RuleEngineController::class, 'destroy'])->name('rules.destroy');
        Route::post('rules/{rule}/test', [RuleEngineController::class, 'test'])->name('rules.test');
    });

    Route::middleware('permission:ai.manage')->prefix('ai')->name('ai.')->group(function () {
        Route::get('providers', [AiProviderController::class, 'index'])->name('providers.index');
        Route::post('providers', [AiProviderController::class, 'store'])->name('providers.store');
        Route::patch('providers/{provider}', [AiProviderController::class, 'update'])->name('providers.update');
        Route::post('providers/{provider}/models', [AiProviderController::class, 'storeModel'])->name('providers.models.store');
        Route::patch('models/{model}', [AiProviderController::class, 'updateModel'])->name('models.update');
    });

    Route::middleware('permission:coach_members.manage')->group(function () {
        Route::get('coach-members', [CoachAssignmentController::class, 'index'])->name('coach-members.index');
        Route::post('coach-members', [CoachAssignmentController::class, 'store'])->name('coach-members.store');
        Route::delete('coach-members/{member}', [CoachAssignmentController::class, 'destroy'])->name('coach-members.destroy');
    });

    Route::middleware('permission:analytics.view')->group(function () {
        Route::get('analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
        Route::get('activity-log', [ActivityLogController::class, 'index'])->name('activity-log.index');
    });

    Route::middleware('permission:subscriptions.manage')->group(function () {
        Route::get('plans', [PlanController::class, 'index'])->name('plans.index');
        Route::post('plans', [PlanController::class, 'store'])->name('plans.store');
        Route::patch('plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
        Route::get('subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
    });

    Route::middleware('permission:app_settings.manage')->prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [AppSettingController::class, 'edit'])->name('edit');
        Route::patch('ai-default', [AppSettingController::class, 'updateAiDefault'])->name('ai-default.update');
        Route::patch('maintenance-mode', [AppSettingController::class, 'updateMaintenanceMode'])->name('maintenance-mode.update');
    });

    Route::middleware('permission:onboarding_questions.manage')->prefix('onboarding-questions')->name('onboarding-questions.')->group(function () {
        Route::get('/', [OnboardingQuestionController::class, 'index'])->name('index');
        Route::post('/', [OnboardingQuestionController::class, 'store'])->name('store');
        Route::patch('{question}', [OnboardingQuestionController::class, 'update'])->name('update');
        Route::post('{question}/toggle-active', [OnboardingQuestionController::class, 'toggleActive'])->name('toggle-active');
        Route::post('{question}/move-up', [OnboardingQuestionController::class, 'moveUp'])->name('move-up');
        Route::post('{question}/move-down', [OnboardingQuestionController::class, 'moveDown'])->name('move-down');
    });
});
