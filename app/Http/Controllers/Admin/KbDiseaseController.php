<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KbDisease;
use App\Models\UserDisease;
use App\Services\Admin\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class KbDiseaseController extends Controller
{
    public function __construct(private ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $diseases = KbDisease::query()
            ->when($request->string('category')->toString(), fn ($q, $category) => $q->where('category', $category))
            ->when($request->string('search')->toString(), fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/kb/diseases/index', [
            'diseases' => $diseases,
            'categories' => KbDisease::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'filters' => $request->only('category', 'search'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['slug'] = $this->uniqueSlug($validated['name']);

        $disease = KbDisease::create($validated);

        $this->activityLogger->log('kb_disease.created', $disease, ['name' => $disease->name]);

        return back();
    }

    public function update(Request $request, KbDisease $disease): RedirectResponse
    {
        $validated = $this->validated($request);
        if ($validated['name'] !== $disease->name) {
            $validated['slug'] = $this->uniqueSlug($validated['name'], $disease->id);
        }

        $disease->update($validated);

        $this->activityLogger->log('kb_disease.updated', $disease, ['name' => $disease->name]);

        return back();
    }

    public function destroy(KbDisease $disease): RedirectResponse
    {
        // user_diseases.kb_disease_id has no ON DELETE clause (RESTRICT),
        // so check up front rather than let the DB throw.
        if (UserDisease::where('kb_disease_id', $disease->id)->exists()) {
            return back()->with('error', "Tidak dapat menghapus \"{$disease->name}\" karena masih tercatat pada riwayat penyakit pengguna.");
        }

        $name = $disease->name;
        $disease->delete();

        $this->activityLogger->log('kb_disease.deleted', null, ['name' => $name]);

        return back();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'dietary_restrictions' => ['nullable', 'array'],
            'recommended_exercise' => ['nullable', 'array'],
            'contraindicated_exercise' => ['nullable', 'array'],
            'reference_source' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;

        while (KbDisease::where('slug', $slug)->when($ignoreId, fn ($q, $id) => $q->where('id', '!=', $id))->exists()) {
            $slug = "{$base}-".++$i;
        }

        return $slug;
    }
}
