<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\KbDisease;
use App\Services\HealthProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class HealthProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $earnedMap = $user->userAchievements()->pluck('earned_at', 'achievement_id');

        return Inertia::render('settings/health', [
            'healthProfile' => $user->healthProfile,
            'lifestyleProfile' => $user->lifestyleProfile,
            'diseases' => $user->diseases()->with('disease:id,name')->get(),
            'allergies' => $user->allergies()->get(),
            'medications' => $user->medications()->get(),
            'measurements' => $user->bodyMeasurements()->orderByDesc('measured_at')->limit(10)->get(),
            'kbDiseases' => KbDisease::orderBy('name')->get(['id', 'name']),
            'achievements' => Achievement::orderBy('name')->get()->map(fn ($achievement) => [
                'id' => $achievement->id,
                'name' => $achievement->name,
                'description' => $achievement->description,
                'icon' => $achievement->icon,
                'earned_at' => $earnedMap->get($achievement->id),
            ]),
        ]);
    }

    public function update(Request $request, HealthProfileService $healthProfileService): RedirectResponse
    {
        $validated = $request->validate([
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'height_cm' => ['nullable', 'numeric', 'between:50,300'],
            'initial_weight_kg' => ['nullable', 'numeric', 'between:20,400'],
            'blood_type' => ['nullable', 'string', 'max:5'],
        ]);

        $request->user()->healthProfile()->updateOrCreate([], $validated);
        $healthProfileService->recalculate($request->user());

        return back();
    }
}
