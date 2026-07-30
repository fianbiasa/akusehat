<?php

namespace App\Http\Controllers\Progress;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Progress\Concerns\ResolvesTargetUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class HealthScoreController extends Controller
{
    use ResolvesTargetUser;

    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveTargetUser($request);

        $query = $user->healthScores()->orderBy('scored_at');

        if ($from = $request->query('from')) {
            $query->where('scored_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->where('scored_at', '<=', $to);
        }

        return response()->json($query->get());
    }

    public function today(Request $request): JsonResponse
    {
        $user = $this->resolveTargetUser($request);

        return response()->json(
            $user->healthScores()->where('scored_at', Carbon::today()->toDateString())->first()
        );
    }
}
