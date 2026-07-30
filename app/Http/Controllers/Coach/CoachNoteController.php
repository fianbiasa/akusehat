<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachNote;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CoachNoteController extends Controller
{
    public function index(Request $request, User $member): JsonResponse
    {
        $this->authorizeAssigned($request->user(), $member);

        return response()->json(
            $member->coachNotesAbout()->where('coach_id', $request->user()->id)->latest()->get()
        );
    }

    public function store(Request $request, User $member): RedirectResponse
    {
        $this->authorizeAssigned($request->user(), $member);

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
            'is_visible_to_member' => ['boolean'],
        ]);

        CoachNote::create([
            'coach_id' => $request->user()->id,
            'member_id' => $member->id,
            'note' => $validated['note'],
            'is_visible_to_member' => $validated['is_visible_to_member'] ?? false,
        ]);

        return back();
    }

    public function update(Request $request, CoachNote $note): RedirectResponse
    {
        abort_unless($note->coach_id === $request->user()->id, 403);

        $validated = $request->validate([
            'note' => ['sometimes', 'string', 'max:2000'],
            'is_visible_to_member' => ['sometimes', 'boolean'],
        ]);

        $note->update($validated);

        return back();
    }

    private function authorizeAssigned(User $coach, User $member): void
    {
        abort_unless($coach->coachedMembers()->where('member_id', $member->id)->where('status', 'active')->exists(), 403);
    }
}
