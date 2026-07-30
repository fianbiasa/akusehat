<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AiPromptTemplateController;
use App\Http\Controllers\Admin\AiProviderController;
use App\Http\Controllers\Admin\AiRequestLogController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\AppSettingController;
use App\Http\Controllers\Admin\CoachAssignmentController;
use App\Http\Controllers\Admin\KbDiseaseController;
use App\Http\Controllers\Admin\KbExerciseController;
use App\Http\Controllers\Admin\KbFaqController;
use App\Http\Controllers\Admin\KbFoodController;
use App\Http\Controllers\Admin\KbNutritionArticleController;
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

        Route::get('prompt-templates', [AiPromptTemplateController::class, 'index'])->name('prompt-templates.index');
        Route::patch('prompt-templates/{promptTemplate}', [AiPromptTemplateController::class, 'update'])->name('prompt-templates.update');

        Route::get('request-logs', [AiRequestLogController::class, 'index'])->name('request-logs.index');
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

    Route::middleware('permission:knowledge_base.manage')->prefix('kb')->name('kb.')->group(function () {
        Route::prefix('foods')->name('foods.')->group(function () {
            Route::get('/', [KbFoodController::class, 'index'])->name('index');
            Route::post('/', [KbFoodController::class, 'store'])->name('store');
            Route::patch('{food}', [KbFoodController::class, 'update'])->name('update');
            Route::delete('{food}', [KbFoodController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('exercises')->name('exercises.')->group(function () {
            Route::get('/', [KbExerciseController::class, 'index'])->name('index');
            Route::post('/', [KbExerciseController::class, 'store'])->name('store');
            Route::patch('{exercise}', [KbExerciseController::class, 'update'])->name('update');
            Route::delete('{exercise}', [KbExerciseController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('diseases')->name('diseases.')->group(function () {
            Route::get('/', [KbDiseaseController::class, 'index'])->name('index');
            Route::post('/', [KbDiseaseController::class, 'store'])->name('store');
            Route::patch('{disease}', [KbDiseaseController::class, 'update'])->name('update');
            Route::delete('{disease}', [KbDiseaseController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('articles')->name('articles.')->group(function () {
            Route::get('/', [KbNutritionArticleController::class, 'index'])->name('index');
            Route::post('/', [KbNutritionArticleController::class, 'store'])->name('store');
            Route::patch('{article}', [KbNutritionArticleController::class, 'update'])->name('update');
            Route::post('{article}/toggle-published', [KbNutritionArticleController::class, 'togglePublished'])->name('toggle-published');
        });

        Route::prefix('faqs')->name('faqs.')->group(function () {
            Route::get('/', [KbFaqController::class, 'index'])->name('index');
            Route::post('/', [KbFaqController::class, 'store'])->name('store');
            Route::patch('{faq}', [KbFaqController::class, 'update'])->name('update');
            Route::post('{faq}/toggle-published', [KbFaqController::class, 'togglePublished'])->name('toggle-published');
            Route::post('{faq}/move-up', [KbFaqController::class, 'moveUp'])->name('move-up');
            Route::post('{faq}/move-down', [KbFaqController::class, 'moveDown'])->name('move-down');
        });
    });
});
