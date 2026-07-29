# Prompt Engineering Guide

| | |
|---|---|
| Document | 07-Prompt-Engineering.md |
| Related | [06-AI-Provider-Interface](06-AI-Provider-Interface.md) · [08-Knowledge-Base](08-Knowledge-Base.md) · [prompts/](../prompts/) (raw template files) |

## 1. Principle: Prompts Are Built, Not Typed

No end user ever types a prompt that goes straight to an AI provider. Every AI call is assembled by `PromptBuilderService` from:

1. An `ai_prompt_templates` row (the **template**, with `{{ variable }}` placeholders)
2. A **variable resolution step** that pulls structured data from the database (profile, Rule Engine output, Knowledge Base facts, AI Memory)
3. A **fixed JSON-output instruction block**, appended to every template, that is never user-editable

```
PromptBuilderService::build(string $templateKey, User $user, array $extra = []): string

  1. $template = AiPromptTemplate::where('key', $templateKey)->active()->first();
  2. $variables = $this->resolveVariables($template->variables, $user, $extra);
     // e.g. resolves "rule_engine_output" by calling RuleEngineService::evaluate($user)
     // e.g. resolves "kb_context" by querying kb_foods/kb_exercises filtered by restrictions
     // e.g. resolves "ai_memory_context" by fetching top-N ai_memories by relevance_score
  3. $filled = strtr($template->template, $variables); // {{var}} substitution
  4. return $filled . self::JSON_OUTPUT_INSTRUCTION_BLOCK;
```

## 2. The Fixed JSON-Output Instruction Block

Appended verbatim to every prompt, regardless of template or provider:

```
IMPORTANT OUTPUT RULES:
- Respond with ONLY valid JSON. No markdown, no code fences, no commentary before or after.
- The JSON MUST conform exactly to the schema provided below.
- Do not invent facts about foods, exercises, or medical conditions that are not present in the
  KNOWLEDGE_BASE_CONTEXT block. If information is insufficient, use your general nutrition/
  fitness knowledge conservatively and note the assumption in the relevant "reason"/"notes" field.
- All numeric fields must be numbers, not strings.
- Language of user-facing text fields (summary, motivation, explanation, reply): {{user_locale}}

SCHEMA:
{{response_schema_json}}
```

This is what makes the "AI must answer JSON, not markdown" requirement enforceable rather than aspirational — it's baked into every template, not left to per-feature prompt authors to remember.

## 3. Variable Categories

| Variable | Resolved from | Example |
|---|---|---|
| `user_profile` | `health_profiles`, `lifestyle_profiles`, `user_diseases`, `user_allergies` | age, gender, height/weight, BMI/BMR/TDEE, activity level, diseases, allergies |
| `rule_engine_output` | `RuleEngineService::evaluate()` against `rule_engine_rules` | calorie_target, macro_split, workout_level, water_target_ml, restrictions[] |
| `kb_context` | `kb_foods`/`kb_exercises`/`kb_diseases` filtered by `restrictions` | candidate foods/exercises the AI is allowed to choose from/vary |
| `ai_memory_context` | Top-N `ai_memories` by `relevance_score` for the user/program | "Weight stagnant 20 days", "Checklist adherence 85% this week" |
| `progress_snapshot` | Recent `weight_logs`/`checklist_items`/`health_scores` | current weight, % change, streak |
| `program_goal` | `program_goals` | target weight, target date |
| `user_locale` | `users.locale` | `id` or `en` — controls output language |
| `response_schema_json` | `ai_prompt_templates.response_schema` | injected verbatim so the model sees its own contract |

## 4. Worked Example (matches the product discussion's original illustration)

**Structured input:**
```json
{
  "user_profile": { "age": 39, "height_cm": 167, "weight_kg": 77.5, "disease": ["tukak_lambung"], "activity_level": "light" },
  "program_goal": { "target_weight_kg": 68 },
  "progress_snapshot": { "weight_change_7d_kg": -0.6, "checklist_completion_pct": 85 }
}
```

