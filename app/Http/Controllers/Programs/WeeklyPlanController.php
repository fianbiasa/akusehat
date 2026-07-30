<?php

namespace App\Http\Controllers\Programs;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Programs\Concerns\AuthorizesProgramAccess;
use App\Models\UserProgram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WeeklyPlanController extends Controller
{
    use AuthorizesProgramAccess;

    public function index(Request $request, UserProgram $userProgram): JsonResponse
    {
        abort_unless($this->canView($request->user(), $userProgram), 403);

        return response()->json($userProgram->weeklyPlans()->orderBy('week_number')->get());
    }

    public function show(Request $request, UserProgram $userProgram, int $week): Response
    {
        abort_unless($this->canView($request->user(), $userProgram), 403);

        $weeklyPlan = $userProgram->weeklyPlans()->where('week_number', $week)->firstOrFail();

        if ($weeklyPlan->ai_review && ! $weeklyPlan->viewed_at) {
            $weeklyPlan->update(['viewed_at' => now()]);
        }

        return Inertia::render('programs/weekly-review', [
            'userProgram' => $userProgram->load('program:id,name'),
            'weeklyPlan' => $weeklyPlan,
        ]);
    }
}
