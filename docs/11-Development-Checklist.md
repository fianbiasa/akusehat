# Development Task Breakdown
## AI Personal Health Coach — v1.0 Build Checklist

| | |
|---|---|
| Document | 11-Development-Checklist.md |
| How to use | Import into your tracker of choice (GitHub Projects, Linear, Jira) one section at a time, or work it directly in VS Code as a literal checklist. Ordered so each phase's dependencies are satisfied by the phase before it. |

---

## Phase 0 — Project Setup

- [x] `laravel new` on Laravel 12 / PHP 8.4, configure `.env` for MySQL 8 + Redis
- [x] Install & configure Inertia.js + React + TypeScript
- [x] Install TailwindCSS + ShadCN/ui, set up base theme tokens
- [x] Install Laravel Sanctum, configure SPA stateful domains
- [x] Install Laravel Horizon for queue monitoring
- [x] Set up Vite build pipeline
- [x] Configure `composer.json`/`package.json` scripts (lint, test, format)
- [ ] Set up CI pipeline (lint, static analysis via PHPStan/Larastan, PHPUnit/Pest, `npm test`, `composer audit`/`npm audit`) — workflow files exist in `.github/workflows/` but aren't pushed yet (current GitHub token lacks `workflow` scope)
- [ ] Configure error tracking (Sentry or equivalent) — no Sentry DSN provided yet
- [x] Set up local dev environment docs (README "Getting Started")
- [x] Create base folder structure for Services/Repositories/Contracts (per [04-Architecture.md](04-Architecture.md) §2)

## Phase 1 — Core / Auth / RBAC

- [x] Migration: `roles`, `permissions`, `role_permissions`, `users` (per [database-schema/mysql.sql](../database-schema/mysql.sql))
- [x] Seeder: default roles (admin/coach/member) + baseline permission set
- [x] `User`, `Role`, `Permission` Eloquent models + relationships
- [x] `PermissionMiddleware` / policy classes enforcing `role_permissions` (`EnsurePermission`, `permission:<name>` route alias)
- [x] Register/login/logout/password-reset controllers + Form Requests
- [x] Email verification flow
- [x] `GET /auth/me` endpoint (`GET /api/v1/auth/me`, Sanctum-protected)
- [x] Admin: user CRUD controller + React pages
- [x] Admin: role/permission management controller + React pages
- [x] Unit tests: permission enforcement (positive + negative cases per role)
- [x] Feature tests: registration, login, password reset

## Phase 2 — Onboarding

- [x] Migrations: `onboarding_questions`, `onboarding_sessions`, `onboarding_answers`
- [x] Seeder: ~55 questions across identity/body/goal/lifestyle/medical categories (content from [wireframe/onboarding.md](../wireframe/onboarding.md)) — landed at 55 across identity/body/lifestyle/medical/preferences/goal
- [x] `OnboardingQuestion`, `OnboardingSession`, `OnboardingAnswer` models
- [x] Wizard API: start/resume session, submit answer, complete
- [x] React: Wizard shell (progress bar, back/next, skip-if-optional)
- [x] React: input components per `input_type` (text/number/date/single_choice/multi_choice/time/scale)
- [x] React: repeatable-row components (medications, allergies) — driven by `onboarding_questions.validation_rules.repeatable`
- [x] `OnboardingCompleted` event + listener dispatching `GenerateInitialProgram` job — job is a logging stub until the Rule Engine/AI Provider layer (Phases 4-6) exists
- [ ] Admin: question bank CRUD (create/edit/reorder/deactivate) + React pages
- [x] Feature test: full wizard completion → event fired → job dispatched
- [x] Feature test: resume after partial completion

## Phase 3 — Health Profile

