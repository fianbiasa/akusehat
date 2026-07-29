# UI/UX Wireframe Overview

| | |
|---|---|
| Document | 09-UI-UX-Wireframe.md |
| Detail wireframes | [wireframe/onboarding.md](../wireframe/onboarding.md) · [wireframe/dashboard.md](../wireframe/dashboard.md) · [wireframe/progress.md](../wireframe/progress.md) · [wireframe/settings.md](../wireframe/settings.md) · [wireframe/coach.md](../wireframe/coach.md) · [wireframe/admin.md](../wireframe/admin.md) |

## 1. Design System

- **Stack**: TailwindCSS + ShadCN/ui components, React via Inertia.
- **Tone**: warm, encouraging, "coach" not "app" — copy avoids clinical/cold phrasing (e.g. "Kamu turun 0.6kg minggu ini — mantap!" not "Weight delta: -0.6kg").
- **Color semantics**: green = on-track/improving, amber = attention needed, red = concern flagged (reserved for genuinely actionable alerts, not decoration).
- **Density**: mobile-first responsive; the app is used daily in short sessions (check-in takes < 30 seconds), so primary actions must be reachable without scrolling on a phone viewport.

## 2. Global Navigation

### Member
```
┌─────────────────────────────────────────────┐
│ 🏠 Dashboard  📈 Progress  💬 Coach  ⚙️ Settings │
└─────────────────────────────────────────────┘
```

### Coach
```
┌───────────────────────────────────────────────────┐
│ 🏠 Dashboard  👥 Members  💬 Chat  ⭐ Reviews  ⚙️ Settings │
└───────────────────────────────────────────────────┘
```

### Admin
```
┌────────────────────────────────────────────────────────────────┐
│ 📊 Analytics  👤 Users  🤖 AI Config  📐 Rule Engine  📚 Knowledge Base  💳 Plans │
└────────────────────────────────────────────────────────────────┘
```

## 3. Core Flow Map

```
Register ──▶ Onboarding Wizard (~55 Qs) ──▶ "Generating your program..." (async status) ──▶ Dashboard
                                                                                                 │
                                    ┌────────────────────────────────────────────────────────────┘
                                    ▼
                        Daily loop: Dashboard ──▶ Check-in ──▶ Checklist updates
                                    │
                                    ▼
                        Weekly: Weekly Review card appears on Dashboard ──▶ Detail view
                                    │
                                    ▼
                        If flagged: Coach sees alert on their Member list ──▶ Reviews/overrides
```

## 4. Page Inventory

| Page | Primary users | Detail |
|---|---|---|
| Onboarding Wizard | Member | [wireframe/onboarding.md](../wireframe/onboarding.md) |
| Dashboard (today view, health score, checklist, weekly review) | Member | [wireframe/dashboard.md](../wireframe/dashboard.md) |
| Progress (charts: weight/waist/health score, photo timeline) | Member, Coach (read) | [wireframe/progress.md](../wireframe/progress.md) |
| Settings (AI Provider, profile, subscription) | Member | [wireframe/settings.md](../wireframe/settings.md) |
| Coach Dashboard & Member Detail | Coach | [wireframe/coach.md](../wireframe/coach.md) |
| Admin Panel (Users, AI Config, Rule Engine, Knowledge Base, Analytics) | Admin | [wireframe/admin.md](../wireframe/admin.md) |

## 5. Interaction Principles

1. **Never block on AI.** Any screen waiting on an AI-generated result shows a skeleton/progress state with a clear "this can take up to ~20s" expectation, never a frozen spinner with no explanation.
2. **AI output always renders through app components**, never as injected raw text/HTML — meal items, workout items, and recommendations are structured cards built from the JSON fields, so visual consistency and localization work regardless of which AI provider answered.
3. **Every AI-suggested change is visible before/after.** When an `ai_recommendations` row is applied automatically, the Dashboard shows a small "Program updated" note with a diff-style explanation (old vs. new), not a silent change.
4. **Coach override is always visible to the Member** (transparency), except private `coach_notes` which are explicitly marked internal.

## 6. AI Settings Screen (referenced from Architecture/AI Provider docs)

See [06-AI-Provider-Interface.md](06-AI-Provider-Interface.md) §8 for the full mockup — reproduced in [wireframe/settings.md](../wireframe/settings.md).
