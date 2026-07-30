<?php

namespace App\Http\Controllers\Progress;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Progress\Concerns\ResolvesTargetUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class WaistLogController extends Controller
{
    use ResolvesTargetUser;

    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveTargetUser($request);

        $query = $user->waistLogs()->orderBy('logged_at');

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
            'waist_cm' => ['required', 'numeric', 'min:30', 'max:200'],
        ]);

        $loggedAt = $validated['logged_at'] ?? Carbon::today()->toDateString();

        $request->user()->waistLogs()->updateOrCreate(
            ['logged_at' => $loggedAt],
            ['waist_cm' => $validated['waist_cm'], 'created_at' => now()],
        );

        return back();
    }
}
