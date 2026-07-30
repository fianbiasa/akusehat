<?php

namespace App\Http\Controllers\Programs;

use App\Events\CheckInSubmitted;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Programs\Concerns\AuthorizesProgramAccess;
use App\Models\DailyTask;
use App\Models\UserProgram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DailyTaskController extends Controller
{
    use AuthorizesProgramAccess;

    public function index(Request $request, UserProgram $userProgram): JsonResponse
    {
        abort_unless($this->canView($request->user(), $userProgram), 403);

        $date = $request->query('date', now()->toDateString());

        return response()->json($userProgram->dailyTasks()->whereDate('task_date', $date)->get());
    }

    public function update(Request $request, DailyTask $dailyTask): RedirectResponse
    {
        abort_unless($dailyTask->userProgram->user_id === $request->user()->id, 403);

        $validated = $request->validate(['is_completed' => ['required', 'boolean']]);

        $dailyTask->update([
            'is_completed' => $validated['is_completed'],
            'completed_at' => $validated['is_completed'] ? now() : null,
        ]);

        CheckInSubmitted::dispatch($request->user());

        return back();
    }
}
