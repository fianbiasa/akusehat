# Database Dictionary
## AI Personal Health Coach — Field-Level Reference

| | |
|---|---|
| Document | 03-Database-Dictionary.md |
| Source of truth | [database-schema/mysql.sql](../database-schema/mysql.sql) |
| Diagrams | [02-ERD.md](02-ERD.md) |

Conventions used throughout: all tables use `BIGINT UNSIGNED` auto-increment primary keys named `id` unless noted; `created_at`/`updated_at` follow Laravel's standard timestamp convention and are omitted from the "notes" column below unless they carry special meaning; `JSON` columns list their expected shape inline since MySQL JSON has no sub-schema of its own.

---

## Module 01 — Core / Auth / RBAC

### `roles`
| Column | Type | Null | Notes |
|---|---|---|---|
| id | bigint PK | | |
| name | varchar(50) | | Machine key: `admin`, `coach`, `member` |
| label | varchar(100) | | Display name |
| description | varchar(255) | Y | |

### `permissions`
| Column | Type | Null | Notes |
|---|---|---|---|
| id | bigint PK | | |
| name | varchar(100) | | Dot notation, e.g. `program.review`, `member.view` |
| module | varchar(50) | | Groups permissions for Admin UI display |
| description | varchar(255) | Y | |

### `role_permissions`
Pivot. Composite PK `(role_id, permission_id)`.

### `users`
| Column | Type | Null | Notes |
|---|---|---|---|
| id | bigint PK | | |
| role_id | bigint FK→roles.id | | |
| name | varchar(150) | | |
| email | varchar(150) | | Unique |
| phone | varchar(30) | Y | |
| password | varchar(255) | | bcrypt hash |
| avatar_path | varchar(255) | Y | Storage disk path |
| timezone | varchar(50) | | Default `Asia/Jakarta`; drives reminder scheduling |
| locale | varchar(10) | | Default `id` |
| status | enum | | `active`, `suspended`, `pending` |
| email_verified_at | timestamp | Y | |
| onboarding_completed_at | timestamp | Y | Set by `GenerateInitialProgram` job trigger (FR-ONB-04) |
| last_login_at | timestamp | Y | |
| deleted_at | timestamp | Y | Soft delete — required for data export/right-to-delete compliance (PRD §13) |

> Standard Laravel/Sanctum tables (`password_reset_tokens`, `sessions`, `personal_access_tokens`, `cache`, `jobs`, `failed_jobs`, `job_batches`, `notifications`) are provisioned by their stock framework migrations and are not redefined here. See [04-Architecture.md](04-Architecture.md) §9.

---

## Module 02 — AI Provider Layer

### `ai_providers`
| Column | Type | Null | Notes |
|---|---|---|---|
| id | bigint PK | | |
| name | varchar(50) | | Display name, e.g. "Claude" |
| slug | varchar(50) | | Unique, e.g. `claude`, `openai`, `groq`, `gemini`, `ollama`, `lm-studio` |
| type | enum | | `cloud` or `local` — local providers (Ollama/LM Studio) skip API-key encryption and billing metering |
| base_url | varchar(255) | Y | Override endpoint, required for local/self-hosted |
| driver_class | varchar(150) | | Fully-qualified class implementing `AIProviderInterface`, e.g. `App\Services\AI\Providers\ClaudeProvider` |
| is_active | boolean | | Admin kill-switch |

### `ai_models`
| Column | Type | Null | Notes |
|---|---|---|---|
| id | bigint PK | | |
| provider_id | bigint FK→ai_providers.id | | |
| name | varchar(100) | | Display name, e.g. "Claude Sonnet" |
| model_key | varchar(100) | | Provider's API model identifier |
| context_length | int | Y | Tokens |
| supports_json_mode | boolean | | Gates whether `AIResponseProcessor` can request native JSON mode vs. prompt-enforced JSON |
| input_cost_per_1k / output_cost_per_1k | decimal(10,6) | Y | Used to compute `ai_request_logs.estimated_cost` |
| is_active | boolean | | |

