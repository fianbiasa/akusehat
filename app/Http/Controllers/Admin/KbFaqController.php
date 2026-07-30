<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KbFaq;
use App\Services\Admin\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KbFaqController extends Controller
{
    public function __construct(private ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $faqs = KbFaq::query()
            ->when($request->string('category')->toString(), fn ($q, $category) => $q->where('category', $category))
            ->orderBy('order')
            ->get();

        return Inertia::render('admin/kb/faqs/index', [
            'faqs' => $faqs,
            'categories' => KbFaq::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'filters' => $request->only('category'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['order'] = (int) KbFaq::max('order') + 1;

        $faq = KbFaq::create($validated);

        $this->activityLogger->log('kb_faq.created', $faq, ['question' => $faq->question]);

        return back();
    }

    public function update(Request $request, KbFaq $faq): RedirectResponse
    {
        $faq->update($this->validated($request));

        $this->activityLogger->log('kb_faq.updated', $faq, ['question' => $faq->question]);

        return back();
    }

    public function togglePublished(KbFaq $faq): RedirectResponse
    {
        $faq->update(['is_published' => ! $faq->is_published]);

        $this->activityLogger->log(
            $faq->is_published ? 'kb_faq.published' : 'kb_faq.unpublished',
            $faq,
            ['question' => $faq->question]
        );

        return back();
    }

    public function moveUp(KbFaq $faq): RedirectResponse
    {
        $this->swapOrder($faq, KbFaq::where('order', '<', $faq->order)->orderByDesc('order')->first());

        return back();
    }

    public function moveDown(KbFaq $faq): RedirectResponse
    {
        $this->swapOrder($faq, KbFaq::where('order', '>', $faq->order)->orderBy('order')->first());

        return back();
    }

    private function swapOrder(KbFaq $faq, ?KbFaq $other): void
    {
        if (! $other) {
            return;
        }

        [$faqOrder, $otherOrder] = [$faq->order, $other->order];
        $faq->update(['order' => $otherOrder]);
        $other->update(['order' => $faqOrder]);

        $this->activityLogger->log('kb_faq.reordered', $faq, ['new_order' => $otherOrder]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string'],
            'category' => ['nullable', 'string', 'max:50'],
        ]);
    }
}
