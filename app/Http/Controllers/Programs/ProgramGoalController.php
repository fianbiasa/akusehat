<?php

namespace App\Http\Controllers\Programs;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Programs\Concerns\AuthorizesProgramAccess;
use App\Models\UserProgram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProgramGoalController extends Controller
{
    use AuthorizesProgramAccess;

    public function index(Request $request, UserProgram $userProgram): JsonResponse
    {
        abort_unless($this->canView($request->user(), $userProgram), 403);

        return response()->json($userProgram->goals()->latest()->get());
    }

    public function store(Request $request, UserProgram $userProgram): RedirectResponse
    {
        abort_unless($this->canManage($request->user(), $userProgram), 403);

        $validated = $request->validate([
            'goal_type' => ['required', Rule::in(['weight_loss', 'weight_gain', 'maintenance', 'endurance'])],
            'target_weight_kg' => ['nullable', 'numeric'],
            'target_waist_cm' => ['nullable', 'numeric'],
            'target_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $userProgram->goals()->create($validated);

        return back();
    }
}