### `user_ai_settings`
| Column | Type | Null | Notes |
|---|---|---|---|
| id | bigint PK | | |
| user_id | bigint FK→users.id | | |
| provider_id | bigint FK→ai_providers.id | | |
| model_id | bigint FK→ai_models.id | | |
| api_key_encrypted | text | Y | `Crypt::encryptString()`; NULL for local providers |
| temperature | decimal(3,2) | | Default 0.70 |
| is_default | boolean | | One default per user (enforced via unique key `(user_id, is_default)` combined with app-level "unset others" logic) |

### `ai_prompt_templates`
| Column | Type | Null | Notes |
|---|---|---|---|
| id | bigint PK | | |
| key | varchar(100) | | Unique. Matches capability name, e.g. `onboarding_analysis`, `meal_plan`, `workout_plan`, `weekly_review`, `daily_motivation`, `coach_review`, `daily_chat` |
| purpose | varchar(255) | | Human-readable description for Admin UI |
| template | longtext | | Contains `{{ variable }}` placeholders resolved by `PromptBuilderService` |
| variables | json | | Documents expected variable names/types for the Admin template editor, e.g. `["user_profile","rule_engine_output","ai_memory_context"]` |
| response_schema | json | | JSON Schema the AI's response is validated against before persistence |
| version | int | | Incremented on edit; historic `ai_request_logs` reference the version active at call time via `request_payload` |
| is_active | boolean | | |

### `rule_engine_rules`
| Column | Type | Null | Notes |
|---|---|---|---|
| id | bigint PK | | |
| category | varchar(50) | | `calorie_target`, `macro_split`, `workout_level`, `water_target`, `disease_restriction`, etc. |
| name | varchar(150) | | |
| condition | json | | e.g. `{"bmi": {">=": 27}}` or compound `{"and": [...]}` |
| action | json | | e.g. `{"calorie_deficit_pct": 20, "workout_level": "beginner"}` |
| priority | int | | Higher wins when multiple rules in the same category match; see [08-Knowledge-Base.md](08-Knowledge-Base.md) §4 for the conflict-resolution algorithm |
| is_active | boolean | | |

### `ai_memories`
| Column | Type | Null | Notes |
|---|---|---|---|
| id | bigint PK | | |
| user_id | bigint FK→users.id | | |
| user_program_id | bigint FK→user_programs.id | Y | NULL for user-level (not program-specific) memories |
| memory_type | enum | | `trend`, `pattern`, `milestone`, `concern` |
| summary | varchar(500) | | Human-readable one-liner, e.g. "Weight stagnant 20 days" |
| data | json | | Structured evidence, e.g. `{"metric":"weight_kg","values":[...],"days_stagnant":20}` |
| relevance_score | decimal(4,2) | | Default 1.00, decays over time (FR-MEM-04); pruned from prompt context below a threshold, never hard-deleted |

### `ai_recommendations`
| Column | Type | Null | Notes |
|---|---|---|---|
| id | bigint PK | | |
| user_id | bigint FK→users.id | | |
| user_program_id | bigint FK→user_programs.id | Y | |
| type | enum | | `meal_adjustment`, `workout_adjustment`, `habit`, `motivation`, `alert` |
| content | json | | Raw structured AI output for this recommendation |
| rationale | text | Y | Why the AI/rule engine suggested it |
| status | enum | | `pending`, `applied`, `rejected`, `expired` — auto-`applied` only when within Rule-Engine-defined bounds (FR-PROG-04), else `pending` for Coach approval |
| applied_at | timestamp | Y | |
| reviewed_by | bigint FK→users.id | Y | Coach who approved/rejected |

### `ai_request_logs`
| Column | Type | Null | Notes |
|---|---|---|---|
| id | bigint PK | | |
| user_id | bigint FK→users.id | Y | NULL for system-triggered background calls not tied to a specific user session |
| provider_id, model_id | bigint FK | | |
| purpose | varchar(100) | | Matches `ai_prompt_templates.key` |
| request_payload / response_payload | json | Y | Full audit trail; consider truncation/redaction policy for PII at scale |
| prompt_tokens, completion_tokens | int | Y | |
| estimated_cost | decimal(10,6) | Y | Computed from `ai_models` cost fields |
| latency_ms | int | Y | |
| status | enum | | `success`, `error`, `timeout`, `invalid_json` |
| error_message | text | Y | |

---

## Module 03 — Knowledge Base

