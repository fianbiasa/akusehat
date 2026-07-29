<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RuleEngineRule;
use App\Services\RuleEngine\RuleEngineConditionEvaluator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RuleEngineController extends Controller
{
    public function index(Request $request): Response
    {
        $rules = RuleEngineRule::query()
            ->when($request->string('category')->toString(), fn ($q, $category) => $q->where('category', $category))
            ->orderBy('category')
            ->orderByDesc('priority')
            ->get();

        return Inertia::render('admin/rule-engine/index', [
            'rules' => $rules,
            'categories' => RuleEngineRule::query()->distinct()->orderBy('category')->pluck('category'),
            'filters' => $request->only('category'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        RuleEngineRule::create($validated);

        return back();
    }

    public function update(Request $request, RuleEngineRule $rule): RedirectResponse
    {
        $validated = $this->validated($request);

        $rule->update($validated);

        return back();
    }

    public function destroy(RuleEngineRule $rule): RedirectResponse
    {
        // Deactivate rather than hard-delete, per docs/05-API-Specification.md §11.
        $rule->update(['is_active' => false]);

        return back();
    }

    /**
     * "Uji Coba" - dry-run a rule's condition against a sample profile
     * payload, showing the resulting action without touching a real user.
     */
    public function test(Request $request, RuleEngineRule $rule, RuleEngineConditionEvaluator $evaluator): JsonResponse
    {
        $context = $request->validate([
            'bmi' => ['nullable', 'numeric'],
            'age' => ['nullable', 'integer'],
            'gender' => ['nullable', 'string'],
            'activity_level' => ['nullable', 'string'],
            'diseases' => ['nullable', 'array'],
            'weight_kg' => ['nullable', 'numeric'],
            'tdee' => ['nullable', 'numeric'],
        ]);

        $matches = $evaluator->evaluate($rule->condition, $context);

        return response()->json([
            'matches' => $matches,
            'action' => $matches ? $rule->action : null,
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'category' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:150'],
            'condition' => ['required', 'array'],
            'action' => ['required', 'array'],
            'priority' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);
    }
}
