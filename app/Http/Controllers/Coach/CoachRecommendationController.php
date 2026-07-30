<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\AiRecommendation;
use App\Models\User;
use App\Services\Coach\CoachRecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CoachRecommendationController extends Controller
{
    public function __construct(private CoachRecommendationService $service) {}

    public function index(Request $request, User $member): JsonResponse
    {
        abort_unless($request->user()->coachedMembers()->where('member_id', $member->id)->where('status', 'active')->exists(), 403);

        return response()->json($member->aiRecommendations()->where('status', 'pending')->latest()->get());
    }

    public function approve(Request $request, AiRecommendation $recommendation): RedirectResponse
    {
        $this->authorizeAssignedTo($request->user(), $recommendation);

        $this->service->approve($recommendation, $request->user());

        return back();
    }

    public function reject(Request $request, AiRecommendation $recommendation): RedirectResponse
    {
        $this->authorizeAssignedTo($request->user(), $recommendation);

        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $this->service->reject($recommendation, $request->user(), $validated['reason'] ?? null);

        return back();
    }

    private function authorizeAssignedTo(User $coach, AiRecommendation $recommendation): void
    {
        abort_unless(
            $coach->coachedMembers()->where('member_id', $recommendation->user_id)->where('status', 'active')->exists(),
            403,
        );
    }
}