**Assembled prompt (abridged — see [prompts/weekly-review.txt](../prompts/weekly-review.txt) for the full template):**
```
You are a certified nutrition coach embedded in a health-coaching application.
Analyze the member's weekly progress below and produce next week's adjustments.

USER_PROFILE: {age: 39, height_cm: 167, weight_kg: 77.5, disease: ["tukak_lambung"], activity_level: "light"}
RULE_ENGINE_OUTPUT: {calorie_target: 1800, macro_split: {...}, restrictions: ["low_acid","small_frequent_meals"]}
PROGRAM_GOAL: {target_weight_kg: 68}
PROGRESS_SNAPSHOT: {weight_change_7d_kg: -0.6, checklist_completion_pct: 85}

Return ONLY valid JSON matching SCHEMA. ...
```

**AI's returned JSON (not markdown, not prose):**
```json
{
  "summary": "Progress steady, on track toward goal.",
  "trend": "improving",
  "adjustments": [
    { "type": "habit", "detail": "Increase daily walking to 10,000 steps", "auto_applicable": true },
    { "type": "meal_adjustment", "detail": "Reduce dinner rice portion to 1/2 serving", "auto_applicable": true }
  ],
  "motivation": "Turun 0.6kg minggu ini — konsistensi kamu terbukti. Lanjutkan!"
}
```

The Laravel backend — not the AI — renders this into the Weekly Review UI card, and `RecommendationApplierService` decides whether each `adjustment` becomes an automatically-applied `ai_recommendations` row or a Coach-approval-pending one, based on Rule Engine bounds (e.g. a calorie cut beyond X% always requires Coach approval regardless of what the AI marked `auto_applicable`).

## 5. Template Catalog

| `ai_prompt_templates.key` | File | Used by capability | Trigger |
|---|---|---|---|
| `onboarding_analysis` | [prompts/onboarding.txt](../prompts/onboarding.txt) | `analyze()` | End of onboarding wizard |
| `meal_plan` | [prompts/meal-plan.txt](../prompts/meal-plan.txt) | `generatePlan()` (meal portion) / `mealSuggestion()` | Program generation, alternative-meal request |
| `workout_plan` | [prompts/workout.txt](../prompts/workout.txt) | `generatePlan()` (workout portion) / `workoutSuggestion()` | Program generation, alternative-workout request |
| `weekly_review` | [prompts/weekly-review.txt](../prompts/weekly-review.txt) | `weeklyReview()` | Weekly scheduled job |
| `daily_chat` | [prompts/daily-chat.txt](../prompts/daily-chat.txt) | `chat()` | Member ↔ AI assistant conversation turn |
| `coach_review` | [prompts/coach-review.txt](../prompts/coach-review.txt) | `analyze()` (Coach-facing variant) | Coach opens a member's AI-flagged concern |

Each file in `prompts/` is the literal seed value for `ai_prompt_templates.template` — load them via a database seeder (`AiPromptTemplateSeeder`), not hardcoded in application code, so Admins can edit them post-launch through `/admin/ai/prompt-templates`.

## 6. Prompt Versioning

Editing a template through the Admin UI increments `ai_prompt_templates.version`. Historic `ai_request_logs.request_payload` stores the fully-resolved prompt actually sent (not just the template key), so past AI outputs remain explainable even after a template changes — this matters for support/debugging ("why did the AI recommend X on this date") and is required for the audit trail referenced in PRD §13.

## 7. Guardrails Checklist (apply to every new template)

- [ ] Ends with the fixed JSON-output instruction block (§2) — never remove or let Admins edit this part
- [ ] Includes `rule_engine_output` so the AI reasons from computed ground truth, not raw user answers
- [ ] Includes only KB facts relevant to the user's actual restrictions (don't dump the entire food/exercise DB into context — token cost and irrelevant-suggestion risk)
- [ ] Explicit language instruction via `{{user_locale}}`
- [ ] `response_schema` registered and covers every field the template's `{{...}}` promises to produce
- [ ] Reviewed against disease-specific contraindications where applicable (a meal-plan prompt for a user with `diabetes` must include the glycemic-index constraint in `rule_engine_output.restrictions`)
