<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiPromptTemplate;
use App\Services\Admin\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiPromptTemplateController extends Controller
{
    public function __construct(private ActivityLogger $activityLogger) {}

    public function index(): Response
    {
        return Inertia::render('admin/ai-prompt-templates/index', [
            'templates' => AiPromptTemplate::orderBy('key')->get(),
        ]);
    }

    /**
     * Templates are seed-managed (their `key` is referenced directly by
     * app code, per docs/11-Development-Checklist.md), so this only edits
     * content - it never creates or deletes a key. Every save bumps
     * `version` per FR-PB-03: historic ai_request_logs are never
     * retroactively reinterpreted against a newer template.
     */
    public function update(Request $request, AiPromptTemplate $promptTemplate): RedirectResponse
    {
        $validated = $request->validate([
            'purpose' => ['required', 'string', 'max:255'],
            'template' => ['required', 'string'],
            'variables' => ['required', 'array'],
            'response_schema' => ['required', 'array'],
            'is_active' => ['boolean'],
        ]);

        $validated['version'] = $promptTemplate->version + 1;

        $promptTemplate->update($validated);

        $this->activityLogger->log('ai_prompt_template.updated', $promptTemplate, [
            'key' => $promptTemplate->key,
            'version' => $promptTemplate->version,
        ]);

        return back();
    }
}
