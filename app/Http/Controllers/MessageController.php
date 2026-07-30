<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\User;
use App\Services\AI\AIGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Real-time messaging (Echo/Pusher) isn't wired up in this environment -
 * the frontend uses the documented polling fallback (re-fetches index()
 * every few seconds) per docs/11-Development-Checklist.md Phase 8.
 *
 * store() processes the ai_assistant chat() call synchronously rather
 * than via a queued job, unlike every other AI-triggering endpoint in
 * this app: chat is an active, expected-to-wait conversation UX (closer
 * to how any chat product actually behaves) where the "never block on
 * AI latency" concern from 04-Architecture.md §5 was specifically about
 * program generation's 2-30s latency blocking an initial page load, not
 * a single already-fast chat turn inside a conversation the user is
 * actively watching.
 */
class MessageController extends Controller
{
    public function __construct(private AIGatewayService $aiGateway) {}

    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeParticipant($request->user(), $conversation);

        return response()->json(
            $conversation->messages()->orderBy('id')->get()
        );
    }

    public function store(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $this->authorizeParticipant($request->user(), $conversation);

        $validated = $request->validate(['content' => ['required', 'string', 'max:2000']]);

        $senderType = $user->id === $conversation->coach_id ? 'coach' : 'user';

        $userMessage = $conversation->messages()->create([
            'sender_type' => $senderType,
            'sender_id' => $user->id,
            'content' => $validated['content'],
            'created_at' => now(),
        ]);

        $conversation->update(['last_message_at' => now()]);

        $aiMessage = null;

        if ($conversation->type === 'ai_assistant') {
            $aiMessage = $this->replyAsAssistant($conversation);
        }

        return response()->json(['message' => $userMessage, 'reply' => $aiMessage]);
    }

    public function markRead(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeParticipant($request->user(), $conversation);

        $conversation->messages()->whereNull('read_at')->where('sender_id', '!=', $request->user()->id)->update(['read_at' => now()]);

        return response()->json(['status' => 'ok']);
    }

    private function replyAsAssistant(Conversation $conversation)
    {
        $member = $conversation->user;
        $memberMessage = $conversation->messages()->latest('id')->first()->content;

        $history = $conversation->messages()
            ->orderByDesc('id')
            ->skip(1)
            ->limit(10)
            ->get()
            ->reverse()
            ->map(fn ($m) => ['sender' => $m->sender_type, 'content' => $m->content])
            ->values()
            ->all();

        $result = $this->aiGateway->send($member, 'chat', 'daily_chat', [
            'conversation_history' => $history,
            'member_message' => $memberMessage,
            'messages' => [['role' => 'user', 'content' => $memberMessage]],
        ]);

        $reply = ($result['ai_unavailable'] ?? false)
            ? 'Maaf, asisten AI sedang tidak tersedia. Coba lagi nanti atau hubungi Coach kamu.'
            : ($result['reply'] ?? 'Maaf, aku tidak bisa memproses itu sekarang.');

        return $conversation->messages()->create([
            'sender_type' => 'ai',
            'sender_id' => null,
            'content' => $reply,
            'meta' => ($result['ai_unavailable'] ?? false) ? null : ['suggested_actions' => $result['suggested_actions'] ?? []],
            'created_at' => now(),
        ]);
    }

    private function authorizeParticipant(User $user, Conversation $conversation): User
    {
        abort_unless($conversation->user_id === $user->id || $conversation->coach_id === $user->id, 403);

        return $user;
    }
}
