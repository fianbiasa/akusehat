<?php

namespace App\Http\Controllers\Progress\Concerns;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * M(own)/C(assigned)/A per 05-API-Specification.md §7. "Assigned" is
 * checked via user_programs.coach_id, same as the Programs module -
 * coach_members (Phase 8) doesn't exist yet.
 */
trait ResolvesTargetUser
{
    private function resolveTargetUser(Request $request): User
    {
        $requestedId = $request->query('user_id');
        $current = $request->user();

        if (! $requestedId || (int) $requestedId === $current->id) {
            return $current;
        }

        $target = User::findOrFail($requestedId);
        $isAssignedCoach = $target->programs()->where('coach_id', $current->id)->exists();

        abort_unless($current->hasRole('admin') || $isAssignedCoach, 403);

        return $target;
    }
}
