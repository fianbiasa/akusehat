# Changelog

All notable changes to this specification/documentation set are recorded here. This is a docs changelog (PRD/ERD/API spec/etc.). As of 2026-07-29 the application itself exists (see entries below) — build progress is tracked here too, phase by phase per [11-Development-Checklist.md](11-Development-Checklist.md), rather than in a separate release changelog.

## [Unreleased] - Application build progress

### Phase 3 — Health Profile (2026-07-29)
- Migrations/models for `health_profiles`, `lifestyle_profiles`, `user_diseases`, `user_allergies`, `user_medications`, `body_measurements`, plus `kb_diseases` pulled forward from Phase 4 (only that one KB table + a 5-row seed, since `user_diseases.kb_disease_id` is a required FK — kb_foods/kb_exercises/kb_nutrition_articles/kb_faqs and their Admin CRUD stay in Phase 4).
- `HealthProfileService`: BMI/BMR (Mifflin-St Jeor)/TDEE, verified against the worked example in docs/07-Prompt-Engineering.md §4 (age 39/167cm/77.5kg → BMI 27.79, BMR 1628.75, TDEE 2239.53 at "light" activity).
- Weight source precedence: most recent `body_measurements` row over `health_profiles.initial_weight_kg` (the onboarding-time baseline) — `weight_logs` (Phase 7) will become the primary trigger once it exists.
- `BodyMeasurementObserver`/`LifestyleProfileObserver` recalculate BMI/BMR/TDEE automatically on a new measurement or an `activity_level` change.
- `MapOnboardingAnswersToHealthProfile` listener populates all six tables from the 55 onboarding answers, matched by `onboarding_questions.step` (coupled to `OnboardingQuestionSeeder` — documented inline since the mapping breaks silently if the two drift apart).
- Settings → "Kesehatan" page (`/profile/health`): editable health/lifestyle fields, disease/allergy/medication add-remove, and a measurement log — all Member-own-data only; Coach/Admin read access is deferred to Phase 8 (`coach_members` doesn't exist yet).
- Two real bugs caught by live HTTP smoke testing (not the in-process test suite): (1) Laravel 12's `Application::configure()` auto-discovers `app/Listeners` by convention, which was silently double-registering every explicitly-`Event::listen()`'d listener (2 listeners → 4 registered) and caused a duplicate-insert crash under concurrent-looking execution — fixed with `->withEvents(discover: false)` in `bootstrap/app.php` so registration order in `AppServiceProvider` is the only source of truth. (2) `date`-cast fields (`date_of_birth`, `measured_at`, etc.) serialize to JSON as full UTC-shifted ISO8601 timestamps by default, which silently breaks `<input type="date">` and can shift the date by a day relative to `APP_TIMEZONE` — fixed with the `date:Y-m-d` cast format across every date-only column.
- 68/68 tests passing (added BMI/BMR/TDEE unit tests, onboarding-mapping feature tests, and profile API CRUD feature tests).

### Phase 2 — Onboarding (2026-07-29)
- Migrations + models for `onboarding_questions`/`onboarding_sessions`/`onboarding_answers`, matching the Database Dictionary (note: `onboarding_answers` has no `created_at`/`updated_at`, only `answered_at` — `OnboardingAnswer::$timestamps = false`).
- 55 seeded questions across identity/body/lifestyle/medical/preferences/goal (wireframe/onboarding.md only sketches step ranges, not literal text — authored here). Medications/allergies use a `validation_rules.repeatable` flag consumed by a generic repeatable-row React component, rather than a one-off special case.
- Wizard API (`/onboarding/*`): resumable session start (idempotent — returns the existing `in_progress` session if one exists), per-question answer autosave advancing `current_step`, and a `complete` endpoint that 422s with the specific missing questions if any required question is unanswered.
- React wizard shell (one question per screen, progress bar, back/skip/next) with input components per `input_type`, driven by plain `fetch` (`resources/js/lib/api.ts`) rather than Inertia visits, so answering a question doesn't trigger a full page reload.
- `OnboardingCompleted` event → `DispatchInitialProgramGeneration` listener → `GenerateInitialProgram` job. The job is a **logging stub**: the real Goal → RuleEngine → AI pipeline is Phases 4-6, not built yet, so this intentionally doesn't fake program generation ahead of the layer it depends on.
- Registration now redirects members to `/onboarding` instead of the dashboard; a new `onboarding.completed` middleware gates the dashboard (and future member-only routes) behind a completed wizard, scoped to the `member` role only.
- Fixed a mass-assignment bug caught by the test suite before it ever reached production: `User::update(['onboarding_completed_at' => ...])` was silently dropped because that column isn't (and shouldn't be) in `$fillable` — it's system-managed, not user-editable. Fixed with `forceFill()` in the one legitimate place that sets it.
- Full 55-question flow verified end-to-end over real HTTP (not just the in-process test suite): register → redirect to wizard → answer all questions → complete → session marked `completed`, `users.onboarding_completed_at` set, job visibly queued in Redis and processed cleanly.
- Not done: Admin question-bank CRUD UI (create/edit/reorder/deactivate) — deferred alongside the rest of the Admin panel.
- 49/49 tests passing.

