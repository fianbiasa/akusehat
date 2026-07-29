# Knowledge Base & Rule Engine Specification

| | |
|---|---|
| Document | 08-Knowledge-Base.md |
| Related | [03-Database-Dictionary](03-Database-Dictionary.md) (`kb_*`, `rule_engine_rules` tables) · [07-Prompt-Engineering](07-Prompt-Engineering.md) |

## 1. Purpose

The Knowledge Base (KB) is the factual grounding the AI is required to reason from instead of hallucinating. The Rule Engine is the deterministic decision layer that converts KB facts + user profile into concrete numeric targets. Neither depends on an AI call — both work even if every AI provider is down, which is the whole point of the hybrid architecture (PRD §7).

## 2. Knowledge Base Content Domains (v1)

| Domain | Table | v1 seed scope |
|---|---|---|
| Indonesian food composition | `kb_foods` | ~300–500 common Indonesian foods/dishes with macros, GI, tags — sourced from TKPI (Tabel Komposisi Pangan Indonesia) or equivalent public nutrition data, curated by an SME before launch |
| Exercises | `kb_exercises` | ~100–150 exercises across cardio/strength/flexibility, beginner→advanced, with MET values and disease contraindications |
| Disease reference | `kb_diseases` | Diabetes Melitus Tipe 2, Hipertensi, Kolesterol Tinggi, Asam Urat (Gout), Tukak Lambung/GERD, and a generic "none" baseline |
| Nutrition articles | `kb_nutrition_articles` | Editorial content explaining BMI/BMR/TDEE, macro basics, disease-specific eating guidance |
| FAQ | `kb_faqs` | Common onboarding/program/billing questions |

### 2.1 Example `kb_diseases` seed rows

| slug | category | dietary_restrictions | contraindicated_exercise |
|---|---|---|---|
| `diabetes-tipe-2` | metabolic | `["low_sugar","low_glycemic_index","controlled_carb"]` | `[]` |
| `hipertensi` | cardiovascular | `["low_sodium"]` | `["heavy_lifting_valsalva"]` |
| `kolesterol-tinggi` | cardiovascular | `["low_saturated_fat","high_fiber"]` | `[]` |
| `asam-urat` | metabolic | `["low_purine"]` | `["high_impact_joint_stress"]` |
| `tukak-lambung-gerd` | digestive | `["low_acid","small_frequent_meals","avoid_spicy"]` | `["inversion_exercises"]` |

### 2.2 Example `kb_foods` seed rows

| name_local | category | calories (per 100g) | protein_g | carbs_g | fat_g | glycemic_index | tags |
|---|---|---|---|---|---|---|---|
| Nasi Putih | staple | 130 | 2.7 | 28 | 0.3 | 73 | `["halal"]` |
| Nasi Merah | staple | 110 | 2.6 | 23 | 0.9 | 55 | `["halal","high_fiber"]` |
| Dada Ayam Panggang (tanpa kulit) | protein | 165 | 31 | 0 | 3.6 | 0 | `["halal","high_protein","low_purine"]` |
| Tempe Goreng | protein | 195 | 15 | 12 | 11 | 15 | `["halal","vegetarian"]` |
| Bayam Rebus | vegetable | 23 | 2.9 | 3.6 | 0.4 | 15 | `["halal","vegetarian","high_fiber"]` |
| Jeroan Sapi | protein | 224 | 20 | 0 | 15 | 0 | `["halal"]` — **excluded** for `asam-urat` restriction via `low_purine` tag absence |

### 2.3 Example `kb_exercises` seed rows

| name | category | met_value | difficulty | contraindications |
|---|---|---|---|---|
| Jalan Kaki (Brisk Walk) | cardio | 3.8 | beginner | `[]` |
| Push Up | strength | 3.8 | beginner | `[]` |
| Angkat Beban Berat (Heavy Deadlift) | strength | 6.0 | advanced | `["hipertensi"]` |
| Yoga Ringan | flexibility | 2.5 | beginner | `[]` |
| Lompat Tali (Jump Rope) | cardio | 11.0 | intermediate | `["asam-urat"]` |

## 3. Rule Engine Semantics

`rule_engine_rules.condition` and `.action` are small JSON DSL documents evaluated by `RuleEngineConditionEvaluator`. Supported condition operators: `>=`, `<=`, `>`, `<`, `==`, `in`, `and`, `or`, `not`. Supported condition fields: any dot-path into the user's evaluation context (`bmi`, `age`, `gender`, `diseases[]`, `activity_level`, etc.).

### 3.1 Example rules

**Calorie target baseline by BMI:**
```json
{
  "category": "calorie_target",
  "name": "Overweight deficit",
  "condition": { "bmi": { ">=": 25 } },
  "action": { "calorie_deficit_pct": 20, "min_calorie_floor": 1200 },
  "priority": 100
}
```

