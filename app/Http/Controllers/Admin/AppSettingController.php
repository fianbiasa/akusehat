<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiProvider;
use App\Services\Admin\ActivityLogger;
use App\Services\AppSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AppSettingController extends Controller
{
    public function __construct(private AppSettingsService $appSettings, private ActivityLogger $activityLogger) {}

    public function edit(): Response
    {
        $aiDefault = $this->appSettings->get('ai.platform_default');

        return Inertia::render('admin/settings/index', [
            'aiDefault' => $aiDefault ? [
                'provider_id' => $aiDefault['provider_id'],
                'model_id' => $aiDefault['model_id'],
                'temperature' => $aiDefault['temperature'] ?? 0.7,
                'has_api_key' => ! empty($aiDefault['api_key_encrypted']),
            ] : null,
            'maintenanceMode' => [
                'enabled' => $this->appSettings->isMaintenanceMode(),
                'message' => $this->appSettings->maintenanceMessage(),
            ],
            'providers' => AiProvider::where('is_active', true)->with(['models' => fn ($q) => $q->where('is_active', true)])->get(),
        ]);
    }

    public function updateAiDefault(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'provider_id' => ['required', 'exists:ai_providers,id'],
            'model_id' => ['required', 'exists:ai_models,id'],
            'api_key' => ['nullable', 'string'],
            'temperature' => ['required', 'numeric', 'between:0,2'],
        ]);

        $this->appSettings->setPlatformDefaultAiSetting(
            $validated['provider_id'],
            $validated['model_id'],
            $validated['api_key'] ?? null,
            (float) $validated['temperature'],
        );

        $this->activityLogger->log('app_setting.ai_default_updated', null, ['provider_id' => $validated['provider_id'], 'model_id' => $validated['model_id']]);

        return back();
    }

    public function updateMaintenanceMode(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $this->appSettings->setMaintenanceMode($validated['enabled'], $validated['message'] ?? null);

        $this->activityLogger->log('app_setting.maintenance_mode_updated', null, ['enabled' => $validated['enabled']]);

        return back();
    }
}
