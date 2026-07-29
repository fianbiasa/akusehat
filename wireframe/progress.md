# Wireframe — Progress

Related: [docs/09-UI-UX-Wireframe.md](../docs/09-UI-UX-Wireframe.md) · endpoints in [05-API-Specification.md](../docs/05-API-Specification.md) §7

## Layout

```
┌───────────────────────────────────────────────────────┐
│  Progress                       [Minggu ▾] [Bulan] [90 Hari] │
│                                                         │
│  Health Score Trend                                     │
│  ┌─────────────────────────────────────────────────┐  │
│  │  100                                               │  │
│  │   80        ___──────●  83                        │  │
│  │   60   ___──                                       │  │
│  │    0                                               │  │
│  │     Minggu 1   Minggu 2   Minggu 3                 │  │
│  └─────────────────────────────────────────────────┘  │
│                                                         │
│  ┌───────────────────┐  ┌───────────────────────────┐  │
│  │  Berat Badan        │  │  Lingkar Pinggang          │  │
│  │  77.5 → 75.9 kg     │  │  92 → 89 cm                │  │
│  │  [mini chart]       │  │  [mini chart]              │  │
│  └───────────────────┘  └───────────────────────────┘  │
│                                                         │
│  ┌───────────────────┐  ┌───────────────────────────┐  │
│  │  Tidur (avg)        │  │  Air Minum (avg/hari)      │  │
│  │  6.8 jam            │  │  1,850 ml / 2,500 ml       │  │
│  └───────────────────┘  └───────────────────────────┘  │
│                                                         │
│  Foto Progress                                    [+]  │
│  ┌─────────┐ ┌─────────┐ ┌─────────┐                  │
│  │ Hari 1  │ │ Hari 30 │ │ Hari 60 │  (front/side/back  │
│  │ [img]   │ │ [img]   │ │ [img]   │   tabs per entry)  │
│  └─────────┘ └─────────┘ └─────────┘                  │
│                                                         │
│  Konsistensi Checklist                                  │
│  ┌─────────────────────────────────────────────────┐  │
│  │  M  M  S  S  R  K  J  S  M  M  S  S  R  K  J      │  │
│  │  ✓  ✓  ✓  ✗  ✓  ✓  ✓  ✓  ✓  ✓  ✗  ✓  ✓  ✓  ✓      │  │
│  └─────────────────────────────────────────────────┘  │
└───────────────────────────────────────────────────────┘
```

## Key Behaviors

- Range selector (Minggu/Bulan/90 Hari) re-queries the same time-series endpoints with different `?from=&to=` windows — no separate pages.
- Progress photos default `is_private = true`; a per-photo "Bagikan ke Coach" toggle explicitly grants Coach visibility for that entry only.
- Tapping the Health Score trend point opens that day's `explanation` text (same AI-generated narrative surfaced on the Dashboard).
- Coach viewing this same page (read-only variant, via [wireframe/coach.md](../wireframe/coach.md)) sees identical charts scoped to the selected member, minus any non-shared private photos.
