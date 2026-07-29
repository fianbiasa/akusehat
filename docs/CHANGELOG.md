# Changelog

All notable changes to this specification/documentation set are recorded here. This is a docs changelog (PRD/ERD/API spec/etc.). As of 2026-07-29 the application itself exists (see entries below) — build progress is tracked here too, phase by phase per [11-Development-Checklist.md](11-Development-Checklist.md), rather than in a separate release changelog.

## [Unreleased] - Application build progress

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
