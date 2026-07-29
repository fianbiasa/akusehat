<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BodyMeasurementController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'measured_at' => ['required', 'date'],
            'weight_kg' => ['nullable', 'numeric', 'between:20,400'],
            'waist_cm' => ['nullable', 'numeric', 'between:30,300'],
            'chest_cm' => ['nullable', 'numeric', 'between:30,300'],
            'hip_cm' => ['nullable', 'numeric', 'between:30,300'],
            'arm_cm' => ['nullable', 'numeric', 'between:10,100'],
            'thigh_cm' => ['nullable', 'numeric', 'between:10,150'],
            'body_fat_pct' => ['nullable', 'numeric', 'between:1,80'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        // whereDate(), not updateOrCreate()'s raw-string match on
        // measured_at directly - the date cast can round-trip through
        // storage with a time component depending on DB driver, which
        // would otherwise make this insert a duplicate instead of updating.
        $measurement = $request->user()->bodyMeasurements()->whereDate('measured_at', $validated['measured_at'])->first()
            ?? $request->user()->bodyMeasurements()->make();

        $measurement->fill($validated)->save();

        return back();
    }
}