**Workout level by BMI + activity:**
```json
{
  "category": "workout_level",
  "name": "High BMI beginner cap",
  "condition": { "and": [ { "bmi": { ">=": 27 } }, { "activity_level": { "in": ["sedentary","light"] } } ] },
  "action": { "workout_level": "beginner" },
  "priority": 100
}
```

**Disease-based restriction injection:**
```json
{
  "category": "disease_restriction",
  "name": "Gout purine restriction",
  "condition": { "diseases": { "in": ["asam-urat"] } },
  "action": { "add_restriction": "low_purine", "exclude_exercise_tags": ["high_impact_joint_stress"] },
  "priority": 200
}
```

### 3.2 Conflict Resolution

Rules are grouped by `category`. Within a category, all matching rules' actions are merged; where two matching rules set the **same** action key to different values, the rule with the higher `priority` wins. `disease_restriction` rules default to a higher priority band (200+) than general demographic rules (100) so medical constraints always override generic BMI-based defaults rather than being averaged with them.

### 3.3 Output Contract

`RuleEngineService::evaluate(User $user): array` always returns:
```json
{
  "calorie_target": 1800,
  "macro_split": { "protein_pct": 30, "carbs_pct": 40, "fat_pct": 30 },
  "workout_level": "beginner",
  "water_target_ml": 2500,
  "restrictions": ["low_purine", "small_frequent_meals"]
}
```
This exact shape is what gets injected into every AI prompt as `rule_engine_output` (see [07-Prompt-Engineering.md](07-Prompt-Engineering.md) §3) and is also usable standalone (without any AI call) to generate a baseline plan — the "AI down" degraded mode.

## 4. BMI / BMR / TDEE Formulas (reference implementation)

- **BMI** = `weight_kg / (height_m ^ 2)`
- **BMR (Mifflin-St Jeor)**:
  - Male: `10 × weight_kg + 6.25 × height_cm − 5 × age + 5`
  - Female: `10 × weight_kg + 6.25 × height_cm − 5 × age − 161`
- **TDEE** = `BMR × activity_multiplier`, where `activity_multiplier`: sedentary=1.2, light=1.375, moderate=1.55, heavy=1.725

These are computed in `HealthProfileService`, not by the AI — recalculated whenever `weight_logs` gets a new row or `lifestyle_profiles.activity_level` changes (FR-HP-02).

## 5. Health Score Formula (v1)

Composite 0–100 score, computed daily by `HealthScoreService` and stored in `health_scores.breakdown`:

| Component | Weight | Basis |
|---|---|---|
| BMI proximity to healthy range | 20 | Distance from 18.5–24.9 range |
| Waist circumference | 10 | Gender-specific healthy threshold |
| Sleep | 15 | `sleep_logs` avg vs. 7–9h target |
| Water intake | 10 | `water_intake_logs` vs. `rule_engine_output.water_target_ml` |
| Activity/workout adherence | 15 | `workout_plans.is_completed` rate, last 7 days |
| Weight trend vs. goal | 15 | Direction/rate vs. `program_goals` |
| Checklist consistency | 10 | `checklist_items.is_checked` rate, last 7 days |
| Disease management adherence | 5 | Restriction-compliant meal logging where applicable |

`breakdown` stores each component's raw sub-score; `explanation` is generated by the `analyze()` AI capability, which receives `breakdown` as input and must explain — never recompute — the score (§4.3 of [06-AI-Provider-Interface.md](06-AI-Provider-Interface.md)).

## 6. Knowledge Base Curation Loop

`meal_plan_items`/`workout_plan_items` allow a nullable `kb_food_id`/`kb_exercise_id` with a `custom_name` fallback specifically so the AI can suggest an item not yet in the KB without blocking plan generation. Operational process:

1. Weekly Admin report: query `meal_plan_items WHERE kb_food_id IS NULL` / `workout_plan_items WHERE kb_exercise_id IS NULL`, grouped by `custom_name` frequency.
2. SME reviews top recurring `custom_name` values, adds them to `kb_foods`/`kb_exercises` with proper macro/MET data via the Admin CRUD ([05-API-Specification.md](05-API-Specification.md) §12).
3. Over time, the "unmatched suggestion" rate should trend toward zero as the KB converges on real usage patterns — track this as an internal KB-health metric on the Admin analytics dashboard.

## 7. Content Licensing Note

Public Indonesian nutrition composition data (TKPI) and general exercise MET tables are widely published reference data; confirm the specific source's license/attribution requirements before bulk-importing at scale, and keep `kb_foods.source` / a disease/exercise equivalent populated for traceability.
