<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "🔴 Perlu Perhatian" per wireframe/coach.md queries ai_memories where
 * memory_type=concern and ai_recommendations where status=pending,
 * scoped to coach_members where coach_id=current coach and status=active.
 */
class CoachDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $coach = $request->user();

        $members = User::whereIn('id', $coach->coachedMembers()->where('status', 'active')->pluck('member_id'))
            ->with(['activePrograms.program'])
            ->get();

        $concerns = collect();

        $rows = $members->map(function (User $member) use (&$concerns) {
            $program = $member->activePrograms->first();
            $latestScore = $member->healthScores()->latest('scored_at')->first();
            $previousScore = $latestScore
                ? $member->healthScores()->where('scored_at', '<', $latestScore->scored_at)->latest('scored_at')->value('score')
                : null;

            $concernMemory = $member->aiMemories()->where('memory_type', 'concern')->latest('created_at')->first();
            $pendingCount = $member->aiRecommendations()->where('status', 'pending')->count();

            if ($concernMemory || $pendingCount > 0) {
                $concerns->push([
                    'member_id' => $member->id,
                    'member_name' => $member->name,
                    'reason' => $concernMemory?->summary ?? "{$pendingCount} rekomendasi menunggu persetujuan.",
                ]);
            }

            return [
                'id' => $member->id,
                'name' => $member->name,
                'program_name' => $program?->program->name,
                'health_score' => $latestScore ? (float) $latestScore->score : null,
                'health_score_delta' => $latestScore && $previousScore !== null
                    ? round((float) $latestScore->score - (float) $previousScore, 1)
                    : null,
                'needs_attention' => (bool) ($concernMemory || $pendingCount > 0),
            ];
        });

        return Inertia::render('coach/dashboard', [
            'maxMembers' => $coach->coachProfile?->max_members ?? 50,
            'memberCount' => $members->count(),
            'concerns' => $concerns->values(),
            'members' => $rows->values(),
        ]);
    }
}
