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

- [x] Analytics aggregation queries (active users, program completion, AI cost, health outcomes) — `AnalyticsService`: Active Users = `status=active` with `last_login_at` in the last 30 days (login tracking added this phase via a `RecordLastLogin` listener on Laravel's built-in `Login` event, since `users.last_login_at` existed in the schema/model but was never actually written anywhere before now); Program Completion % = completed / total `user_programs`; Avg Health Score = average of each user's *latest* `health_scores.score` (portable `MAX(id)` per-user subquery, since ids are monotonic and a day's score is upserted in place); AI Cost (30d) + AI Cost by Provider = `ai_request_logs.estimated_cost` summed/grouped over the trailing 30 days.
- [x] Admin dashboard React page ([wireframe/admin.md](../wireframe/admin.md)) — `/admin/analytics`, 4 stat cards + AI Cost by Provider bar breakdown, matching the wireframe's "Analytics Landing" layout.
- [x] Consolidate all Admin CRUD screens built in earlier phases into a coherent Admin shell/navigation — Users/Roles/Rule Engine/AI Providers/Analytics/Activity Log now all listed together under one "Admin" sidebar section (`app-sidebar.tsx`).
- [x] Admin activity audit log viewer (`activity_logs`) — table + `ActivityLog` model + `ActivityLogger` service pulled forward from Phase 10 (which had originally scoped this migration), since Phase 9's viewer needs it to exist; `ActivityLogger::log()` wired into every admin write action added so far (user create/update/delete, role permission sync, coach assign/unassign, AI provider/model create/update, rule engine rule create/update/deactivate). Member-facing write-through (program creation, plan override, recommendation approval) stays Phase 10 scope per the original plan.
- [x] Role-gated Admin route group + navigation — both new routes gated by the already-seeded `analytics.view` permission (admin-only), reusing it for both screens rather than adding a redundant permission since they're both the same "observability" concern.
- KB CRUD (foods/exercises/diseases/articles/FAQ editing), Admin Prompt Template editor, and a dedicated AI request-log viewer remain deferred — `wireframe/admin.md` and the PRD depict/require them, but they aren't listed as Phase 9 checklist bullets here; treating this checklist as the authoritative phase-scoping source (same precedent as every earlier phase), not the wireframe/PRD directly.
- 294/294 tests passing.

## Phase 10 — Achievements & Notifications

- [x] Migrations: `achievements`, `user_achievements` — `activity_logs` already created in Phase 9 (pulled forward for the Admin audit log viewer)
- [x] `EvaluateAchievementsJob` (daily scheduled, criteria matching against logs) — `AchievementCriteriaEvaluator` supports 3 documented criteria types (the Database Dictionary only sketches two example JSON shapes, no formal schema): `weight_loss_kg` (vs. earliest weight log), `checklist_streak_days` (N consecutive fully-checked days), `program_milestone_days` (program age). 6 baseline achievements seeded via `AchievementSeeder`, covering both PRD-named categories ("streaks, milestones").
- [x] API: achievements catalog + earned achievements — `GET /achievements` (catalog, any authenticated user) and `GET /profile/achievements` (earned, M(own)/C(assigned)/A — reuses the Progress module's `ResolvesTargetUser` pattern).
- [x] React: achievement badges on Dashboard/Profile — Dashboard shows the 3 most recent earned badges; the health Profile page (`/profile/health`, this app's actual "health profile" screen) shows the full catalog as a locked/unlocked badge grid.
- [x] `activity_logs` write-through in remaining member/coach-facing services (program creation, plan override, recommendation approval) — `ProgramGenerationService::bootstrap()` logs `program.created`; `MealPlanController`/`WorkoutPlanController::update()` log `meal_plan.overridden`/`workout_plan.overridden` **only** when an actual manual override happens (total_calories/duration_minutes set), not on routine `is_completed` checklist-style toggles, per `ActivityLogger`'s own "audit-worthy, not routine noise" design; `CoachRecommendationService::approve()`/`reject()` log `recommendation.approved`/`recommendation.rejected`. Admin-side write-through (users, roles, coach assignment, AI provider, rule engine) was already done in Phase 9.
- [x] Laravel Notification channels: in-app + email (push deferred to v1.1) — added `mail` (queued, `ShouldQueue`) alongside `database` for one-time/significant events: `ProgramReady`, `RecommendationReviewed`, and the new `AchievementEarned`. `ReminderDue` stays database-only per its existing Phase 6 reasoning (per-occurrence cadence, would spam).
- Bug found and fixed via live smoke testing (unrelated to this phase's own new code, but discovered while creating smoke-test accounts): `Admin\UserController::store()` set `email_verified_at` via a plain `User::create()` array, but that column is deliberately excluded from `$fillable` (see `OnboardingController`'s existing `forceFill()` precedent for `onboarding_completed_at`) — so it was being silently dropped on every Admin-created account. No functional impact today (no route uses the `verified` middleware), but the code's own intent was broken. Fixed with the same `forceFill()` pattern already established elsewhere in the codebase, with a regression test.
- 316/316 tests passing.

## Phase 11 — Subscription Scaffolding (schema + gating, no live gateway in v1)

- [x] Migrations: `plans`, `subscriptions`, `payments`
- [x] Seed: default plan tiers — `PlanSeeder`: Gratis (free, max_programs=1, no coach access), Premium Bulanan, Premium Tahunan (both max_programs=3, coach access) — neither the PRD nor wireframe name actual tiers/pricing (PRD §12 leaves the AI-cost/pricing model as an open business question), so this is a designed scaffold, not a business decision.
- [x] `SubscriptionRenewalCheckJob` (scheduled, flags expiring) — daily job transitions `active` subscriptions past `ends_at` to `expired` (+ `SubscriptionExpired` notification) and flags ones expiring in exactly 7 days (+ `SubscriptionExpiringSoon` notification). No live gateway means there's no actual "renewal" to attempt — this only detects and notifies. Once expired, a user's next `SubscriptionService::currentSubscription()` call lazily re-enrolls them onto Gratis rather than the job inserting that row itself.
- [x] Plan-based gating middleware (`max_programs`, `has_coach_access`) — `max_programs` is real Illuminate middleware (`plan.program_limit` → `EnsureWithinProgramLimit`) on `POST /user-programs`, since it only needs the request's own user. `has_coach_access` is checked inline in `Admin\CoachAssignmentController::store()` instead of literal middleware, since that check needs the *target member* being assigned (a route param), not the acting admin — documented in both files.
- [x] API: plans catalog, subscription status, sandboxed subscribe/cancel (§14 of [05-API-Specification.md](05-API-Specification.md)) — `GET /plans` (public), `GET /subscription` (Inertia page, matching every other Settings-tab endpoint in this app), `POST /subscription/subscribe` simulates an instantly-successful payment (no live gateway in v1; `payments.provider` has no "sandbox" enum value, so `midtrans` is used as the placeholder with an obviously-fake `provider_reference`), `POST /subscription/cancel` (cancel-at-period-end, blocked on the free plan or an already-cancelled subscription), `GET /subscription/payments`.
- [x] React: Subscription tab in Settings ([wireframe/settings.md](../wireframe/settings.md)) — "Langganan" tab at `/subscription`: current plan/status/usage, plan comparison cards, cancel dialog, payment history table.
- [x] Admin: plans CRUD + subscriptions list — `/admin/plans` (create/edit, `ActivityLogger`-wired), `/admin/subscriptions` (filterable by status/plan), gated by a new `subscriptions.manage` permission (admin-only).
- Every new registration is eagerly enrolled onto Gratis via a `Registered` event listener (`AssignDefaultPlan`); `SubscriptionService::currentSubscription()` also lazily backfills it for any user reached another way (pre-Phase-11 existing accounts, or a just-expired subscription), so gating code never has to null-check a user's plan.
- A pre-existing Phase 8 test (`CoachAssignmentControllerTest`) needed updating: assigning a coach now requires the member's plan to include coach access, so the test's plain factory member had to be subscribed to a paid plan first — a real, correct interaction between two phases' features, not a regression.
- 344/344 tests passing.

## Phase 12 — App Settings & Cross-Cutting

- [x] Migration: `app_settings` — matches `mysql.sql` exactly: `key` unique, `value` json, `description`, `updated_at` only (no `created_at`).
- [x] Platform default AI provider/fallback order config — resolves PRD §6.3's open business question ("bring-your-own-key OR platform provides a default shared provider/key"). `AppSettingsService::platformDefaultAiSetting()` builds an unsaved `UserAiSetting`-shaped object from `app_settings` key `ai.platform_default` (provider_id/model_id/temperature + an already-encrypted API key), reusing `UserAiSetting`'s own `decryptedApiKey()`/relations rather than inventing a parallel structure. `AIGatewayService::defaultSettings()` falls back to it only when a user has configured *zero* of their own providers — a user with even one personal provider never touches the shared key. Every AI call still logs to `ai_request_logs` under the real user's `user_id` even when using the shared key, so per-user usage against a metered shared key stays measurable.
- [x] Maintenance-mode flag wiring — `EnsureNotInMaintenanceMode` middleware, appended globally to the `web` group (not scoped to specific routes, since maintenance mode by definition affects the whole app). Distinct from Laravel's built-in file-based `php artisan down` (which needs shell access an Admin operator doesn't have) — this is DB-backed and Admin-toggleable from `/admin/settings`. Admins always bypass it (so they can turn it back off), and `/login`/`/logout` always stay reachable so an Admin isn't locked out before authenticating. Custom `resources/views/errors/503.blade.php` shows the configured message on a clean standalone page (no Vite/React dependency, in case maintenance is genuinely needed because the frontend build is broken).
- [x] `app_settings` Admin editor — `/admin/settings`: AI Provider Default card (provider/model select + API key + temperature, matching the member-facing AI settings form's UX) and Maintenance Mode card (toggle + message), gated by a new `app_settings.manage` permission, wired into `ActivityLogger`.
- **Caught and fixed a brief self-inflicted production issue while smoke testing**: this server *is* the live site — code edits (like appending new middleware to the global `web` group in `bootstrap/app.php`) take effect on real traffic immediately, unlike every prior phase's new-route-only changes which were safe to leave unmigrated on the real DB until the end-of-phase smoke test. The `app_settings` migration hadn't been run against the real database yet when the global middleware started executing, so every request site-wide 500'd (`app_settings` table not found) for roughly a minute until caught by the routine pre-smoke-test health check and fixed by running the migration. See [[project-akusehat-infra]] — this generalizes: any future phase touching a globally-applied middleware/config must have its migration run the moment the code change lands, not deferred.
- Maintenance mode was live-tested directly against production (enable → verify 503 for guests/login-reachable/admin-bypass → disable → re-verify 200), kept to the smallest possible time window, then fully cleaned up (including the `app_settings` rows themselves) so no smoke-test residue was left in the real database.
- 364/364 tests passing.

## Phase 13 — PWA / Accessibility / Non-Functional Hardening

- [x] Web app manifest + service worker (offline shell, cached static/KB assets) — `public/manifest.json` + `public/sw.js`. This is an Inertia SPA with server-rendered/sensitive health data, not a fully offline-capable client app, so the service worker deliberately only caches content-hashed `/build/` assets (safe forever) and a small `offline.html` fallback shown on failed navigation — it never caches API/page-data responses, since serving stale health data while "offline" would be actively misleading. New `public/icon.svg` (purpose-built, since the only existing asset was the generic starter-kit `logo.svg`, not real AkuSehat branding).
- [x] Responsive QA pass across all wireframed pages at mobile/tablet/desktop breakpoints — code-level Tailwind audit (no browser-automation tooling available in this environment). Found and fixed two real, systemic gaps: 5 Admin/Settings data tables used `overflow-hidden` on their wrapper (clips wide content on mobile instead of scrolling) instead of `overflow-x-auto`; 7 form-field grids across 6 files used an un-prefixed `grid-cols-2` (crams two columns even on a narrow phone) instead of `grid-cols-1 sm:grid-cols-2`. See [14-Accessibility-Audit.md](14-Accessibility-Audit.md).
- [x] WCAG 2.1 AA pass on onboarding, dashboard, check-in flows (keyboard nav, contrast, ARIA labels) — code-level review. Found and fixed 4 real issues: the onboarding wizard's question heading was never programmatically associated with its input (`aria-labelledby` now threads through every `QuestionInput` variant); single/multi-choice and scale buttons had no `aria-pressed` conveying selection state to screen readers; a repeatable-row "remove" button was icon-only with no accessible name; the Dashboard's daily checklist checkbox (the actual check-in mechanism) had **no label association at all**. Full findings in [14-Accessibility-Audit.md](14-Accessibility-Audit.md).
- [x] Rate limiting on auth + AI-triggering endpoints — `POST /login` already had Laravel's own per-email+IP limiter built in. Added route-level `throttle` to `POST /register` (5/min), `POST /forgot-password` (3/min), `POST /ai/settings/{id}/test` (10/min), `POST /conversations/{id}/messages` (20/min), `POST /user-programs` + `.../regenerate` (5/min each) — every endpoint that either sends a real email or triggers a real AI-calling code path.
- [x] Security pass: OWASP Top 10 checklist against [04-Architecture.md](04-Architecture.md) §8 — full findings in [13-Security-Audit.md](13-Security-Audit.md). Broken access control, injection, XSS, mass assignment, file upload, and sensitive-data-logging all verified clean via systematic grep-based audits across every module. `composer audit`/`npm audit` run; the only findings are 6 high-severity advisories in the `eslint` devDependency chain (dev-tooling only, never shipped to production) and a stale-cache caveat on `composer audit` (no outbound network access in this environment to check live advisories).
- [x] Load test: queue worker throughput for `GenerateProgramJob` under concurrent onboarding completions — **found first that no persistent queue worker or scheduler was running for this app in production at all** (checked `ps aux`/`supervisorctl status` — every other Laravel site on this shared box had one, akusehat didn't). Fixed by adding `/etc/supervisor/conf.d/akusehat-worker.conf` (2-process `queue:work` + a `schedule:work` process), matching the exact pattern already used for sibling sites. With that running, 30- and 100-job concurrent-dispatch batches both drained in under ~1s with 0 failures. Full results and methodology in [15-Load-Test-Results.md](15-Load-Test-Results.md).
- [x] Backup/restore runbook for MySQL + object storage — [16-Backup-Restore-Runbook.md](16-Backup-Restore-Runbook.md). Also found no automated backup exists anywhere on this server currently (for any site, not just this one) — documented the manual procedure plus a proposed (not yet enabled) automation script, since actually enabling scheduled backups requires a decision on offsite storage this session isn't positioned to make unilaterally.
- [x] Data export job (right-to-export compliance, PRD §13) — `UserDataExportService` gathers everything a Member can already see about themselves elsewhere in the app (deliberately excludes other users' data, coach notes not marked visible to the member, and any encrypted secret) into a JSON file, queued via `ExportUserDataJob`, delivered through a 24h signed download link (mail + database notification). Live smoke testing against the real database caught a genuine bug undetectable via the PHPUnit suite: a typo'd column name (`ai_memories.content`, which doesn't exist — confused with `ai_recommendations.content`) that SQLite silently swallows (returns empty/NULL instead of erroring) but MySQL correctly rejects — see the new gotcha documented in project memory. Fixed, and the regression test strengthened to include a real row in that relation so SQLite can't hide the same class of bug again.
- [x] Account deletion flow (soft-delete cascade verification) — verified `SoftDeletingScope` correctly excludes deleted users from login, the Admin users list, and every other Eloquent query app-wide (no raw `DB::table('users')` queries or `withTrashed()` calls exist anywhere that would bypass it). Found and fixed a real, if minor, compliance-communication bug: the starter-kit's self-service "Delete account" dialog claimed data would be "permanently deleted," which was never true (it's a soft delete, matching the Admin-side deletion copy which already said this correctly) — corrected the wording. Also added Sanctum token revocation on both the self-service and Admin-initiated deletion paths (defense in depth) and 3 new regression tests confirming data retention, login lockout, and token revocation.
- 379/379 tests passing (34 new for Phase 13).

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
