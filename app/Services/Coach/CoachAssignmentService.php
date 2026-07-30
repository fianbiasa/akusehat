<?php

namespace App\Services\Coach;

use App\Models\CoachMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * "create/reassign (ends old row, creates new)" per
 * docs/11-Development-Checklist.md Phase 8. coach_members has no DB-level
 * unique constraint enforcing "one active assignment per member" - the
 * literal (coach_id, member_id, status) constraint in mysql.sql (itself
 * documented there as "Unique-ish") would break re-assigning a member
 * back to a coach they'd previously left, since that would collide with
 * the earlier 'ended' row for the same pair. Enforced here instead:
 * end the member's current active assignment (whoever it's with) before
 * creating the new one, in the same transaction.
 */
class CoachAssignmentService
{
    public function assign(User $coach, User $member): CoachMember
    {
        return DB::transaction(function () use ($coach, $member) {
            // Method-call form, not the property accessor - the property
            // accessor caches the relation per-instance, so a caller that
            // holds the same $member across an assign() then unassign()
            // (or two reassignments) would silently reuse a stale
            // (possibly null) cached result instead of re-querying.
            $member->activeCoachAssignment()->first()?->update(['status' => 'ended', 'ended_at' => now()]);

            $assignment = CoachMember::create([
                'coach_id' => $coach->id,
                'member_id' => $member->id,
                'status' => 'active',
                'assigned_at' => now(),
            ]);

            // Keeps Program-module authorization (AuthorizesProgramAccess,
            // Phase 6) in sync - user_programs.coach_id answers "who
            // manages this specific program run", coach_members answers
            // "is this coach currently assigned to this member at all".
            $member->activePrograms()->update(['coach_id' => $coach->id]);

            return $assignment;
        });
    }

    public function unassign(User $member): void
    {
        DB::transaction(function () use ($member) {
            // Method-call form, not the property accessor - the property
            // accessor caches the relation per-instance, so a caller that
            // holds the same $member across an assign() then unassign()
            // (or two reassignments) would silently reuse a stale
            // (possibly null) cached result instead of re-querying.
            $member->activeCoachAssignment()->first()?->update(['status' => 'ended', 'ended_at' => now()]);
            $member->activePrograms()->update(['coach_id' => null]);
        });
    }
}
