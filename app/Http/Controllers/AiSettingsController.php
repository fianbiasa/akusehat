<?php

namespace App\Http\Controllers;

use App\Models\AiProvider;
use App\Models\UserAiSetting;
use App\Services\AI\AIProviderException;
use App\Services\AI\AIProviderFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AiSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/ai', [
            'settings' => $request->user()->aiSettings()->with(['provider:id,name,slug,type', 'model:id,name,model_key'])->get(),
            'providers' => AiProvider::where('is_active', true)->with(['models' => fn ($q) => $q->where('is_active', true)])->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        DB::transaction(function () use ($request, $validated) {
            if ($validated['is_default'] ?? false) {
                $request->user()->aiSettings()->update(['is_default' => false]);
            }

            $request->user()->aiSettings()->create($validated);
        });

        return back();
    }

    public function update(Request $request, UserAiSetting $setting): RedirectResponse
    {
        abort_unless($setting->user_id === $request->user()->id, 403);

        $validated = $this->validated($request, $setting);

        DB::transaction(function () use ($request, $setting, $validated) {
            if ($validated['is_default'] ?? false) {
                $request->user()->aiSettings()->where('id', '!=', $setting->id)->update(['is_default' => false]);
            }

            $setting->update($validated);
        });

        return back();
    }

    public function destroy(Request $request, UserAiSetting $setting): RedirectResponse
    {
        abort_unless($setting->user_id === $request->user()->id, 403);

        $setting->delete();

        return back();
    }

    public function setDefault(Request $request, UserAiSetting $setting): RedirectResponse
    {
        abort_unless($setting->user_id === $request->user()->id, 403);

        DB::transaction(function () use ($request, $setting) {
            $request->user()->aiSettings()->where('id', '!=', $setting->id)->update(['is_default' => false]);
            $setting->update(['is_default' => true]);
        });

        return back();
    }

    /**
     * Fires a lightweight test call directly through the provider adapter -
     * deliberately bypasses AIResponseProcessor's retry/schema-validation
     * loop, since this is only meant to prove the credentials/base_url
     * are reachable, not exercise a real capability.
     */
    public function test(Request $request, UserAiSetting $setting, AIProviderFactory $factory): JsonResponse
    {
        abort_unless($setting->user_id === $request->user()->id, 403);

        $setting->load(['provider', 'model']);
        $provider = $factory->make($setting->provider, $setting->model, $setting->decryptedApiKey(), (float) $setting->temperature);

        $startedAt = microtime(true);

        try {
            $provider->chat([['role' => 'user', 'content' => 'Reply with {"ok": true} and nothing else.']]);
        } catch (AIProviderException $e) {
            return response()->json([
                'success' => false,
                'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'message' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'message' => 'Koneksi berhasil.',
        ]);
    }

    private function validated(Request $request, ?UserAiSetting $existing = null): array
    {
        $validated = $request->validate([
            'provider_id' => ['required', 'exists:ai_providers,id'],
            'model_id' => ['required', 'exists:ai_models,id'],
            'api_key' => ['nullable', 'string'],
            'temperature' => ['required', 'numeric', 'between:0,2'],
            'is_default' => ['boolean'],
        ]);

        if (! ($validated['api_key'] ?? null) && $existing) {
            unset($validated['api_key']);
        }

        return $validated;
    }
}
