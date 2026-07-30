<?php

namespace App\Http\Controllers\Programs;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Programs\Concerns\AuthorizesProgramAccess;
use App\Models\MealPlan;
use App\Models\UserProgram;
use App\Services\Admin\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MealPlanController extends Controller
{
    use AuthorizesProgramAccess;

    public function __construct(private ActivityLogger $activityLogger) {}

    public function index(Request $request, UserProgram $userProgram): JsonResponse
    {
        abort_unless($this->canView($request->user(), $userProgram), 403);

        $date = $request->query('date', now()->toDateString());

        return response()->json($userProgram->mealPlans()->with('items.kbFood')->whereDate('plan_date', $date)->get());
    }

    public function show(Request $request, MealPlan $mealPlan): JsonResponse
    {
        abort_unless($this->canView($request->user(), $mealPlan->userProgram), 403);

        return response()->json($mealPlan->load('items.kbFood'));
    }

    public function update(Request $request, MealPlan $mealPlan): RedirectResponse
    {
        abort_unless($this->canManage($request->user(), $mealPlan->userProgram), 403);

        $validated = $request->validate([
            'is_completed' => ['sometimes', 'boolean'],
            'total_calories' => ['sometimes', 'nullable', 'numeric'],
        ]);

        if (isset($validated['total_calories'])) {
            $validated['source'] = 'manual';
            $this->activityLogger->log('meal_plan.overridden', $mealPlan, ['total_calories' => $validated['total_calories']]);
        }

        $mealPlan->update($validated);

        return back();
    }
}
