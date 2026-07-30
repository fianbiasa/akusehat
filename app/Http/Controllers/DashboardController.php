<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Today view" per wireframe/dashboard.md.
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

        $latestWeightLog = $user->weightLogs()->latest('logged_at')->first(['weight_kg', 'logged_at']);
        $latestHealthScore = $user->healthScores()->latest('scored_at')->first(['score', 'scored_at']);
        $yesterdayScore = $latestHealthScore
            ? $user->healthScores()->where('scored_at', '<', $latestHealthScore->scored_at)->latest('scored_at')->value('score')
            : null;

        return Inertia::render('dashboard', [
            'selectedProgramId' => $primaryProgram?->id,
            'activePrograms' => $activePrograms->map(fn ($p) => [
                'id' => $p->id,
                'program_name' => $p->program->name,
                'start_date' => $p->start_date->toDateString(),
                'end_date' => $p->end_date?->toDateString(),
                'day_number' => Carbon::parse($p->start_date)->diffInDays(Carbon::today()) + 1,
                'duration_days' => $p->program->default_duration_days,
            ]),
            'checklist' => $checklist,
            'latestMeasurement' => $latestWeightLog ? ['weight_kg' => $latestWeightLog->weight_kg, 'measured_at' => $latestWeightLog->logged_at->toDateString()] : null,
            'healthScore' => $latestHealthScore ? [
                'score' => (float) $latestHealthScore->score,
                'scored_at' => $latestHealthScore->scored_at->toDateString(),
                'delta' => $yesterdayScore !== null ? round((float) $latestHealthScore->score - (float) $yesterdayScore, 1) : null,
            ] : null,
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
