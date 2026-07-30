<?php

namespace App\Http\Controllers\Progress;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Progress\Concerns\ResolvesTargetUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Unlike weight/waist/body-fat/sleep, water_intake_logs has no unique-
 * per-day constraint - each glass/bottle is its own row, summed per day
 * for display (docs/03-Database-Dictionary.md Module 07).
 */
class WaterIntakeLogController extends Controller
{
    use ResolvesTargetUser;

    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveTargetUser($request);

        $query = $user->waterIntakeLogs()->orderBy('logged_at');

        if ($from = $request->query('from')) {
            $query->where('logged_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->where('logged_at', '<=', $to);
        }

        $entries = $query->get();

        $daily = $entries->groupBy(fn ($log) => $log->logged_at->toDateString())
            ->map(fn ($day) => $day->sum('amount_ml'))
            ->map(fn ($total, $date) => ['logged_at' => $date, 'total_ml' => $total])
            ->values();

        return response()->json(['entries' => $entries, 'daily_totals' => $daily]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'logged_at' => ['nullable', 'date'],
            'amount_ml' => ['required', 'integer', 'min:1', 'max:5000'],
        ]);

        $request->user()->waterIntakeLogs()->create([
            'logged_at' => $validated['logged_at'] ?? Carbon::today()->toDateString(),
            'amount_ml' => $validated['amount_ml'],
            'created_at' => now(),
        ]);

        return back();
    }
}
