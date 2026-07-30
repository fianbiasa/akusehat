<?php

namespace App\Http\Controllers\Programs;

use App\Http\Controllers\Controller;
use App\Models\Reminder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReminderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($request->user()->reminders()->orderBy('scheduled_at')->get());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['water', 'meal', 'workout', 'checkin', 'medication'])],
            'title' => ['required', 'string', 'max:150'],
            'message' => ['nullable', 'string', 'max:255'],
            'scheduled_at' => ['required', 'date_format:H:i'],
            'is_recurring' => ['boolean'],
        ]);

        $request->user()->reminders()->create([
            ...$validated,
            'recurrence_rule' => ($validated['is_recurring'] ?? true) ? 'RRULE:FREQ=DAILY' : null,
        ]);

        return back();
    }

    public function update(Request $request, Reminder $reminder): RedirectResponse
    {
        abort_unless($reminder->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:150'],
            'message' => ['sometimes', 'nullable', 'string', 'max:255'],
            'scheduled_at' => ['sometimes', 'date_format:H:i'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $reminder->update($validated);

        return back();
    }

    public function destroy(Request $request, Reminder $reminder): RedirectResponse
    {
        abort_unless($reminder->user_id === $request->user()->id, 403);

        $reminder->delete();

        return back();
    }
}