### `kb_diseases`
| Column | Type | Null | Notes |
|---|---|---|---|
| id | bigint PK | | |
| name | varchar(100) | | e.g. "Diabetes Melitus Tipe 2" |
| slug | varchar(100) | | Unique, e.g. `diabetes-tipe-2` |
| category | varchar(50) | Y | `metabolic`, `cardiovascular`, `digestive` |
| description | text | Y | |
| dietary_restrictions | json | Y | e.g. `["low_sugar","low_glycemic_index"]` |
| recommended_exercise | json | Y | e.g. `["walking","swimming"]` |
| contraindicated_exercise | json | Y | e.g. `["heavy_lifting"]` |
| reference_source | varchar(255) | Y | Citation for medical review |

### `kb_foods`
| Column | Type | Null | Notes |
|---|---|---|---|
| id | bigint PK | | |
| name / name_local | varchar(150) | Y (local) | e.g. "Rice" / "Nasi Putih" |
| category | varchar(50) | Y | `staple`, `protein`, `vegetable`, `snack`, `drink` |
| serving_unit, serving_size | varchar/decimal | | Default 100g |
| calories, protein_g, carbs_g, fat_g, fiber_g, sodium_mg | decimal | | Per serving_size |
| glycemic_index | int | Y | Used for diabetes rule filtering |
| tags | json | Y | e.g. `["halal","vegetarian","low_purine"]` — `low_purine` used for gout (asam urat) filtering |
| source | varchar(150) | Y | e.g. "TKPI" (Tabel Komposisi Pangan Indonesia) |

### `kb_exercises`
| Column | Type | Null | Notes |
|---|---|---|---|
| id | bigint PK | | |
| name | varchar(150) | | |
| category | varchar(50) | Y | `cardio`, `strength`, `flexibility`, `sport` |
| target_muscle | varchar(100) | Y | |
| met_value | decimal(5,2) | Y | Metabolic Equivalent of Task, used to estimate calories burned = `met_value × weight_kg × duration_hours` |
| difficulty | enum | | `beginner`, `intermediate`, `advanced` |
| equipment | varchar(150) | Y | |
| instructions | text | Y | |
| video_url | varchar(255) | Y | |
| contraindications | json | Y | Array of `kb_diseases.slug` this exercise should be avoided for |

### `kb_nutrition_articles`
Editorial content used as AI grounding context (RAG-lite). `slug` unique, `tags` json, `is_published` boolean gate.

### `kb_faqs`
`question`, `answer` (text), `category`, `order` (display sort), `is_published`.

---

## Module 04 — Health Profile

### `health_profiles`
| Column | Type | Null | Notes |
|---|---|---|---|
| id | bigint PK | | |
| user_id | bigint FK→users.id | | Unique — one profile per user |
| date_of_birth | date | Y | |
| gender | enum | Y | `male`, `female` — drives BMR formula constant |
| height_cm | decimal(5,2) | Y | |
| initial_weight_kg | decimal(5,2) | Y | Captured once at onboarding; current weight lives in `weight_logs` |
| blood_type | varchar(5) | Y | |
| bmi, bmr, tdee | decimal | Y | Derived fields, recalculated by Rule Engine whenever `weight_logs` gets a new entry or `lifestyle_profiles.activity_level` changes |

### `lifestyle_profiles`
One row per user (unique `user_id`). Fields capture onboarding lifestyle answers: `activity_level` (enum sedentary/light/moderate/heavy — feeds TDEE activity multiplier), sleep/wake time, `diet_pattern`, `sugary_drinks_frequency`, `smoking_status`, `alcohol_frequency`, `exercise_frequency`.

### `user_diseases`
Many-per-user. `kb_disease_id` FK, `diagnosed_at`, `status` (`active`/`managed`/`resolved`), `notes`.

