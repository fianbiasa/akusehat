<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ConversationController;
use App\Models\User;
use App\Services\AI\AIGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class CoachMemberController extends Controller
{
    public function __construct(private AIGatewayService $aiGateway) {}

    public function index(Request $request): JsonResponse
    {
        $coach = $request->user();

        return response()->json(
            User::whereIn('id', $coach->coachedMembers()->where('status', 'active')->pluck('member_id'))->get(['id', 'name', 'email'])
        );
    }

    public function show(Request $request, User $member): Response
    {
        $this->authorizeAssigned($request->user(), $member);

        $healthProfile = $member->healthProfile;
        $activeProgram = $member->activePrograms()->with('program')->first();
        $diseases = $member->diseases()->with('disease:id,name')->get()->pluck('disease.name')->values();
        $pendingRecommendations = $member->aiRecommendations()->where('status', 'pending')->latest()->get();
        $concernMemory = $member->aiMemories()->where('memory_type', 'concern')->latest('created_at')->first();

        return Inertia::render('coach/member-detail', [
            'member' => [
                'id' => $member->id,
                'name' => $member->name,
                'age' => $healthProfile?->date_of_birth?->age,
                'bmi' => $healthProfile?->bmi,
                'diseases' => $diseases,
                'activity_level' => $member->lifestyleProfile?->activity_level,
            ],
            'activeProgram' => $activeProgram ? [
                'id' => $activeProgram->id,
                'program_name' => $activeProgram->program->name,
            ] : null,
            'advisory' => $this->buildAdvisory($member, $pendingRecommendations, $concernMemory),
            'pendingRecommendations' => $pendingRecommendations->map(fn ($r) => [
                'id' => $r->id,
                'type' => $r->type,
                'content' => $r->content,
                'rationale' => $r->rationale,
            ]),
            'notes' => $member->coachNotesAbout()->where('coach_id', $request->user()->id)->latest()->get(),
        ]);
    }

    public function conversation(Request $request, User $member): RedirectResponse
    {
        $this->authorizeAssigned($request->user(), $member);

        $conversation = ConversationController::findOrCreateCoachMemberConversation($request->user(), $member);

        return to_route('conversations.show', $conversation);
    }

    private function authorizeAssigned(User $coach, User $member): void
    {
        abort_unless($coach->coachedMembers()->where('member_id', $member->id)->where('status', 'active')->exists(), 403);
    }

    /**
     * Only calls the AI when there's actually something to advise on -
     * calling coach_review for every member on every page view would be
     * a wasted AI call for members who have no concerns or pending
     * recommendations at all.
     */
    private function buildAdvisory(User $member, Collection $pendingRecommendations, $concernMemory): ?array
    {
        if (! $concernMemory && $pendingRecommendations->isEmpty()) {
            return null;
        }

        $result = $this->aiGateway->send($member, 'analyze', 'coach_review', []);

        if ($result['ai_unavailable'] ?? false) {
            return [
                'summary' => $concernMemory?->summary ?? 'Ada rekomendasi AI yang menunggu persetujuan.',
                'recommendation_notes' => [],
                'manual_checks' => [],
            ];
        }

        return $result;
    }
}
