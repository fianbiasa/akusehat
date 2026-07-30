<?php

namespace App\Http\Controllers\Programs;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Programs\Concerns\AuthorizesProgramAccess;
use App\Jobs\GenerateProgramJob;
use App\Models\Program;
use App\Models\Review;
use App\Models\UserProgram;
use App\Services\Program\ProgramGenerationStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserProgramController extends Controller
{
    use AuthorizesProgramAccess;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = UserProgram::with(['program', 'goals']);

        if (! $user->hasRole('admin')) {
            $query->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('coach_id', $user->id));
        }

        return response()->json($query->orderByDesc('created_at')->get());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'program_id' => ['required', 'exists:programs,id'],
            'start_date' => ['nullable', 'date'],
        ]);

        $program = Program::findOrFail($validated['program_id']);
        $startDate = $validated['start_date'] ?? Carbon::today()->toDateString();

        $userProgram = $request->user()->programs()->create([
            'program_id' => $program->id,
            'status' => 'active',
            'start_date' => $startDate,
            'end_date' => Carbon::parse($startDate)->addDays($program->default_duration_days - 1),
            'created_by' => 'user',
        ]);

        GenerateProgramJob::dispatch($userProgram, Carbon::parse($startDate)->toDateString());

        return back();
    }

    public function show(Request $request, UserProgram $userProgram): Response
    {
        abort_unless($this->canView($request->user(), $userProgram), 403);

        $userProgram->load(['program', 'goals' => fn ($q) => $q->latest(), 'coach:id,name']);
        $date = Carbon::today()->toDateString();

        $myReview = $userProgram->coach_id && $userProgram->user_id === $request->user()->id
            ? Review::where('coach_id', $userProgram->coach_id)->where('member_id', $request->user()->id)->first()
            : null;

        return Inertia::render('programs/show', [
            'userProgram' => $userProgram,
            'weeklyPlans' => $userProgram->weeklyPlans()->orderBy('week_number')->get(),
            'mealPlans' => $userProgram->mealPlans()->with('items.kbFood')->whereDate('plan_date', $date)->get(),
            'workoutPlans' => $userProgram->workoutPlans()->with('items.kbExercise')->whereDate('plan_date', $date)->get(),
            'generateStatus' => ProgramGenerationStatus::get($userProgram->id, $date),
            'myReview' => $myReview,
        ]);
    }

    public function update(Request $request, UserProgram $userProgram): RedirectResponse
    {
        abort_unless($this->canManage($request->user(), $userProgram), 403);

        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(['active', 'paused', 'completed', 'cancelled'])],
            'end_date' => ['sometimes', 'nullable', 'date'],
        ]);

        $userProgram->update($validated);

        return back();
    }

    public function regenerate(Request $request, UserProgram $userProgram): RedirectResponse
    {
        abort_unless($this->canManage($request->user(), $userProgram), 403);

        $date = Carbon::today()->toDateString();
        GenerateProgramJob::dispatch($userProgram, $date);

        return back();
    }

    public function generateStatus(Request $request, UserProgram $userProgram): JsonResponse
    {
        abort_unless($this->canView($request->user(), $userProgram), 403);

        $date = $request->query('date', Carbon::today()->toDateString());
        $status = ProgramGenerationStatus::get($userProgram->id, $date);

        if ($status === 'unknown' && $userProgram->mealPlans()->whereDate('plan_date', $date)->exists()) {
            $status = 'ready';
        }

        return response()->json(['status' => $status]);
    }
}
