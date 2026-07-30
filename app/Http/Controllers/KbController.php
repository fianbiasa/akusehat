<?php

namespace App\Http\Controllers;

use App\Models\KbDisease;
use App\Models\KbExercise;
use App\Models\KbFaq;
use App\Models\KbFood;
use App\Models\KbNutritionArticle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only search/browse per docs/05-API-Specification.md §12. Content
 * itself is admin-curated via Admin\Kb*Controller (foods/exercises/
 * diseases/articles/FAQs).
 */
class KbController extends Controller
{
    public function foods(Request $request): JsonResponse
    {
        $foods = KbFood::query()
            ->when($request->string('q')->trim()->toString(), fn ($q, $term) => $q->where(
                fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('name_local', 'like', "%{$term}%")
            ))
            ->when($request->string('category')->toString(), fn ($q, $category) => $q->where('category', $category))
            ->when($request->input('tags'), fn ($q, $tags) => $q->where(
                fn ($q) => collect($tags)->each(fn ($tag) => $q->orWhereJsonContains('tags', $tag))
            ))
            ->orderBy('name_local')
            ->paginate(20);

        return response()->json($foods);
    }

    public function food(KbFood $food): JsonResponse
    {
        return response()->json($food);
    }

    public function exercises(Request $request): JsonResponse
    {
        $exercises = KbExercise::query()
            ->when($request->string('q')->trim()->toString(), fn ($q, $term) => $q->where('name', 'like', "%{$term}%"))
            ->when($request->string('category')->toString(), fn ($q, $category) => $q->where('category', $category))
            ->orderBy('name')
            ->paginate(20);

        return response()->json($exercises);
    }

    public function exercise(KbExercise $exercise): JsonResponse
    {
        return response()->json($exercise);
    }

    public function diseases(): JsonResponse
    {
        return response()->json(KbDisease::orderBy('name')->get());
    }

    public function disease(KbDisease $disease): JsonResponse
    {
        return response()->json($disease);
    }

    public function articles(): JsonResponse
    {
        return response()->json(KbNutritionArticle::where('is_published', true)->orderByDesc('created_at')->paginate(20));
    }

    public function article(string $slug): JsonResponse
    {
        return response()->json(KbNutritionArticle::where('slug', $slug)->where('is_published', true)->firstOrFail());
    }

    public function faqs(): JsonResponse
    {
        return response()->json(KbFaq::where('is_published', true)->orderBy('order')->get());
    }
}