### Phase 1 — Core / Auth / RBAC (2026-07-29)
- Real `roles`/`permissions`/`role_permissions`/`users` migrations matching the Database Dictionary, replacing the starter kit's placeholder schema.
- `Role`/`Permission` Eloquent models, `User::hasPermission()`/`hasRole()`, `EnsurePermission` middleware (`permission:<name>` route alias).
- `RolePermissionSeeder`: baseline admin/coach/member roles and the permission set from PRD §10.
- Registration assigns the `member` role by default; `GET /api/v1/auth/me` (Sanctum-protected) returns the user with role + permissions.
- Admin panel: Users CRUD (search/filter by role & status, create/edit/soft-delete) and Roles & Permissions management (per-role permission matrix), both gated behind `permission:users.manage` / `permission:roles.manage`. Admin nav section only renders for users with `users.manage`.
- Fixed an infra bug found via live smoke testing (not caught by the test suite, which runs in-process): server-wide ModSecurity (OWASP CRS) was blocking all PATCH/DELETE requests with a 403 before they reached Laravel. Fixed with a `tx.allowed_methods` override scoped to the `akusehat.web.id` vhost only (see the comment in `/etc/apache2/sites-available/akusehat.web.id.conf`, outside this repo).
- 41/41 tests passing (Pest/PHPUnit, in-memory SQLite).

### Phase 0 — Project Setup (2026-07-29)
- Scaffolded Laravel 12 via `laravel/react-starter-kit` (Inertia + React + TypeScript + Tailwind + ShadCN), added Sanctum and Horizon.
- Wired to the real MySQL/MariaDB database and Redis; serving live at `https://akusehat.web.id`.
- Base `app/Services`, `app/Repositories`, `app/Contracts` folders per [04-Architecture.md](04-Architecture.md) §2.
- Not done: CI pipeline (workflow files exist but aren't pushed — token scope gap) and error tracking (no Sentry DSN yet).

## [1.0.0] - 2026-07-29

### Added
- Initial full documentation set generated from the product discussion in [chat_dengan_chatgpt.txt](../chat_dengan_chatgpt.txt):
  - `docs/01-PRD.md` — Product Requirements Document
  - `docs/02-ERD.md` — Entity Relationship Diagrams (11 module diagrams, 57 tables)
  - `docs/03-Database-Dictionary.md` — field-level reference for every table
  - `docs/04-Architecture.md` — System architecture, AI abstraction, jobs, security
  - `docs/05-API-Specification.md` — REST API specification (100+ endpoints)
  - `docs/06-AI-Provider-Interface.md` — `AIProviderInterface` contract and adapter pattern
  - `docs/07-Prompt-Engineering.md` — Prompt Builder mechanics and template catalog
  - `docs/08-Knowledge-Base.md` — Knowledge Base content model and Rule Engine DSL
  - `docs/09-UI-UX-Wireframe.md` + `wireframe/*.md` — design system and page wireframes
  - `docs/10-Roadmap.md` — v1.0 → v3.0 roadmap
  - `docs/11-Development-Checklist.md` — phase-by-phase build task breakdown
  - `database-schema/mysql.sql` — canonical MySQL 8 schema (source of truth)
  - `database-schema/erd.dbml` — DBML mirror for dbdiagram.io visualization
  - `prompts/*.txt` — seed AI prompt templates (onboarding, meal-plan, workout, weekly-review, daily-chat, coach-review)

### Decisions Locked In (from product discussion)
- Platform positioning: **AI Personal Health Coach**, not a diet-only app; "Diet & Transformasi 90 Hari" is module 1 of an extensible platform.
- Architecture: Rule Engine + Knowledge Base compute deterministic ground truth; AI reasons over it and returns JSON only, validated against a schema before persistence or display.
- Stack: Laravel 12, PHP 8.4, Inertia + React, TailwindCSS + ShadCN, MySQL, Queue + Scheduler.
- Roles: Admin, Coach, Member.
- AI may auto-adjust programs within Rule-Engine-defined bounds; out-of-bounds changes require Coach approval.
- Users may run multiple programs concurrently/sequentially.
- Multi-provider AI support (OpenAI, Claude, Groq, Gemini, Ollama, LM Studio) via a shared `AIProviderInterface`, user-configurable per account.

### Open Questions Carried Forward
See [docs/01-PRD.md](../docs/01-PRD.md) §12 — AI cost/billing model, coach assignment mechanism, medical liability posture, data residency.
