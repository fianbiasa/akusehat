<?php

namespace App\Http\Controllers\Programs;

use App\Events\CheckInSubmitted;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Programs\Concerns\AuthorizesProgramAccess;
use App\Models\ChecklistItem;
use App\Models\UserProgram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ChecklistItemController extends Controller
{
    use AuthorizesProgramAccess;

    public function index(Request $request, UserProgram $userProgram): JsonResponse
    {
        abort_unless($this->canView($request->user(), $userProgram), 403);

        $date = $request->query('date', now()->toDateString());

        return response()->json($userProgram->checklistItems()->whereDate('item_date', $date)->get());
    }

    public function update(Request $request, ChecklistItem $checklistItem): RedirectResponse
    {
        abort_unless($checklistItem->userProgram->user_id === $request->user()->id, 403);

        $validated = $request->validate(['is_checked' => ['required', 'boolean']]);

        $checklistItem->update([
            'is_checked' => $validated['is_checked'],
            'checked_at' => $validated['is_checked'] ? now() : null,
        ]);

        CheckInSubmitted::dispatch($request->user());

        return back();
    }
}
