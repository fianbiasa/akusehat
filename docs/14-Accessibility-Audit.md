# Accessibility & Responsive Audit — Phase 13

WCAG 2.1 AA pass on the 3 flows named in the Phase 13 checklist: onboarding, dashboard, and the daily check-in (checklist) flow, plus a responsive QA pass across every wireframed page. Both conducted 2026-07-30 via code-level review (Tailwind class audit, keyboard/screen-reader semantics, label association) — not a live browser/axe-core/Lighthouse run, since no browser automation tooling was available in this environment. A follow-up automated pass is recommended before launch (see below).

## Accessibility (WCAG 2.1 AA)

## Real issues found and fixed

| Location | Issue | Fix |
|---|---|---|
| `resources/js/pages/onboarding/wizard.tsx` + `question-input.tsx` | The question heading (`<h1>`) had no `id`, and none of the `text`/`number`/`date`/`time` inputs were programmatically associated with it — a screen reader landing on the field announced only "edit text", with no indication of which question was being asked. | Gave the heading `id="question-title"`, threaded an `ariaLabelledBy` prop down through `QuestionInput` to every input variant (`aria-labelledby`). |
| `question-input.tsx` `ChoiceCards`/`ScaleInput` | Single/multi-choice option buttons and the 1-5 scale buttons convey "selected" purely via a visual border/background color change — no `aria-pressed`, so a screen reader user had no way to tell which option(s) were currently selected. | Added `aria-pressed={selected}` to every option button, plus `role="group" aria-labelledby=...` on the containing div so the group itself is announced with the question text. |
| `question-input.tsx` `RepeatableRows` | The per-row text inputs relied on `placeholder` as their only label (placeholder text isn't a WCAG-valid label substitute — it disappears once typed and isn't reliably announced). The "remove row" button was icon-only (`Trash2`) with no accessible name at all. | Added `aria-label={field.label}` to each input, `aria-label="Hapus baris"` to the remove button. |
| `resources/js/pages/dashboard.tsx` (daily checklist / check-in) | The checklist checkbox and its label text were siblings with **no** association at all (no `id`/`htmlFor`, not nested in a `<label>`) — the single most-used interactive element in the app's daily check-in flow had no accessible name whatsoever. | Gave each `Checkbox` an `id`, wrapped the label text in a real `<Label htmlFor=...>`. |

Every other `<Checkbox>` usage found elsewhere in the codebase (Admin Roles, Progress photo sharing, Admin Settings, Admin Plans, Coach note visibility, Login "remember me") was already correctly labeled — either via explicit `id`/`htmlFor` or by being nested inside a native `<label>` element (both are valid WCAG-conformant patterns). The one `<img>` tag in the app (progress photos) already has a descriptive `alt`.

## Not independently re-verified in this pass
- **Color contrast**: relies entirely on the shadcn/ui + Tailwind default palette, which is designed to meet WCAG AA contrast ratios out of the box; this pass did not run an automated contrast checker against the rendered pages.
- **Focus indicators**: shadcn/ui's Radix-based components (`Button`, `Checkbox`, `Input`, `Select`, `Dialog`) ship with `focus-visible` ring styles by default and weren't overridden anywhere found in this codebase.
- **Screen-reader end-to-end walkthrough**: this was a code-level review, not a live pass with an actual screen reader (VoiceOver/NVDA/JAWS) — recommended before launch.

## Responsive QA

Code-level audit of Tailwind responsive classes across every Admin/Settings page built through Phase 13 (the member-facing pages — dashboard, progress, onboarding, coach dashboard — already used responsive breakpoints consistently going in).

| Location | Issue | Fix |
|---|---|---|
| `admin/subscriptions`, `admin/users`, `admin/activity-log`, `admin/rule-engine`, `settings/subscription` (5 pages) | Every data table's wrapper used `overflow-hidden rounded-lg border` — `overflow-hidden` was meant to clip the wrapper's corners to match `rounded-lg`, but it also silently **clips** any table content wider than the viewport instead of making it scrollable. On a narrow phone, columns past the edge (e.g. Admin Users' Actions column, Admin Subscriptions' Berakhir date) would simply be invisible and unreachable. | Changed the wrapper to `overflow-x-auto rounded-lg border` on all 5 — the table now scrolls horizontally within its own container on narrow viewports instead of clipping. |
| `admin/rule-engine` (×2), `admin/settings`, `admin/plans`, `admin/ai-providers`, `settings/health` (×2) — 7 form-field grids across 6 files | Two-column form grids (`grid grid-cols-2 gap-4`) with no responsive breakpoint — on a narrow phone this crams two label+input pairs into ~140px columns each, cramped for longer labels/values. | Changed to `grid grid-cols-1 gap-4 sm:grid-cols-2` — single column on mobile, two columns from the `sm:` breakpoint up, matching the pattern already used correctly elsewhere in the codebase (e.g. the achievement badge grid). |

Also checked and confirmed already correct, no changes needed:
- Every `<Dialog>` in the app inherits the shared `dialog.tsx` component's `w-full max-w-lg` sizing — fluid down to the viewport width on mobile, only capped on larger screens. No dialog anywhere overrides this with a fixed, non-responsive width.
- No other un-prefixed `grid-cols-{2,3,4...}` instances remain anywhere in `resources/js/pages/`.

## Follow-up recommended before launch
Run an automated pass (axe-core via Playwright, or Lighthouse's accessibility + mobile-usability audits) against the onboarding wizard, dashboard, and Settings pages once a browser-automation environment is available; do a live screen-reader walkthrough of the onboarding wizard end-to-end (it's the single longest, most form-heavy flow a new user encounters); and manually spot-check the 5 fixed data tables on an actual narrow device/emulator to confirm the horizontal-scroll UX feels acceptable rather than just technically non-clipping.
