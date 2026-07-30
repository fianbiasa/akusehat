<?php

namespace App\Http\Controllers\Progress;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Progress\Concerns\ResolvesTargetUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class SleepLogController extends Controller
{
    use ResolvesTargetUser;

    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveTargetUser($request);

        $query = $user->sleepLogs()->orderBy('logged_at');

        if ($from = $request->query('from')) {
            $query->where('logged_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->where('logged_at', '<=', $to);
        }

        return response()->json($query->get());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'logged_at' => ['nullable', 'date'],
            'sleep_hours' => ['required', 'numeric', 'min:0', 'max:24'],
            'quality' => ['nullable', Rule::in(['poor', 'fair', 'good', 'excellent'])],
        ]);

        $loggedAt = $validated['logged_at'] ?? Carbon::today()->toDateString();

        $request->user()->sleepLogs()->updateOrCreate(
            ['logged_at' => $loggedAt],
            ['sleep_hours' => $validated['sleep_hours'], 'quality' => $validated['quality'] ?? null, 'created_at' => now()],
        );

        return back();
    }
}
