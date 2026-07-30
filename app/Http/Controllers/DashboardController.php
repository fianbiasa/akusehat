<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Today view" per wireframe/dashboard.md. Health Score card is Phase 7
 * (health_scores doesn't exist yet) - omitted here rather than faked.
 */
class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $today = Carbon::today()->toDateString();

        $activePrograms = $user->activePrograms()->with('program')->get();
        $selectedId = $request->query('program');
        $primaryProgram = $selectedId ? $activePrograms->firstWhere('id', (int) $selectedId) : $activePrograms->first();
        $primaryProgram ??= $activePrograms->first();

        $checklist = [];
        $weeklyReview = null;

        if ($primaryProgram) {
            $checklist = $primaryProgram->checklistItems()->whereDate('item_date', $today)->get();

            $weeklyReview = $primaryProgram->weeklyPlans()
                ->whereNotNull('ai_review')
                ->whereNull('viewed_at')
                ->orderByDesc('week_number')
                ->first();
        }

        $latestMeasurement = $user->bodyMeasurements()->whereNotNull('weight_kg')->latest('measured_at')->first(['weight_kg', 'measured_at']);

        return Inertia::render('dashboard', [
            'selectedProgramId' => $primaryProgram?->id,
            'activePrograms' => $activePrograms->map(fn ($p) => [
                'id' => $p->id,
                'program_name' => $p->program->name,
                'start_date' => $p->start_date,
                'end_date' => $p->end_date,
                'day_number' => Carbon::parse($p->start_date)->diffInDays(Carbon::today()) + 1,
                'duration_days' => $p->program->default_duration_days,
            ]),
            'checklist' => $checklist,
            'latestMeasurement' => $latestMeasurement,
            'weeklyReview' => $weeklyReview ? [
                'id' => $weeklyReview->id,
                'user_program_id' => $primaryProgram->id,
                'week_number' => $weeklyReview->week_number,
                'ai_summary' => $weeklyReview->ai_summary,
                'ai_review' => $weeklyReview->ai_review,
            ] : null,
        ]);
    }
}
