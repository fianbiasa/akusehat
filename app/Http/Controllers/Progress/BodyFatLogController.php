<?php

namespace App\Http\Controllers\Progress;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Progress\Concerns\ResolvesTargetUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BodyFatLogController extends Controller
{
    use ResolvesTargetUser;

    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveTargetUser($request);

        $query = $user->bodyFatLogs()->orderBy('logged_at');

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
            'body_fat_pct' => ['required', 'numeric', 'min:2', 'max:70'],
        ]);

        $loggedAt = $validated['logged_at'] ?? Carbon::today()->toDateString();

        $request->user()->bodyFatLogs()->updateOrCreate(
            ['logged_at' => $loggedAt],
            ['body_fat_pct' => $validated['body_fat_pct'], 'created_at' => now()],
        );

        return back();
    }
}
