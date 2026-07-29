<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiModel;
use App\Models\AiProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AiProviderController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/ai-providers/index', [
            'providers' => AiProvider::with('models')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'slug' => ['required', 'string', 'max:50', 'unique:ai_providers,slug'],
            'type' => ['required', Rule::in(['cloud', 'local'])],
            'base_url' => ['nullable', 'string', 'max:255'],
            'driver_class' => ['required', 'string', 'max:150'],
            'is_active' => ['boolean'],
        ]);

        AiProvider::create($validated);

        return back();
    }

    public function update(Request $request, AiProvider $provider): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'type' => ['required', Rule::in(['cloud', 'local'])],
            'base_url' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        $provider->update($validated);

        return back();
    }

    public function storeModel(Request $request, AiProvider $provider): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'model_key' => ['required', 'string', 'max:100'],
            'context_length' => ['nullable', 'integer', 'min:1'],
            'supports_json_mode' => ['boolean'],
            'input_cost_per_1k' => ['nullable', 'numeric', 'min:0'],
            'output_cost_per_1k' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $provider->models()->create($validated);

        return back();
    }

    public function updateModel(Request $request, AiModel $model): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'context_length' => ['nullable', 'integer', 'min:1'],
            'supports_json_mode' => ['boolean'],
            'input_cost_per_1k' => ['nullable', 'numeric', 'min:0'],
            'output_cost_per_1k' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $model->update($validated);

        return back();
    }
}
