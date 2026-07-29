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

- [ ] Migrations: `onboarding_questions`, `onboarding_sessions`, `onboarding_answers`
- [ ] Seeder: ~55 questions across identity/body/goal/lifestyle/medical categories (content from [wireframe/onboarding.md](../wireframe/onboarding.md))
- [ ] `OnboardingQuestion`, `OnboardingSession`, `OnboardingAnswer` models
- [ ] Wizard API: start/resume session, submit answer, complete
- [ ] React: Wizard shell (progress bar, back/next, skip-if-optional)
- [ ] React: input components per `input_type` (text/number/date/single_choice/multi_choice/time/scale)
- [ ] React: repeatable-row components (medications, allergies)
- [ ] `OnboardingCompleted` event + listener dispatching `GenerateInitialProgram` job
- [ ] Admin: question bank CRUD (create/edit/reorder/deactivate) + React pages
- [ ] Feature test: full wizard completion → event fired → job dispatched
- [ ] Feature test: resume after partial completion

## Phase 3 — Health Profile

- [ ] Migrations: `health_profiles`, `lifestyle_profiles`, `user_diseases`, `user_allergies`, `user_medications`, `body_measurements`
- [ ] Models + relationships
- [ ] `HealthProfileService`: BMI/BMR/TDEE calculation (formulas in [08-Knowledge-Base.md](08-Knowledge-Base.md) §4)
- [ ] Listener: recalculate BMI/BMR/TDEE on new `weight_logs` entry or `lifestyle_profiles.activity_level` change
- [ ] Onboarding → Health Profile mapping (answers populate these tables on completion)
- [ ] API: profile/lifestyle/diseases/allergies/medications/measurements endpoints (§5 of [05-API-Specification.md](05-API-Specification.md))
- [ ] React: Profile edit screens ([wireframe/settings.md](../wireframe/settings.md) "Profil" tab)
- [ ] Unit tests: BMI/BMR/TDEE formula correctness against known reference values

## Phase 4 — Knowledge Base & Rule Engine

- [ ] Migrations: `kb_diseases`, `kb_foods`, `kb_exercises`, `kb_nutrition_articles`, `kb_faqs`, `rule_engine_rules`
- [ ] Seed: initial disease set (diabetes, hipertensi, kolesterol, asam urat, tukak lambung/GERD)
- [ ] Seed: initial food set (~300–500 Indonesian foods, SME-sourced per [08-Knowledge-Base.md](08-Knowledge-Base.md) §7)
- [ ] Seed: initial exercise set (~100–150 exercises)
- [ ] Seed: baseline rule set (calorie target, macro split, workout level, water target, disease restrictions)
- [ ] `RuleEngineConditionEvaluator`: implement condition DSL (`>=`,`<=`,`>`,`<`,`==`,`in`,`and`,`or`,`not`)
- [ ] `RuleEngineService::evaluate(User $user): array` — priority-based conflict resolution across categories
- [ ] Admin: Rule Engine CRUD + "Uji Coba" (test-against-sample-profile) endpoint/UI
- [ ] Admin: Knowledge Base CRUD (foods/exercises/diseases/articles/FAQ) + React pages
- [ ] KB search endpoints (`?q=&category=&tags[]=`)
- [ ] Unit tests: rule evaluator against each example rule in [08-Knowledge-Base.md](08-Knowledge-Base.md) §3
- [ ] Unit tests: conflict resolution (two rules same category, priority wins)

## Phase 5 — AI Provider Layer

- [ ] Migrations: `ai_providers`, `ai_models`, `user_ai_settings`, `ai_prompt_templates`, `ai_memories`, `ai_recommendations`, `ai_request_logs`
- [ ] Define `AIProviderInterface` contract (per [06-AI-Provider-Interface.md](06-AI-Provider-Interface.md) §2)
- [ ] Implement `OpenAIProvider`
- [ ] Implement `ClaudeProvider`
- [ ] Implement `GroqProvider`
- [ ] Implement `GeminiProvider`
- [ ] Implement `OllamaProvider`
- [ ] Implement `LMStudioProvider`
- [ ] `AIProviderServiceProvider`: bind `driver_class` → concrete resolution
- [ ] `PromptBuilderService`: template + variable resolution + fixed JSON instruction block
- [ ] Seed `ai_prompt_templates` from `prompts/*.txt` (onboarding, meal-plan, workout, weekly-review, daily-chat, coach-review)
- [ ] `AIGatewayService`: provider resolution, call timing, logging to `ai_request_logs`, failover to secondary provider
- [ ] `AIResponseProcessor`: JSON decode + schema validation + retry (≤2) + Rule-Engine fallback
- [ ] Member: AI Settings CRUD (provider/model/API key/temperature) + "Test Connection" + React page ([wireframe/settings.md](../wireframe/settings.md))
- [ ] Admin: AI Provider/Model CRUD + React pages
- [ ] Admin: Prompt Template editor (with version bump on save) + React page
- [ ] Admin: AI request log viewer + cost dashboard
- [ ] Unit tests: each provider adapter against a mocked HTTP client (request shape correctness)
- [ ] Unit tests: `AIResponseProcessor` retry/fallback branches
- [ ] Integration test: at least 2 real providers (1 cloud, 1 local) end-to-end against a sandbox/dev key

