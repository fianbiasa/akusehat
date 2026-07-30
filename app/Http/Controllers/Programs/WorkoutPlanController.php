<?php

namespace App\Http\Controllers\Programs;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Programs\Concerns\AuthorizesProgramAccess;
use App\Models\UserProgram;
use App\Models\WorkoutPlan;
use App\Services\Admin\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkoutPlanController extends Controller
{
    use AuthorizesProgramAccess;

    public function __construct(private ActivityLogger $activityLogger) {}

    public function index(Request $request, UserProgram $userProgram): JsonResponse
    {
        abort_unless($this->canView($request->user(), $userProgram), 403);

        $date = $request->query('date', now()->toDateString());

        return response()->json($userProgram->workoutPlans()->with('items.kbExercise')->whereDate('plan_date', $date)->get());
    }

    public function show(Request $request, WorkoutPlan $workoutPlan): JsonResponse
    {
        abort_unless($this->canView($request->user(), $workoutPlan->userProgram), 403);

        return response()->json($workoutPlan->load('items.kbExercise'));
    }

    public function update(Request $request, WorkoutPlan $workoutPlan): RedirectResponse
    {
        abort_unless($this->canManage($request->user(), $workoutPlan->userProgram), 403);

        $validated = $request->validate([
            'is_completed' => ['sometimes', 'boolean'],
            'duration_minutes' => ['sometimes', 'nullable', 'integer'],
        ]);

        if (isset($validated['duration_minutes'])) {
            $validated['source'] = 'manual';
            $this->activityLogger->log('workout_plan.overridden', $workoutPlan, ['duration_minutes' => $validated['duration_minutes']]);
        }

        $workoutPlan->update($validated);

        return back();
    }
}