- [x] Migrations: `health_profiles`, `lifestyle_profiles`, `user_diseases`, `user_allergies`, `user_medications`, `body_measurements` — plus `kb_diseases` pulled forward from Phase 4 (required FK, minimal 5-row seed only; kb_foods/kb_exercises/kb_nutrition_articles/kb_faqs and their Admin CRUD stay in Phase 4)
- [x] Models + relationships
- [x] `HealthProfileService`: BMI/BMR/TDEE calculation (formulas in [08-Knowledge-Base.md](08-Knowledge-Base.md) §4)
- [x] Listener: recalculate BMI/BMR/TDEE — implemented as `BodyMeasurementObserver` (on create) and `LifestyleProfileObserver` (on `activity_level` change), since `weight_logs` doesn't exist yet (Phase 7); `body_measurements` (Phase 3-scoped) is the interim trigger
- [x] Onboarding → Health Profile mapping (`MapOnboardingAnswersToHealthProfile` listener, matched by `onboarding_questions.step` — coupled to `OnboardingQuestionSeeder`, documented inline)
- [x] API: profile/lifestyle/diseases/allergies/medications/measurements endpoints (§5 of [05-API-Specification.md](05-API-Specification.md)) — Member (own) scope only; Coach/Admin read access deferred to Phase 8 (`coach_members` doesn't exist yet)
- [x] React: Profile edit screens ([wireframe/settings.md](../wireframe/settings.md) "Profil" tab) — `/profile/health`, linked from Settings as "Kesehatan"
- [x] Unit tests: BMI/BMR/TDEE formula correctness against known reference values (verified against the worked example in [07-Prompt-Engineering.md](07-Prompt-Engineering.md) §4)

## Phase 4 — Knowledge Base & Rule Engine

- [x] Migrations: `kb_diseases` (done in Phase 3, pulled forward for `user_diseases`' FK), `kb_foods`, `kb_exercises`, `kb_nutrition_articles`, `kb_faqs`, `rule_engine_rules`
- [x] Seed: initial disease set (diabetes, hipertensi, kolesterol, asam urat, tukak lambung/GERD) — done in Phase 3
- [x] Seed: initial food set — **40 rows, a starter/demo set, not the ~300–500 SME/TKPI-sourced catalog** §7 calls for. Per PRD §6.3 that full curation is an explicit product-owner/SME task, not something to fabricate wholesale; `kb_foods.source` flags every seeded row as pending review.
- [x] Seed: initial exercise set — 28 rows, same starter/demo caveat as foods.
- [x] Seed: baseline rule set (calorie target, macro split, workout level, water target, disease restrictions) — 11 rules covering all 5 categories, including the 3 worked examples from [08-Knowledge-Base.md](08-Knowledge-Base.md) §3.1 verbatim
- [x] `RuleEngineConditionEvaluator`: implement condition DSL (`>=`,`<=`,`>`,`<`,`==`,`in`,`and`,`or`,`not`)
- [x] `RuleEngineService::evaluate(User $user): array` — priority-based conflict resolution across categories; `disease_restriction` accumulates (union) rather than overwrites, since a user can have multiple conditions at once
- [x] Admin: Rule Engine CRUD + "Uji Coba" (test-against-sample-profile) endpoint/UI
- [ ] Admin: Knowledge Base CRUD (foods/exercises/diseases/articles/FAQ) + React pages — deferred; read-only search exists, editing UI does not yet
- [x] KB search endpoints (`?q=&category=&tags[]=`)
- [x] Unit tests: rule evaluator against each example rule in [08-Knowledge-Base.md](08-Knowledge-Base.md) §3
- [x] Unit tests: conflict resolution (two rules same category, priority wins)

## Phase 5 — AI Provider Layer

- [x] Migrations: `ai_providers`, `ai_models`, `user_ai_settings`, `ai_prompt_templates`, `ai_memories`, `ai_recommendations`, `ai_request_logs` — `user_ai_settings` deliberately drops `mysql.sql`'s literal `UNIQUE(user_id, is_default)` index (MySQL has no partial/filtered unique index, so a plain 2-column unique also caps *non*-default rows at one per user, which breaks FR-AI-06 provider failover); "one default per user" is enforced app-side instead (every write path unsets others in the same transaction). `ai_memories`/`ai_recommendations.user_program_id` are nullable plain columns without an FK for now — the constraint lands in Phase 6 once `user_programs` exists.
- [x] Define `AIProviderInterface` contract (per [06-AI-Provider-Interface.md](06-AI-Provider-Interface.md) §2)
- [x] Implement `OpenAIProvider`
- [x] Implement `ClaudeProvider`
- [x] Implement `GroqProvider`
- [x] Implement `GeminiProvider`
- [x] Implement `OllamaProvider`
- [x] Implement `LMStudioProvider`
- [x] `AIProviderFactory`: resolves `driver_class` → concrete adapter instance (a factory rather than a dedicated `AIProviderServiceProvider`, since resolution needs a runtime `AiProvider`/`AiModel` row, not a static container binding)
- [x] `PromptBuilderService`: template + variable resolution + fixed JSON instruction block (the instruction block lives in the seeded `prompts/*.txt` template text itself, per [07-Prompt-Engineering.md](07-Prompt-Engineering.md))
- [x] Seed `ai_prompt_templates` from `prompts/*.txt` (onboarding, meal-plan, workout, weekly-review, daily-chat, coach-review)
- [x] `AIGatewayService`: provider resolution, call timing, logging to `ai_request_logs`, failover to secondary provider
- [x] `AIResponseProcessor`: JSON decode + schema validation + retry (≤2) + Rule-Engine fallback
- [x] Member: AI Settings CRUD (provider/model/API key/temperature) + "Test Connection" + React page ([wireframe/settings.md](../wireframe/settings.md)) — shown as a list of configured provider rows (not the wireframe's single radio group), since failover requires more than one saved provider at a time
- [x] Admin: AI Provider/Model CRUD + React pages
- [ ] Admin: Prompt Template editor (with version bump on save) + React page — deferred; templates are seed-managed only for now
- [ ] Admin: AI request log viewer + cost dashboard — deferred; `ai_request_logs` is written correctly (verified via tinker + tests) but has no viewer UI yet
- [x] Unit tests: each provider adapter against a mocked HTTP client (request shape correctness)
- [x] Unit tests: `AIResponseProcessor` retry/fallback branches
- [ ] Integration test: at least 2 real providers (1 cloud, 1 local) end-to-end against a sandbox/dev key — not possible in this environment (no real API keys/local model server available); covered instead by `Http::fake()`-based tests plus a live HTTP smoke test that hit the real OpenAI API with an invalid key and a local Ollama endpoint with nothing listening, confirming both failure paths degrade gracefully over real network calls

## Phase 6 — Program Generation

- [x] Migrations: `programs`, `user_programs`, `program_goals`, `weekly_plans`, `daily_tasks`, `meal_plans`, `meal_plan_items`, `workout_plans`, `workout_plan_items`, `checklist_items`, `reminders` — plus the stock `notifications` table (database channel, for the dashboard's 🔔 bell) and a follow-up migration adding the `ai_memories`/`ai_recommendations.user_program_id` FKs that Phase 5 deliberately deferred until this table existed. `weekly_plans` also gains a `viewed_at` column not in `mysql.sql`, needed for wireframe/dashboard.md's "only appears until viewed" behavior — additive/nullable, documented deviation.
- [x] Seed: `programs` catalog (just "Diet & Transformasi 90 Hari", per scope)
- [x] `ProgramGenerationService`: Goal → RuleEngine → PromptBuilder → AIGateway → AIResponseProcessor → persist — generates **one day at a time** (today, on program creation; any date via `/regenerate`), not a 90-day batch upfront. Reusing Phase 5's `meal_plan`/`workout_plan` templates (already scoped per-day) via the `generatePlan` capability, rather than a new combined "full plan" template — avoids 180+ AI calls per program and a much larger single-response schema-validation surface. Falls back to a deterministic Rule-Engine-only plan (KB foods/exercises picked directly, no AI) when no provider is configured or all fail, exactly like every other AI capability in this app.
- [x] `GenerateProgramJob` (queued), status-polling endpoint (`GET /user-programs/{id}/generate/status`, backed by a short-TTL cache entry, not a schema column — `user_programs` has no place reserved for ephemeral job status)
- [x] `AIMemoryService`: deterministic (not ML) trend/pattern/milestone/concern heuristics from checklist completion + weight history; `ScanAIMemoryJob` scheduled daily, also invoked incrementally by `CheckInSubmitted`. `PruneAIMemoryRelevanceJob` (weekly relevance decay) added too.
- [x] `RecommendationApplierService`: only `habit`/`motivation` adjustments ever auto-apply, even when the AI marks `meal_adjustment`/`workout_adjustment` as `auto_applicable` — the AI's adjustment `detail` is free text, not a structured delta, so there is no sound way to bounds-check a mutation to `meal_plan_items`/`workout_plan_items` against it. Structural adjustments always route to Coach approval; a fabricated text-parsing heuristic would be worse than this conservative rule. Documented in the service's docblock.
- [x] `GenerateWeeklyReviewJob` — dispatched by a daily scheduler tick that finds `weekly_plans` rows whose week just ended and haven't been reviewed yet ("per program's week boundary" is user-relative, not a shared calendar day, so there's no single fixed cron time for it)
- [x] API: program catalog, user-programs CRUD, goals, weekly-plans, daily-tasks, meal-plans (+items), workout-plans (+items), checklist, reminders (§6 of [05-API-Specification.md](05-API-Specification.md)) — plain web routes under `auth`+`onboarding.completed`, matching every prior phase's convention (no separate `/api/v1` namespace yet)
- [x] `DispatchRemindersJob` (per-minute scheduler tick, timezone-aware via `users.timezone`, `last_sent_at` as the same-day dedup guard)
- [x] React: Dashboard (today view) per [wireframe/dashboard.md](../wireframe/dashboard.md) — Health Score card omitted (Phase 7, `health_scores` doesn't exist yet); weight is shown as the latest logged value, full trend chart is also Phase 7
- [x] React: Program detail / weekly review detail views
- [x] React: multi-program switcher (segmented buttons, shown when >1 active program)
- [x] Events: `ProgramGenerated`, `CheckInSubmitted`, `AIRecommendationCreated` + listeners — the coach-notification listener checks `user_programs.coach_id` (which exists now) and safely no-ops until Phase 8 builds actual coach assignment
- [x] Feature test: full generation pipeline produces schema-valid, persisted plan (both the AI-success path with KB item matching, and the Rule-Engine-only fallback path)
- [x] Feature test: auto-apply vs. pending-approval branching for a boundary-testing recommendation
- Two pre-existing bugs (Phase 3, not Phase 6) caught by live HTTP smoke testing, not the in-process suite: (1) `HealthProfileService`'s BMI/BMR/TDEE calculation has no input sanity bounds, so an unrealistic height/weight (e.g. a typo) produces a BMI that overflows `health_profiles.bmi`'s `DECIMAL(5,2)` and crashes onboarding completion with a raw SQL error under MySQL's strict mode — invisible against the test suite's SQLite, which doesn't enforce DECIMAL range. (2) `OnboardingController::answer()` never validated that a "repeatable" question (medications/allergies) actually receives an array, so a non-array value crashed `MapOnboardingAnswersToHealthProfile::mapRepeatable()` with a `TypeError` instead of a clean 422. Both fixed with boundary validation (min/max on `onboarding_questions.validation_rules` for steps 6-10, an `array` rule for repeatable questions), each with a regression test.

## Phase 7 — Progress Tracking & Health Score

- [x] Migrations: `weight_logs`, `waist_logs`, `body_fat_logs`, `progress_photos`, `water_intake_logs`, `sleep_logs`, `health_scores` — `weight_logs`/`waist_logs` now supersede `body_measurements` (Phase 3) as the primary weight/waist source (`User::latestWeightKg()`/`latestWaistCm()` check the new tables first, falling back to `body_measurements` then `health_profiles.initial_weight_kg`), per the forward-looking comment left in Phase 3. `weekly_plans`/`ai_memories` etc. untouched.
- [x] `HealthScoreService`: weighted composite calculation (formula in [08-Knowledge-Base.md](08-Knowledge-Base.md) §5) — the doc names 8 weighted components and their "basis" but not an exact decay curve per component, so each component's scoring function is designed and documented inline in the service (e.g. BMI: 2 points lost per unit outside 18.5-24.9; waist: IDF Asian-population cutoffs, male <90cm/female <80cm; disease management: proxied via `meal_plans.is_completed` since there's no per-meal "what did you actually eat" logging in this app). 17 unit tests pin down every component against known inputs.
- [x] `ComputeHealthScoreJob` (daily scheduled)
- [x] `analyze()` capability wiring for Health Score `explanation` generation — new `health_score_explain` prompt template (`prompts/health-score-explain.txt`), explicitly instructed to explain, never recompute, the score; falls back to a deterministic "your weakest component is X" explanation when no AI provider is available.
- [x] API: weight/waist/body-fat/sleep/water/photos/health-score endpoints (§7 of [05-API-Specification.md](05-API-Specification.md)) — M/C/A access via a `?user_id=` param checked against `user_programs.coach_id` (same pattern as the Programs module), default is the caller's own data.
- [x] File upload handling for progress photos (validation, private storage, signed URLs) — stored on the `local` disk (`storage/app/private`, never web-accessible directly) regardless of `is_private`; served only via a 30-minute temporary signed route. `is_private` toggling is the "Bagikan ke Coach" mechanism from the wireframe, not a separate column.
- [x] React: Progress page (charts, photo timeline, consistency grid) per [wireframe/progress.md](../wireframe/progress.md) — the Minggu/Bulan/90-Hari range selector filters an already-loaded 90-day dataset client-side rather than issuing a fresh request per range (the dataset is small enough that a round-trip per range change would be pure overhead). Health Score card added to the Dashboard (deferred from Phase 6).
- [x] Charting library integration (time-series weight/waist/health-score trend) — added `recharts`; no shadcn chart wrapper existed yet, so charts are built directly against it.
- [x] Unit tests: Health Score formula against known component inputs
- [x] Feature test: photo privacy default + share-to-coach toggle
- Two bugs found by live HTTP smoke testing (both pre-existing, from Phase 3/6, not introduced this phase): (1) `HealthProfileService`/`AIMemoryService`/etc. all independently duplicated the same 2-3-way weight-source fallback chain — consolidated into `User::latestWeightKg()`/`latestWaistCm()` while wiring in the new tables, rather than adding a 4th copy. (2) A response array manually rebuilt from a date-cast Eloquent attribute (e.g. `['start_date' => $model->start_date]`) silently bypasses the `'date:Y-m-d'` cast format — only the model's own `toArray()`/`toJson()` respects it. Carbon's default JSON serialization (a full UTC-shifted ISO8601 timestamp) takes over instead, identical in spirit to the Phase 3 date-cast gotcha but triggered differently. Found on `ProgressPhotoController`/`ProgressPageController`/`DashboardController`; fixed by explicitly calling `->toDateString()` at every such site, each with a regression test. This bug is pure PHP/Carbon behavior (not MySQL-vs-SQLite), so it was independently confirmed reproducible under the PHPUnit suite's SQLite connection too, once the missing assertions were added — a reminder that "not caught by tests" and "untestable under SQLite" are different failure modes worth telling apart.
- 238/238 tests passing.

## Phase 8 — Coach Module

- [x] Migrations: `coach_profiles`, `coach_members`, `coach_notes`, `conversations`, `messages`, `reviews` — `coach_members`' literal `UNIQUE(coach_id, member_id, status)` (itself flagged "Unique-ish" in the Database Dictionary) is dropped; it would break re-assigning a member back to a coach they'd previously left (a second `(coach_id, member_id, 'ended')` row collides with the first). "One active assignment per member" is enforced app-side instead, same precedent as Phase 5's `user_ai_settings`.
- [x] `CoachProfile` model + onboarding flow for coach accounts (Admin-created) — `Admin\UserController::store()` auto-creates a blank `coach_profiles` row whenever the created user's role is `coach`, so every coach account always has one; the coach self-service edits their own bio/specialization/certification at `/coach/profile`.
- [x] Assignment logic: `coach_members` create/reassign (ends old row, creates new) — `CoachAssignmentService::assign()` also syncs the member's active `user_programs.coach_id` so Phase 6's `AuthorizesProgramAccess` (Program module) and this phase's own coach-scoping stay consistent. Caught a real bug here: using the `$member->activeCoachAssignment` *property* accessor (not the method-call form) caches the relation per-instance, so calling `assign()` then `unassign()` on the same `$member` object reused a stale cached `null` and silently skipped ending the row - fixed by always using `activeCoachAssignment()->first()`.
- [x] Coach dashboard aggregation query (flagged concerns via `ai_memories`/`ai_recommendations`) — scoped to `coach_members` where `coach_id=current coach` and `status=active`, per wireframe/coach.md.
- [x] `chat()` capability wiring for AI-assistant conversations; coach_member conversations are plain messaging (no AI) — reuses Phase 5's `daily_chat` template; the AI call is processed synchronously (not queued) since chat is an actively-watched conversation, not a background generation task.
- [x] Real-time messaging (broadcast via Laravel Echo/Pusher-compatible channel) or polling fallback — polling fallback implemented (no Echo/Pusher/Reverb credentials in this environment); the frontend re-fetches messages every 4s.
- [x] API: coach members/notes/recommendations approve-reject/dashboard/reviews (§8–9 of [05-API-Specification.md](05-API-Specification.md)) — reused Phase 1's already-seeded `member.view`/`program.review`/`note.manage`/`chat.send` permissions (anticipated back then, unused until now) rather than inventing a parallel role-check middleware; added one new `coach_members.manage` permission for the Admin assignment endpoint.
- [x] React: Coach Dashboard + Member Detail per [wireframe/coach.md](../wireframe/coach.md)
- [x] React: Chat UI (shared component for coach_member and ai_assistant conversation types)
- [x] React: Review/rating submission (Member-facing, on program completion or periodically) — added to the Program detail page, shown whenever that program has an assigned coach.
- [x] Feature test: recommendation approve/reject updates status + notifies Member
- [x] Feature test: private note visibility toggle
- Also extended Phase 7's Progress page to accept `?user_id=` for the Coach's read-only view (wireframe/progress.md's "Coach viewing this same page... scoped to the selected member" was written in Phase 7 but unreachable until this phase's coach-assignment mechanism existed) — non-shared private photos are filtered out for non-owners.
- 278/278 tests passing.

## Phase 9 — Admin Panel

- [ ] Analytics aggregation queries (active users, program completion, AI cost, health outcomes)
- [ ] Admin dashboard React page ([wireframe/admin.md](../wireframe/admin.md))
- [ ] Consolidate all Admin CRUD screens built in earlier phases into a coherent Admin shell/navigation
- [ ] Admin activity audit log viewer (`activity_logs`)
- [ ] Role-gated Admin route group + navigation

## Phase 10 — Achievements & Notifications

- [ ] Migrations: `achievements`, `user_achievements`, `activity_logs`
- [ ] `EvaluateAchievementsJob` (daily scheduled, criteria matching against logs)
- [ ] API: achievements catalog + earned achievements
- [ ] React: achievement badges on Dashboard/Profile
- [ ] `activity_logs` write-through in key services (program creation, plan override, recommendation approval)
- [ ] Laravel Notification channels: in-app + email (push deferred to v1.1)

## Phase 11 — Subscription Scaffolding (schema + gating, no live gateway in v1)

- [ ] Migrations: `plans`, `subscriptions`, `payments`
- [ ] Seed: default plan tiers
- [ ] `SubscriptionRenewalCheckJob` (scheduled, flags expiring)
- [ ] Plan-based gating middleware (`max_programs`, `has_coach_access`)
- [ ] API: plans catalog, subscription status, sandboxed subscribe/cancel (§14 of [05-API-Specification.md](05-API-Specification.md))
- [ ] React: Subscription tab in Settings ([wireframe/settings.md](../wireframe/settings.md))
- [ ] Admin: plans CRUD + subscriptions list

## Phase 12 — App Settings & Cross-Cutting

- [ ] Migration: `app_settings`
- [ ] Platform default AI provider/fallback order config
- [ ] Maintenance-mode flag wiring
- [ ] `app_settings` Admin editor

## Phase 13 — PWA / Accessibility / Non-Functional Hardening

- [ ] Web app manifest + service worker (offline shell, cached static/KB assets)
- [ ] Responsive QA pass across all wireframed pages at mobile/tablet/desktop breakpoints
- [ ] WCAG 2.1 AA pass on onboarding, dashboard, check-in flows (keyboard nav, contrast, ARIA labels)
- [ ] Rate limiting on auth + AI-triggering endpoints
- [ ] Security pass: OWASP Top 10 checklist against [04-Architecture.md](04-Architecture.md) §8
- [ ] Load test: queue worker throughput for `GenerateProgramJob` under concurrent onboarding completions
- [ ] Backup/restore runbook for MySQL + object storage
- [ ] Data export job (right-to-export compliance, PRD §13)
- [ ] Account deletion flow (soft-delete cascade verification)

## Phase 14 — QA & Launch

- [ ] End-to-end test: register → onboarding → program generated → check-in → weekly review → (simulated) stagnation → AI Memory flag → Coach approval
- [ ] Cross-provider validation: run the same program-generation scenario against all 6 providers, confirm schema-valid output from each
- [ ] Content QA: SME review of seeded KB (foods/exercises/diseases) and Rule Engine baseline rules
- [ ] Legal/compliance review: consent copy, data retention policy, PDP Law alignment (PRD §13, open question §12)
- [ ] Staging soak test (realistic multi-day program lifecycle, not just single-request testing)
- [ ] Production deployment per topology in [04-Architecture.md](04-Architecture.md) §12
- [ ] Post-launch monitoring dashboard live (error tracking, AI cost, queue depth)

---

## How This Maps to Success Metrics

Each phase above traces back to a PRD success metric ([01-PRD.md](01-PRD.md) §4) or functional requirement ID — when estimating/prioritizing, prefer finishing a phase's test coverage before moving to the next phase's happy-path UI, since the Rule Engine/AI Response Processor correctness (Phases 4–5) is the foundation every later phase's "adaptive" behavior depends on.
