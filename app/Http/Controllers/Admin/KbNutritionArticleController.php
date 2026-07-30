<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KbNutritionArticle;
use App\Services\Admin\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class KbNutritionArticleController extends Controller
{
    public function __construct(private ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $articles = KbNutritionArticle::query()
            ->when($request->string('category')->toString(), fn ($q, $category) => $q->where('category', $category))
            ->when($request->string('search')->toString(), fn ($q, $search) => $q->where('title', 'like', "%{$search}%"))
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('admin/kb/articles/index', [
            'articles' => $articles,
            'categories' => KbNutritionArticle::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'filters' => $request->only('category', 'search'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['slug'] = $this->uniqueSlug($validated['title']);

        $article = KbNutritionArticle::create($validated);

        $this->activityLogger->log('kb_article.created', $article, ['title' => $article->title]);

        return back();
    }

    public function update(Request $request, KbNutritionArticle $article): RedirectResponse
    {
        $validated = $this->validated($request);
        if ($validated['title'] !== $article->title) {
            $validated['slug'] = $this->uniqueSlug($validated['title'], $article->id);
        }

        $article->update($validated);

        $this->activityLogger->log('kb_article.updated', $article, ['title' => $article->title]);

        return back();
    }

    public function togglePublished(KbNutritionArticle $article): RedirectResponse
    {
        $article->update(['is_published' => ! $article->is_published]);

        $this->activityLogger->log(
            $article->is_published ? 'kb_article.published' : 'kb_article.unpublished',
            $article,
            ['title' => $article->title]
        );

        return back();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'category' => ['nullable', 'string', 'max:50'],
            'content' => ['required', 'string'],
            'tags' => ['nullable', 'array'],
        ]);
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $i = 1;

        while (KbNutritionArticle::where('slug', $slug)->when($ignoreId, fn ($q, $id) => $q->where('id', '!=', $id))->exists()) {
            $slug = "{$base}-".++$i;
        }

        return $slug;
    }
}
