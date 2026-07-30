<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Progress\Concerns\ResolvesTargetUser;
use App\Models\Achievement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 05-API-Specification.md §13 — catalog is M/C/A (any authenticated
 * user); earned achievements reuse the Progress module's M(own)/
 * C(assigned)/A access pattern via ResolvesTargetUser.
 */
class AchievementController extends Controller
{
    use ResolvesTargetUser;

    public function index(): JsonResponse
    {
        return response()->json(Achievement::orderBy('name')->get(['id', 'name', 'description', 'icon']));
    }

    public function mine(Request $request): JsonResponse
    {
        $user = $this->resolveTargetUser($request);

        $earned = $user->achievements()->orderByDesc('user_achievements.earned_at')->get(['achievements.id', 'achievements.name', 'achievements.description', 'achievements.icon']);

        return response()->json($earned);
    }
}
