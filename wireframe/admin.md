# Wireframe — Admin Panel

Related: [docs/09-UI-UX-Wireframe.md](../docs/09-UI-UX-Wireframe.md) · FR-ADMIN in [01-PRD.md](../docs/01-PRD.md) §8.12

## Analytics Landing

```
┌───────────────────────────────────────────────────────┐
│  Analytics                                              │
│  ┌───────────┐ ┌───────────┐ ┌───────────┐ ┌─────────┐│
│  │ Active     │ │ Program    │ │ Avg Health │ │ AI Cost ││
│  │ Users      │ │ Completion │ │ Score      │ │ (30d)   ││
│  │ 1,204      │ │ 71%        │ │ 74.2       │ │ $312.40 ││
│  └───────────┘ └───────────┘ └───────────┘ └─────────┘│
│                                                         │
│  AI Cost by Provider                                     │
│  ┌─────────────────────────────────────────────────┐  │
│  │  OpenAI  ███████████████ 62%                       │  │
│  │  Claude  ██████ 24%                                │  │
│  │  Groq    ███ 14%                                   │  │
│  └─────────────────────────────────────────────────┘  │
└───────────────────────────────────────────────────────┘
```

## AI Provider Config

```
┌───────────────────────────────────────────────────────┐
│  AI Providers                              [+ Tambah]  │
│  ┌─────────────────────────────────────────────────┐  │
│  │ OpenAI    cloud   ● Aktif   3 model    [Kelola]    │  │
│  │ Claude    cloud   ● Aktif   2 model    [Kelola]    │  │
│  │ Groq      cloud   ● Aktif   1 model    [Kelola]    │  │
│  │ Gemini    cloud   ○ Nonaktif 0 model   [Kelola]    │  │
│  │ Ollama    local   ● Aktif   2 model    [Kelola]    │  │
│  │ LM Studio local   ○ Nonaktif 0 model   [Kelola]    │  │
│  └─────────────────────────────────────────────────┘  │
└───────────────────────────────────────────────────────┘
```

## Rule Engine Editor

```
┌───────────────────────────────────────────────────────┐
│  Rule Engine Rules              [Filter: calorie_target▾]│
│  ┌─────────────────────────────────────────────────┐  │
│  │ "Overweight deficit"        priority 100  ● Aktif  │  │
│  │  IF bmi >= 25                                      │  │
│  │  THEN calorie_deficit_pct = 20                     │  │
│  │                                    [Edit] [Uji Coba]│  │
│  └─────────────────────────────────────────────────┘  │
│                                                         │
│  [Edit Kondisi (JSON)]        [Edit Aksi (JSON)]        │
│  ┌─────────────────┐          ┌─────────────────┐      │
│  │ { "bmi": {        │         │ { "calorie_       │      │
│  │   ">=": 25 } }     │         │   deficit_pct":20}│      │
│  └─────────────────┘          └─────────────────┘      │
└───────────────────────────────────────────────────────┘
```
"Uji Coba" (Test) opens a sample-profile form and calls `POST /admin/rule-engine/rules/{id}/test`, showing the resulting action output before saving — lets an Admin validate a rule change against realistic profiles without affecting live users.

## Knowledge Base CRUD (Foods example)

```
┌───────────────────────────────────────────────────────┐
│  Knowledge Base → Makanan                  [+ Tambah]  │
│  [Cari...]  [Kategori ▾]  [Tag ▾]                       │
│  ┌─────────────────────────────────────────────────┐  │
│  │ Nasi Putih      130 kcal   GI 73   [Edit] [Hapus]  │  │
│  │ Tempe Goreng    195 kcal   GI 15   [Edit] [Hapus]  │  │
│  │ Dada Ayam Panggang 165 kcal GI 0   [Edit] [Hapus]  │  │
│  └─────────────────────────────────────────────────┘  │
│                                                         │
│  ⚠ 12 item disarankan AI belum ada di database          │
│     (lihat laporan kurasi mingguan)          [Lihat →] │
└───────────────────────────────────────────────────────┘
```
The "belum ada di database" banner surfaces the curation loop described in [08-Knowledge-Base.md](../docs/08-Knowledge-Base.md) §6 — unmatched `custom_name` suggestions from `meal_plan_items`/`workout_plan_items`.

## Users & Roles

```
┌───────────────────────────────────────────────────────┐
│  Users                                      [+ Tambah]  │
│  [Cari...]  [Role ▾]  [Status ▾]                        │
│  ┌─────────────────────────────────────────────────┐  │
│  │ Budi S.     Member   Aktif      [Edit]             │  │
│  │ Dr. Rina    Coach    Aktif      [Edit]             │  │
│  │ admin@...   Admin    Aktif      [Edit]             │  │
│  └─────────────────────────────────────────────────┘  │
└───────────────────────────────────────────────────────┘
```
