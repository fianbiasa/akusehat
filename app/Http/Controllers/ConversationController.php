<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $conversations = Conversation::where('user_id', $user->id)
            ->orWhere('coach_id', $user->id)
            ->with(['user:id,name', 'coach:id,name'])
            ->orderByDesc('last_message_at')
            ->get();

        return Inertia::render('conversations/index', [
            'conversations' => $conversations,
            'hasActiveCoach' => (bool) $user->activeCoachAssignment,
        ]);
    }

    public function show(Request $request, Conversation $conversation): Response
    {
        $this->authorizeParticipant($request->user(), $conversation);

        return Inertia::render('conversations/show', [
            'conversation' => $conversation->load(['user:id,name', 'coach:id,name']),
        ]);
    }

    /**
     * Starts (or returns the existing) AI assistant conversation for the
     * caller - per 05-API-Specification.md §9 this endpoint is scoped to
     * type=ai_assistant only; coach_member conversations are found-or-
     * created lazily from the Coach Member Detail / Dashboard entry
     * points instead (there's exactly one per coach-member pair).
     */
    public function store(Request $request): RedirectResponse
    {
        $conversation = $request->user()->conversations()->firstOrCreate(['type' => 'ai_assistant']);

        return to_route('conversations.show', $conversation);
    }

    public static function findOrCreateCoachMemberConversation(User $coach, User $member): Conversation
    {
        return Conversation::firstOrCreate(
            ['type' => 'coach_member', 'user_id' => $member->id, 'coach_id' => $coach->id],
        );
    }

    private function authorizeParticipant(User $user, Conversation $conversation): void
    {
        abort_unless($conversation->user_id === $user->id || $conversation->coach_id === $user->id, 403);
    }
}
