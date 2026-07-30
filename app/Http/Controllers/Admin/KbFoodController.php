<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KbFood;
use App\Services\Admin\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KbFoodController extends Controller
{
    public function __construct(private ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $foods = KbFood::query()
            ->when($request->string('category')->toString(), fn ($q, $category) => $q->where('category', $category))
            ->when($request->string('search')->toString(), fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")->orWhere('name_local', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/kb/foods/index', [
            'foods' => $foods,
            'categories' => KbFood::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'filters' => $request->only('category', 'search'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $food = KbFood::create($this->validated($request));

        $this->activityLogger->log('kb_food.created', $food, ['name' => $food->name]);

        return back();
    }

    public function update(Request $request, KbFood $food): RedirectResponse
    {
        $food->update($this->validated($request));

        $this->activityLogger->log('kb_food.updated', $food, ['name' => $food->name]);

        return back();
    }

    public function destroy(KbFood $food): RedirectResponse
    {
        $name = $food->name;

        // meal_plan_items.kb_food_id is ON DELETE SET NULL, so this never
        // fails - existing meal plans just fall back to their custom_name.
        $food->delete();

        $this->activityLogger->log('kb_food.deleted', null, ['name' => $name]);

        return back();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'name_local' => ['nullable', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:50'],
            'serving_unit' => ['required', 'string', 'max:30'],
            'serving_size' => ['required', 'numeric', 'min:0'],
            'calories' => ['required', 'numeric', 'min:0'],
            'protein_g' => ['required', 'numeric', 'min:0'],
            'carbs_g' => ['required', 'numeric', 'min:0'],
            'fat_g' => ['required', 'numeric', 'min:0'],
            'fiber_g' => ['nullable', 'numeric', 'min:0'],
            'sodium_mg' => ['nullable', 'numeric', 'min:0'],
            'glycemic_index' => ['nullable', 'integer', 'min:0', 'max:110'],
            'tags' => ['nullable', 'array'],
            'source' => ['nullable', 'string', 'max:150'],
        ]);
    }
}
