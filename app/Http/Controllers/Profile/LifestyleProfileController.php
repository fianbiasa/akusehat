<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LifestyleProfileController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'activity_level' => ['required', Rule::in(['sedentary', 'light', 'moderate', 'heavy'])],
            'sleep_time' => ['nullable', 'date_format:H:i'],
            'wake_time' => ['nullable', 'date_format:H:i'],
            'work_hours_per_day' => ['nullable', 'numeric', 'between:0,24'],
            'diet_pattern' => ['nullable', 'string', 'max:50'],
            'sugary_drinks_frequency' => ['nullable', Rule::in(['never', 'rarely', 'often', 'daily'])],
            'smoking_status' => ['nullable', Rule::in(['never', 'former', 'current'])],
            'alcohol_frequency' => ['nullable', Rule::in(['never', 'rarely', 'often', 'daily'])],
            'exercise_frequency' => ['nullable', Rule::in(['never', '1_2_week', '3_4_week', '5plus_week'])],
        ]);

        $request->user()->lifestyleProfile()->updateOrCreate([], $validated);

        return back();
    }
}
