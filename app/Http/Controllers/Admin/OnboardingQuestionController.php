<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OnboardingQuestion;
use App\Services\Admin\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingQuestionController extends Controller
{
    public function __construct(private ActivityLogger $activityLogger) {}

    public function index(): Response
    {
        return Inertia::render('admin/onboarding-questions/index', [
            'questions' => OnboardingQuestion::orderBy('order')->get(),
            'categories' => OnboardingQuestion::query()->distinct()->orderBy('category')->pluck('category'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        $nextOrder = (int) OnboardingQuestion::max('order') + 1;

        $question = OnboardingQuestion::create([
            ...$validated,
            'step' => $nextOrder,
            'order' => $nextOrder,
        ]);

        $this->activityLogger->log('onboarding_question.created', $question, ['question_text' => $question->question_text]);

        return back();
    }

    public function update(Request $request, OnboardingQuestion $question): RedirectResponse
    {
        $validated = $this->validated($request);

        $question->update($validated);

        $this->activityLogger->log('onboarding_question.updated', $question, ['question_text' => $question->question_text]);

        return back();
    }

    public function toggleActive(OnboardingQuestion $question): RedirectResponse
    {
        $question->update(['is_active' => ! $question->is_active]);

        $this->activityLogger->log(
            $question->is_active ? 'onboarding_question.activated' : 'onboarding_question.deactivated',
            $question,
            ['question_text' => $question->question_text],
        );

        return back();
    }

    /**
     * step and order are always kept in lockstep (see OnboardingQuestionSeeder
     * - they're the same incrementing sequence number in this app, not two
     * independent concepts), so reordering swaps both together.
     */
    public function moveUp(OnboardingQuestion $question): RedirectResponse
    {
        $previous = OnboardingQuestion::where('order', '<', $question->order)->orderByDesc('order')->first();
        $this->swapOrder($question, $previous);

        return back();
    }

    public function moveDown(OnboardingQuestion $question): RedirectResponse
    {
        $next = OnboardingQuestion::where('order', '>', $question->order)->orderBy('order')->first();
        $this->swapOrder($question, $next);

        return back();
    }

    private function swapOrder(OnboardingQuestion $question, ?OnboardingQuestion $other): void
    {
        if (! $other) {
            return;
        }

        [$questionOrder, $otherOrder] = [$question->order, $other->order];

        $question->update(['step' => $otherOrder, 'order' => $otherOrder]);
        $other->update(['step' => $questionOrder, 'order' => $questionOrder]);

        $this->activityLogger->log('onboarding_question.reordered', $question, ['new_order' => $otherOrder]);
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'category' => ['required', 'string', 'max:50'],
            'question_text' => ['required', 'string', 'max:255'],
            'input_type' => ['required', Rule::in(['text', 'number', 'date', 'single_choice', 'multi_choice', 'time', 'scale'])],
            'options' => ['nullable', 'array'],
            'validation_rules' => ['nullable', 'array'],
            'is_required' => ['boolean'],
        ]);

        if (in_array($validated['input_type'], ['text', 'number', 'date', 'time']) && ! ($validated['validation_rules']['repeatable'] ?? false)) {
            $validated['options'] = null;
        }

        return $validated;
    }
}
