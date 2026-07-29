# Wireframe — Member Dashboard

Related: [docs/09-UI-UX-Wireframe.md](../docs/09-UI-UX-Wireframe.md)

## Layout

```
┌───────────────────────────────────────────────────────┐
│ Halo, Budi 👋                              🔔  🧑‍⚕️ Coach │
│                                                         │
│ ┌───────────────────┐  ┌───────────────────────────┐  │
│ │   Health Score      │  │  Program: Diet & Transf.  │  │
│ │        83 / 100     │  │  Hari ke-23 dari 90        │  │
│ │   ▲ +3 dari kemarin │  │  [██████░░░░░░░░░░] 26%   │  │
│ └───────────────────┘  └───────────────────────────┘  │
│                                                         │
│  Checklist Hari Ini                                    │
│  ┌─────────────────────────────────────────────────┐  │
│  │ ☑ Sarapan: Nasi Merah + Telur Rebus               │  │
│  │ ☑ Minum air 500ml (3/8 gelas)                     │  │
│  │ ☐ Olahraga: Jalan Kaki 30 menit                   │  │
│  │ ☐ Makan Malam: Sup Ayam + Sayur                   │  │
│  └─────────────────────────────────────────────────┘  │
│                                                         │
│  Berat Badan                          [Log Berat +]    │
│  ┌─────────────────────────────────────────────────┐  │
│  │     77.5 ╲                                        │  │
│  │            ╲___                                   │  │
│  │                 ╲____ 75.9                        │  │
│  │  Hari 1      Hari 20       Hari 23                │  │
│  └─────────────────────────────────────────────────┘  │
│                                                         │
│  💡 Weekly Review (baru)                                │
│  ┌─────────────────────────────────────────────────┐  │
│  │ "Progress steady, on track toward goal."          │  │
│  │ Perubahan minggu ini:                              │  │
│  │  • Jalan kaki naik jadi 10.000 langkah/hari        │  │
│  │  • Porsi nasi malam dikurangi jadi 1/2             │  │
│  │                                     [Lihat Detail] │  │
│  └─────────────────────────────────────────────────┘  │
└───────────────────────────────────────────────────────┘
```

## Key Behaviors

- **Health Score** card links to the full trend view ([wireframe/progress.md](../wireframe/progress.md)) and its `explanation` (tap to expand, AI-generated narrative, [06-AI-Provider-Interface.md](../docs/06-AI-Provider-Interface.md) §4.3).
- **Checklist** items map 1:1 to `checklist_items` for the current date; checking one calls `PATCH /checklist-items/{id}` and optimistically updates the UI before confirming.
- **Weekly Review card** only appears when a new `weekly_plans` row exists that the user hasn't viewed; tapping "Lihat Detail" opens the full review (trend classification, all adjustments, motivation line) and marks it read.
- If the day's plan is still generating (first day, or after `regenerate`), the meal/workout sections show a skeleton state with the async-status pattern from [wireframe/onboarding.md](../wireframe/onboarding.md), never blank/broken cards.
- If a `daily_tasks`/`meal_plan`/`workout_plan` row has `source = coach`, a small "Disesuaikan oleh Coach" badge appears — transparency principle from the UI/UX overview §5.
- Multiple active programs (FR-PROG-01): a program switcher (segmented control) appears above the program card when `user_programs` has more than one `status=active` row.