## Phase 6 — Program Generation

- [ ] Migrations: `programs`, `user_programs`, `program_goals`, `weekly_plans`, `daily_tasks`, `meal_plans`, `meal_plan_items`, `workout_plans`, `workout_plan_items`, `checklist_items`, `reminders`
- [ ] Seed: `programs` catalog (starting with "Diet & Transformasi 90 Hari")
- [ ] `ProgramGenerationService`: Goal → RuleEngine → PromptBuilder → AIGateway → AIResponseProcessor → persist
- [ ] `GenerateProgramJob` (queued), status-polling endpoint
- [ ] `AIMemoryService`: scheduled trend/pattern/milestone/concern detection (`ScanAIMemoryJob`)
- [ ] `RecommendationApplierService`: bounds-check against Rule Engine, auto-apply vs. queue for Coach approval
- [ ] `GenerateWeeklyReviewJob` (scheduled per program's week boundary)
- [ ] API: program catalog, user-programs CRUD, goals, weekly-plans, daily-tasks, meal-plans (+items), workout-plans (+items), checklist, reminders (§6 of [05-API-Specification.md](05-API-Specification.md))
- [ ] `DispatchRemindersJob` (per-minute scheduler tick, timezone-aware)
- [ ] React: Dashboard (today view) per [wireframe/dashboard.md](../wireframe/dashboard.md)
- [ ] React: Program detail / weekly review detail views
- [ ] React: multi-program switcher
- [ ] Events: `ProgramGenerated`, `CheckInSubmitted`, `AIRecommendationCreated` + listeners
- [ ] Feature test: full generation pipeline produces schema-valid, persisted plan
- [ ] Feature test: auto-apply vs. pending-approval branching for a boundary-testing recommendation

## Phase 7 — Progress Tracking & Health Score

- [ ] Migrations: `weight_logs`, `waist_logs`, `body_fat_logs`, `progress_photos`, `water_intake_logs`, `sleep_logs`, `health_scores`
- [ ] `HealthScoreService`: weighted composite calculation (formula in [08-Knowledge-Base.md](08-Knowledge-Base.md) §5)
- [ ] `ComputeHealthScoreJob` (daily scheduled)
- [ ] `analyze()` capability wiring for Health Score `explanation` generation
- [ ] API: weight/waist/body-fat/sleep/water/photos/health-score endpoints (§7 of [05-API-Specification.md](05-API-Specification.md))
- [ ] File upload handling for progress photos (validation, private storage, signed URLs)
- [ ] React: Progress page (charts, photo timeline, consistency grid) per [wireframe/progress.md](../wireframe/progress.md)
- [ ] Charting library integration (time-series weight/waist/health-score trend)
- [ ] Unit tests: Health Score formula against known component inputs
- [ ] Feature test: photo privacy default + share-to-coach toggle

## Phase 8 — Coach Module

- [ ] Migrations: `coach_profiles`, `coach_members`, `coach_notes`, `conversations`, `messages`, `reviews`
- [ ] `CoachProfile` model + onboarding flow for coach accounts (Admin-created)
- [ ] Assignment logic: `coach_members` create/reassign (ends old row, creates new)
- [ ] Coach dashboard aggregation query (flagged concerns via `ai_memories`/`ai_recommendations`)
- [ ] `chat()` capability wiring for AI-assistant conversations; coach_member conversations are plain messaging (no AI)
- [ ] Real-time messaging (broadcast via Laravel Echo/Pusher-compatible channel) or polling fallback
- [ ] API: coach members/notes/recommendations approve-reject/dashboard/reviews (§8–9 of [05-API-Specification.md](05-API-Specification.md))
- [ ] React: Coach Dashboard + Member Detail per [wireframe/coach.md](../wireframe/coach.md)
- [ ] React: Chat UI (shared component for coach_member and ai_assistant conversation types)
- [ ] React: Review/rating submission (Member-facing, on program completion or periodically)
- [ ] Feature test: recommendation approve/reject updates status + notifies Member
- [ ] Feature test: private note visibility toggle

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
