<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Models\UserAllergy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserAllergyController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'allergen' => ['required', 'string', 'max:150'],
            'severity' => ['nullable', Rule::in(['mild', 'moderate', 'severe'])],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $request->user()->allergies()->create($validated);

        return back();
    }

    public function destroy(Request $request, UserAllergy $allergy): RedirectResponse
    {
        abort_unless($allergy->user_id === $request->user()->id, 403);

        $allergy->delete();

        return back();
    }
}
