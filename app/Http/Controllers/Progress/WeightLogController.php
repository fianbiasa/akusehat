<?php

namespace App\Http\Controllers\Progress;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Progress\Concerns\ResolvesTargetUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class WeightLogController extends Controller
{
    use ResolvesTargetUser;

    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveTargetUser($request);

        $query = $user->weightLogs()->orderBy('logged_at');

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
            'weight_kg' => ['required', 'numeric', 'min:20', 'max:300'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $loggedAt = $validated['logged_at'] ?? Carbon::today()->toDateString();

        $request->user()->weightLogs()->updateOrCreate(
            ['logged_at' => $loggedAt],
            ['weight_kg' => $validated['weight_kg'], 'note' => $validated['note'] ?? null, 'created_at' => now()],
        );

        return back();
    }
}
