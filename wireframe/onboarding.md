# Wireframe — Onboarding Wizard

Related: [docs/09-UI-UX-Wireframe.md](../docs/09-UI-UX-Wireframe.md) · FR-ONB in [01-PRD.md](../docs/01-PRD.md) §8.2

## Layout Pattern (repeats per step)

```
┌───────────────────────────────────────────┐
│ ●●●●●●●●●●○○○○○○○○○○○○○○○○○○○○○○○○○○○○○○  │  ← progress bar (step X of ~55)
│                                             │
│         Berapa berat badan kamu            │  ← one focused question
│              sekarang?                     │
│                                             │
│            ┌───────────────┐               │
│            │     77.5      │  kg           │  ← large single input
│            └───────────────┘               │
│                                             │
│                                             │
│  ← Kembali                     Lanjut →     │  ← back / next (next disabled until valid)
└───────────────────────────────────────────┘
```

## Step Sequence (category-grouped, ~55 questions total)

1. **Identity** (steps 1–5): name, gender, date of birth, phone (optional), preferred language
2. **Body** (steps 6–10): height, current weight, waist circumference, target weight, target timeframe
3. **Activity/Lifestyle** (steps 11–20): activity level (illustrated choice cards: duduk terus / ringan / sedang / berat), sleep time, wake time, work hours, diet pattern, sugary drinks frequency, smoking, alcohol, exercise frequency, preferred workout time
4. **Medical** (steps 21–35): disease checklist (multi-select cards: Diabetes, Hipertensi, Kolesterol, Asam Urat, Tukak Lambung/GERD, dll.), per-disease follow-up (diagnosed when, currently managed how), medications (repeatable add-row), allergies (repeatable add-row)
5. **Preferences** (steps 36–45): food preferences/dislikes, cuisine preferences, equipment access, program category interest (diet/bulking/etc.)
6. **Goals & Motivation** (steps 46–55): why now (free text or preset chips), biggest past obstacle, preferred reminder times, notification preferences

## Choice-Card Pattern (used for activity level, disease checklist, etc.)

```
┌───────────────┐ ┌───────────────┐ ┌───────────────┐ ┌───────────────┐
│  🪑 Duduk Terus│ │  🚶 Ringan     │ │  🏃 Sedang     │ │  💪 Berat      │
│  (selected: ●) │ │               │ │               │ │               │
└───────────────┘ └───────────────┘ └───────────────┘ └───────────────┘
```

## Completion Screen

```
┌───────────────────────────────────────────┐
│               ✅ Selesai!                   │
│                                             │
│   Sedang menyusun program 90 hari kamu...  │
│   [====================        ] 65%      │
│                                             │
│   Ini biasanya butuh beberapa detik.       │
└───────────────────────────────────────────┘
```
This screen polls `GET /user-programs/{id}/generate/status` (see [05-API-Specification.md](../docs/05-API-Specification.md) §16) and transitions to Dashboard on completion — never a hard blocking request.

## Behavior Notes

- Each answer auto-saves on "Lanjut" (`POST /onboarding/sessions/{id}/answers`) — closing the app mid-wizard resumes at `current_step` on return (FR-ONB-03).
- Optional questions show a "Lewati" (skip) link; required questions do not.
- Disease/medication/allergy steps use repeatable rows ("+ Tambah lagi") since these are variable-length lists, not single answers.