### `user_allergies`
Many-per-user. Freeform `allergen` (not FK'd to a master list — allergens are too varied to enumerate exhaustively; Admin can later promote common ones into a KB table if needed), `severity` (`mild`/`moderate`/`severe`).

### `user_medications`
Many-per-user. `name`, `dosage`, `frequency`, `started_at`, `is_active`.

### `body_measurements`
Many-per-user, one row per `measured_at` date (unique `(user_id, measured_at)`). Full-body snapshot: weight, waist, chest, hip, arm, thigh, body_fat_pct. Distinct from the single-metric `weight_logs`/`waist_logs`/`body_fat_logs` tables in Module 07 — `body_measurements` is the full manual measurement entry (e.g. monthly), the Module 07 logs are the lightweight daily-tracking entries used for streaks/charts. Both are populated; `body_measurements` is the richer source when present.

---

## Module 05 — Onboarding

### `onboarding_questions`
| Column | Type | Null | Notes |
|---|---|---|---|
| id | bigint PK | | |
| step | int | | Wizard screen number (1 question can map to 1 step, or a step can bundle related micro-questions at the frontend's discretion) |
| category | varchar(50) | | `identity`, `body`, `goal`, `lifestyle`, `medical` |
| question_text | varchar(255) | | |
| input_type | enum | | `text`, `number`, `date`, `single_choice`, `multi_choice`, `time`, `scale` |
| options | json | Y | For choice types: `[{"value":"light","label":"Ringan"}, ...]` |
| validation_rules | json | Y | Laravel validation rule strings, e.g. `["required","numeric","min:30","max:300"]` |
| is_required | boolean | | |
| order | int | | Display order (independent of `step` to allow reordering without renumbering steps) |
| is_active | boolean | | Admin can retire a question without deleting historic answers |

### `onboarding_sessions`
One active/completed session per onboarding attempt. `status` (`in_progress`/`completed`/`abandoned`), `current_step` for resume.

### `onboarding_answers`
`onboarding_session_id` + `question_id` unique pair. `answer_value` json (shape depends on `input_type`: scalar for text/number/date/time, array for multi_choice).

---

## Module 06 — Program

### `programs`
Master catalog of program templates. `name`, `slug` (unique), `category` (`diet`, `bulking`, `cardio`, `disease_management`, `prenatal`, `senior`), `description`, `default_duration_days` (default 90), `is_active`.

### `user_programs`
| Column | Type | Null | Notes |
|---|---|---|---|
| id | bigint PK | | |
| user_id | bigint FK→users.id | | |
| program_id | bigint FK→programs.id | | |
| coach_id | bigint FK→users.id | Y | Assigned coach for this specific program run |
| status | enum | | `active`, `paused`, `completed`, `cancelled` |
| start_date, end_date | date | end nullable | |
| created_by | enum | | `user`, `coach`, `ai` — who initiated this program |

A user may have many rows here concurrently (FR-PROG-01) — no uniqueness constraint on `user_id`.

### `program_goals`
Per `user_program`. `goal_type` (`weight_loss`/`weight_gain`/`maintenance`/`endurance`), `target_weight_kg`, `target_waist_cm`, `target_date`, `notes`.

### `weekly_plans`
Unique `(user_program_id, week_number)`. `ai_summary` (text), `ai_review` (raw JSON response from the `weeklyReview()` AI capability), `generated_by` (`rule_engine`/`ai`).

### `daily_tasks`
Generic per-day task/habit item. `task_type` (`meal`,`workout`,`water`,`sleep`,`habit`,`checkin`), `is_completed`, `source` (`rule_engine`/`ai`/`coach`) — distinguishes system-generated vs. AI-personalized vs. coach-assigned tasks for analytics.

### `meal_plans` / `meal_plan_items`
`meal_plans` is the per-date-per-meal_type header (`breakfast`/`lunch`/`dinner`/`snack`) with rolled-up totals. `meal_plan_items` is the line-item breakdown, each optionally referencing `kb_foods` (nullable — AI may suggest a food item not yet catalogued, using `custom_name` instead; see curation loop in [08-Knowledge-Base.md](08-Knowledge-Base.md) §6).

### `workout_plans` / `workout_plan_items`
Same header/line-item pattern as meal plans, referencing `kb_exercises`.

### `checklist_items`
Simple per-day checkbox habit list distinct from `daily_tasks` — `checklist_items` is what renders in the "Daily Checklist" UI widget (FR-PROG-06); `daily_tasks` is the richer scheduling/completion record used by the Program Generator and analytics. Both are populated together by the generation pipeline.

### `reminders`
Per-user (not per-program). `type` (`water`/`meal`/`workout`/`checkin`/`medication`), `scheduled_at` (time-of-day), `recurrence_rule` (RRULE string), `last_sent_at` (dedup guard for the scheduler).

---

## Module 07 — Progress Tracking

### `weight_logs` / `waist_logs` / `body_fat_logs` / `sleep_logs`
Identical shape: `user_id`, `logged_at` (date, unique per user/day), metric value, optional `note` (weight only). These are the lightweight daily entries that power streaks, charts, and AI Memory trend detection (FR-MEM-01).

### `progress_photos`
`angle` (`front`/`side`/`back`), `photo_path`, `is_private` (default true — never shown to Coach/other users without explicit share action at the application layer).

### `water_intake_logs`
Multiple entries per day allowed (no unique constraint — each glass/bottle logged is summed for the day), `amount_ml`.

### `health_scores`
Unique `(user_id, scored_at)`. `score` (0–100), `breakdown` (json weights, see [08-Knowledge-Base.md](08-Knowledge-Base.md) §5 for the formula), `explanation` (AI-generated narrative via the `analyze()` capability).

---

## Module 08 — Coach

### `coach_profiles`
One-to-one with `users` (only populated for role=coach). `specialization`, `certification`, `max_members` (default 50 — caseload cap referenced in PRD success metric), `rating_avg` (denormalized from `reviews`, recalculated on new review).

### `coach_members`
Assignment table. Unique-ish `(coach_id, member_id, status)` — a member can be reassigned to a new coach by ending the old row (`status=ended`) and creating a new `active` row, preserving history.

### `coach_notes`
Private by default (`is_visible_to_member=0`); Coach can flag a note visible to the Member (e.g., a personalized tip they want surfaced in the Member's feed).

### `conversations` / `messages`
`conversations.type`: `coach_member` (has `coach_id`) or `ai_assistant` (no `coach_id`, `user_id` chats with the AI directly). `messages.sender_type`: `user`, `coach`, `ai`, `system`. `messages.meta` json carries attachments, AI provider/model used, token counts for `ai` sender_type messages.

### `reviews`
One review per `(coach_id, member_id)` pair (unique constraint) — a member updates their existing review rather than creating duplicates.

---

## Module 09 — Analytics / Gamification

### `achievements`
`criteria` json, e.g. `{"streak_days": 30}` or `{"weight_loss_kg": 5}` — evaluated by a scheduled job comparing against the user's logs.

### `user_achievements`
Unique `(user_id, achievement_id)` — earned once.

### `activity_logs`
Generic audit trail: `action` (e.g. `program.created`, `plan.overridden`), polymorphic `subject_type`/`subject_id`, `meta` json, `ip_address`. Supports compliance audit requirements (PRD §13) and debugging.

---

## Module 10 — Subscription / Billing (SaaS-ready scaffolding)

### `plans`
`price`, `billing_cycle` (`monthly`/`yearly`/`lifetime`), `features` json, `max_programs` (concurrent program cap per plan tier), `has_coach_access` (gates whether Coach assignment is available on this plan).

### `subscriptions`
`status` (`trialing`/`active`/`past_due`/`cancelled`/`expired`), `starts_at`/`ends_at`/`cancelled_at`.

### `payments`
`provider` (`stripe`/`xendit`/`midtrans`), `provider_reference` (external transaction ID), `amount`/`currency` (default IDR), `status` (`pending`/`paid`/`failed`/`refunded`).

> v1 ships this schema and gating logic; live payment gateway integration is scoped for v1.1 (see [10-Roadmap.md](10-Roadmap.md)).

---

## Module 11 — App Settings

### `app_settings`
Generic key-value store for platform-wide configuration not worth a dedicated table (e.g. default AI provider fallback order, maintenance mode flag). `key` unique, `value` json.

---

## Appendix: Standard Framework Tables (not redefined in this dictionary)

These ship via Laravel's own migrations and are used as-is:

| Table | Purpose |
|---|---|
| `password_reset_tokens` | Laravel's password reset flow |
| `sessions` | Session driver storage (if using database session driver) |
| `personal_access_tokens` | Laravel Sanctum API tokens |
| `cache`, `cache_locks` | Database cache driver (if used instead of Redis) |
| `jobs`, `job_batches`, `failed_jobs` | Laravel Queue |
| `notifications` | Laravel's polymorphic notifications table, used for in-app notification delivery (distinct from the domain `reminders` table, which is the scheduling source that dispatches these notifications) |
