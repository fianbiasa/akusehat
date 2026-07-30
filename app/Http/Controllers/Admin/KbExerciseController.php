<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KbExercise;
use App\Services\Admin\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class KbExerciseController extends Controller
{
    public function __construct(private ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $exercises = KbExercise::query()
            ->when($request->string('category')->toString(), fn ($q, $category) => $q->where('category', $category))
            ->when($request->string('search')->toString(), fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/kb/exercises/index', [
            'exercises' => $exercises,
            'categories' => KbExercise::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'filters' => $request->only('category', 'search'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $exercise = KbExercise::create($this->validated($request));

        $this->activityLogger->log('kb_exercise.created', $exercise, ['name' => $exercise->name]);

        return back();
    }

    public function update(Request $request, KbExercise $exercise): RedirectResponse
    {
        $exercise->update($this->validated($request));

        $this->activityLogger->log('kb_exercise.updated', $exercise, ['name' => $exercise->name]);

        return back();
    }

    public function destroy(KbExercise $exercise): RedirectResponse
    {
        $name = $exercise->name;

        // workout_plan_items.kb_exercise_id is ON DELETE SET NULL, so this
        // never fails - existing workout plans just lose the KB link.
        $exercise->delete();

        $this->activityLogger->log('kb_exercise.deleted', null, ['name' => $name]);

        return back();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:50'],
            'target_muscle' => ['nullable', 'string', 'max:100'],
            'met_value' => ['nullable', 'numeric', 'min:0'],
            'difficulty' => ['required', Rule::in(['beginner', 'intermediate', 'advanced'])],
            'equipment' => ['nullable', 'string', 'max:150'],
            'instructions' => ['nullable', 'string'],
            'video_url' => ['nullable', 'url', 'max:255'],
            'contraindications' => ['nullable', 'array'],
        ]);
    }
}
