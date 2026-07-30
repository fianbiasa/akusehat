<?php

namespace App\Http\Controllers\Programs\Concerns;

use App\Models\User;
use App\Models\UserProgram;

/**
 * M(own)/C(assigned via user_programs.coach_id)/A per 05-API-
 * Specification.md §6 roles column. coach_members (Phase 8) doesn't
 * exist yet, but user_programs.coach_id already does, so "assigned
 * coach" is checkable now even though the assignment-management UI
 * isn't built until Phase 8.
 */
trait AuthorizesProgramAccess
{
    private function canView(User $user, UserProgram $userProgram): bool
    {
        return $userProgram->user_id === $user->id
            || $userProgram->coach_id === $user->id
            || $user->hasRole('admin');
    }

    private function canManage(User $user, UserProgram $userProgram): bool
    {
        return $userProgram->user_id === $user->id || $userProgram->coach_id === $user->id;
    }
}
