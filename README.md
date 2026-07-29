# AI Personal Health Coach

A SaaS platform that generates and continuously adapts personalized health programs (diet, workout, sleep, hydration, habits) using a hybrid **Rule Engine + Knowledge Base + AI** architecture, delivered through a human Coach layer. AI reasons over structured data and returns structured JSON — it never decides medical/nutritional facts unilaterally, and it never renders directly to the UI.

First shipped module: **Diet & Transformasi 90 Hari** (90-Day Diet & Transformation), built on a platform foundation designed from day one to support additional program categories (bulking, marathon training, disease management, prenatal, senior, vegetarian, intermittent fasting) without re-architecture.

Stack: **Laravel 12 · PHP 8.4 · Inertia · React · TailwindCSS · ShadCN · MySQL 8 · Redis (Queue/Cache) · Multi-provider AI (OpenAI, Claude, Groq, Gemini, Ollama, LM Studio)**

## Start Here

1. [docs/01-PRD.md](docs/01-PRD.md) — what we're building and why
2. [docs/04-Architecture.md](docs/04-Architecture.md) — how it's built
3. [docs/11-Development-Checklist.md](docs/11-Development-Checklist.md) — the literal build order

## Documentation Index

| # | Document | Contents |
|---|---|---|
| 01 | [PRD](docs/01-PRD.md) | Vision, scope, functional & non-functional requirements, roles, open questions |
| 02 | [ERD](docs/02-ERD.md) | Entity relationship diagrams, grouped by module (57 tables) |
| 03 | [Database Dictionary](docs/03-Database-Dictionary.md) | Field-by-field reference for every table |
| 04 | [Architecture](docs/04-Architecture.md) | Layered architecture, AI abstraction, jobs, security, deployment |
| 05 | [API Specification](docs/05-API-Specification.md) | REST API — 100+ endpoints across every module |
| 06 | [AI Provider Interface](docs/06-AI-Provider-Interface.md) | `AIProviderInterface` contract, adapter pattern, validation/fallback policy |
| 07 | [Prompt Engineering](docs/07-Prompt-Engineering.md) | Prompt Builder mechanics, template catalog, guardrails |
| 08 | [Knowledge Base](docs/08-Knowledge-Base.md) | KB content domains, Rule Engine DSL, Health Score formula |
| 09 | [UI/UX Wireframes](docs/09-UI-UX-Wireframe.md) | Design system, navigation, page inventory (detail in [wireframe/](wireframe/)) |
| 10 | [Roadmap](docs/10-Roadmap.md) | v1.0 → v1.1 → v2.0 → v3.0 |
| 11 | [Development Checklist](docs/11-Development-Checklist.md) | Phase-by-phase build task breakdown |

## Repository Structure

The Laravel application (`app/`, `bootstrap/`, `config/`, `database/`, `public/`, `resources/`, `routes/`, etc.) lives alongside the planning docs below — standard Laravel layout, not reproduced here. `app/Services/`, `app/Repositories/`, and `app/Contracts/` are the base folders for the layered architecture in [04-Architecture.md](docs/04-Architecture.md) §2; they're empty until Phase 1+ fills them in.

```
health/
├── README.md                      ← you are here
├── chat_dengan_chatgpt.txt        ← original product discussion this spec was derived from
├── docs/
│   ├── 01-PRD.md
│   ├── 02-ERD.md
│   ├── 03-Database-Dictionary.md
│   ├── 04-Architecture.md
│   ├── 05-API-Specification.md
│   ├── 06-AI-Provider-Interface.md
│   ├── 07-Prompt-Engineering.md
│   ├── 08-Knowledge-Base.md
│   ├── 09-UI-UX-Wireframe.md
│   ├── 10-Roadmap.md
│   ├── 11-Development-Checklist.md
│   └── CHANGELOG.md
├── database-schema/
│   ├── mysql.sql                  ← canonical schema (source of truth, 57 tables)
│   └── erd.dbml                   ← import at dbdiagram.io for a visual ERD
├── prompts/
│   ├── onboarding.txt
│   ├── meal-plan.txt
│   ├── workout.txt
│   ├── weekly-review.txt
│   ├── daily-chat.txt
│   └── coach-review.txt
└── wireframe/
    ├── onboarding.md
    ├── dashboard.md
    ├── progress.md
    ├── settings.md
    ├── coach.md
    └── admin.md
```

## Core Architectural Principle

```
                        User
                          │
                          ▼
                 Laravel Backend (API)
                          │
        ┌─────────────────┼─────────────────┐
        ▼                 ▼                 ▼
   Rule Engine      Knowledge Base      AI Provider
  (deterministic)    (reference data)   (reasoning, JSON out)
        └─────────────────┼─────────────────┘
                          ▼
               AI Response Processor
              (validates JSON schema,
               falls back to Rule Engine
               output if AI fails)
                          ▼
              React (Inertia) Frontend
```

The app — not the LLM — owns domain logic. Swapping AI providers is a configuration change (`user_ai_settings` row), never a code change. See [docs/06-AI-Provider-Interface.md](docs/06-AI-Provider-Interface.md).

## Getting Started

Phase 0 (project scaffold) is done: Laravel 12 + Inertia/React/TypeScript + TailwindCSS/ShadCN (via `laravel/react-starter-kit`), Sanctum, and Horizon are installed, wired to the `db_akusehatwebid` MySQL/MariaDB database and local Redis, and serving at `https://akusehat.web.id`.

```bash
composer install
npm install
cp .env.example .env   # then fill in DB/Redis credentials — see .env for the values already configured on this server
php artisan key:generate
npm run build           # or `npm run dev` for hot-reload during development
```

Notes specific to this deployment:
- The Apache vhost's `DocumentRoot` points at the repo root, not `public/` — the root [`.htaccess`](.htaccess) rewrites all requests into `public/` and denies direct access to `.env`/`composer.*`/`package*.json`. If you ever get the chance to point `DocumentRoot` straight at `public/` instead, that's the more standard setup — this `.htaccess` is a workaround for a shared-hosting-style constraint.
- The `laravel/react-starter-kit`'s own auth scaffolding (`users` migration, Fortify-less session auth) is a placeholder — it does not match this project's real `users` schema (`role_id`, `timezone`, `status`, etc. per [03-Database-Dictionary](docs/03-Database-Dictionary.md)). Phase 1 replaces it with migrations generated from [database-schema/mysql.sql](database-schema/mysql.sql).
- `npm run build` uses `@rollup/wasm-node` (via a `package.json` override) instead of Rollup's native binary — this server's glibc (2.31, Debian 11) is older than what recent Rollup native builds require (2.32+). Safe to remove once the host OS is upgraded.

## Next Steps

Follow [docs/11-Development-Checklist.md](docs/11-Development-Checklist.md) Phase 1 onward (Core/Auth/RBAC first) — later phases (Program Generation, Coach Module) depend on the Rule Engine and AI Provider Layer built in Phases 4–5.
