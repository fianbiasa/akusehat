<?php

namespace App\Http\Controllers;

use App\Events\OnboardingCompleted;
use App\Models\OnboardingAnswer;
use App\Models\OnboardingQuestion;
use App\Models\OnboardingSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    /**
     * Wizard shell (FR-ONB-01). Loads every active question + the resumable
     * session's existing answers once; the frontend drives step navigation
     * client-side and calls the JSON endpoints below per step.
     */
    public function wizard(Request $request): Response|RedirectResponse
    {
        if ($request->user()->onboarding_completed_at) {
            return to_route('dashboard');
        }

        $session = $this->resumableSession($request);

        return Inertia::render('onboarding/wizard', [
            'questions' => OnboardingQuestion::where('is_active', true)->orderBy('order')->get(),
            'session' => $session->load('answers'),
        ]);
    }

    public function questions(): JsonResponse
    {
        return response()->json(
            OnboardingQuestion::where('is_active', true)->orderBy('order')->get()
        );
    }

    /**
     * Start or resume the caller's onboarding session (FR-ONB-03).
     */
    public function start(Request $request): JsonResponse
    {
        return response()->json($this->resumableSession($request)->load('answers'));
    }

    public function current(Request $request): JsonResponse
    {
        $session = $request->user()->onboardingSessions()->where('status', 'in_progress')->latest()->first();

        return response()->json($session?->load('answers'));
    }

    /**
     * Persist one step's answer and advance current_step (FR-ONB-03: each
     * answer auto-saves so the wizard is resumable).
     */
    public function answer(Request $request, OnboardingSession $session): JsonResponse
    {
        abort_unless($session->user_id === $request->user()->id, 403);
        abort_if($session->status !== 'in_progress', 422, 'This onboarding session is no longer in progress.');

        $validated = $request->validate([
            'question_id' => ['required', 'integer', 'exists:onboarding_questions,id'],
            'value' => ['required'],
        ]);

        $question = OnboardingQuestion::findOrFail($validated['question_id']);

        $answer = OnboardingAnswer::updateOrCreate(
            ['onboarding_session_id' => $session->id, 'question_id' => $question->id],
            ['answer_value' => $validated['value'], 'answered_at' => now()],
        );

        $session->update(['current_step' => max($session->current_step, $question->step + 1)]);

        return response()->json($answer);
    }

    /**
     * Finalize the wizard (FR-ONB-04): all required questions must be
     * answered, then mark the session/user complete and hand off to
     * program generation via OnboardingCompleted.
     */
    public function complete(Request $request, OnboardingSession $session): JsonResponse
    {
        abort_unless($session->user_id === $request->user()->id, 403);

        $answeredQuestionIds = $session->answers()->pluck('question_id');
        $missingRequired = OnboardingQuestion::where('is_active', true)
            ->where('is_required', true)
            ->whereNotIn('id', $answeredQuestionIds)
            ->pluck('question_text');

        if ($missingRequired->isNotEmpty()) {
            return response()->json([
                'message' => 'Beberapa pertanyaan wajib belum dijawab.',
                'missing_questions' => $missingRequired,
            ], 422);
        }

        DB::transaction(function () use ($session, $request) {
            $session->update(['status' => 'completed', 'completed_at' => now()]);
            // onboarding_completed_at is system-managed, not user-fillable - see App\Models\User.
            $request->user()->forceFill(['onboarding_completed_at' => now()])->save();
        });

        event(new OnboardingCompleted($session->fresh()));

        return response()->json(['redirect' => route('dashboard')]);
    }

    private function resumableSession(Request $request): OnboardingSession
    {
        return $request->user()->onboardingSessions()->where('status', 'in_progress')->latest()->first()
            ?? $request->user()->onboardingSessions()->create(['status' => 'in_progress', 'current_step' => 1, 'started_at' => now()]);
    }
}
