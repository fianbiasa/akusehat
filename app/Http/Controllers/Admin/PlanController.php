<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\Admin\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    public function __construct(private ActivityLogger $activityLogger) {}

    public function index(): Response
    {
        return Inertia::render('admin/plans/index', [
            'plans' => Plan::orderBy('price')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request, isNew: true);

        $plan = Plan::create($validated);

        $this->activityLogger->log('plan.created', $plan, ['name' => $plan->name]);

        return back();
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $validated = $this->validated($request, isNew: false);

        $plan->update($validated);

        $this->activityLogger->log('plan.updated', $plan, ['name' => $plan->name]);

        return back();
    }

    private function validated(Request $request, bool $isNew): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => [$isNew ? 'required' : 'sometimes', 'string', 'max:100', Rule::unique('plans', 'slug')->ignore($request->route('plan'))],
            'price' => ['required', 'numeric', 'min:0'],
            'billing_cycle' => ['required', Rule::in(['monthly', 'yearly', 'lifetime'])],
            'features' => ['nullable', 'array'],
            'max_programs' => ['required', 'integer', 'min:1'],
            'has_coach_access' => ['boolean'],
            'is_active' => ['boolean'],
        ]);
    }
}
