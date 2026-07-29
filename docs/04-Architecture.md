# System Architecture Document
## AI Personal Health Coach

| | |
|---|---|
| Document | 04-Architecture.md |
| Related | [01-PRD](01-PRD.md) · [05-API-Specification](05-API-Specification.md) · [06-AI-Provider-Interface](06-AI-Provider-Interface.md) |

## 1. Technology Stack

| Layer | Choice | Rationale |
|---|---|---|
| Backend framework | Laravel 12, PHP 8.4 | Confirmed stack per product decision; mature ecosystem for queues/scheduler/auth |
| Frontend | Inertia.js + React + TypeScript | SPA feel without a separate API-consumer frontend to maintain in lockstep |
| Styling | TailwindCSS + ShadCN/ui | Fast, consistent design system |
| Database | MySQL 8.0 | Confirmed; JSON column support used extensively for AI payloads and rule definitions |
| Cache / Queue broker | Redis | Session/cache driver + Laravel Horizon-managed queues |
| Queue | Laravel Queue (Redis driver) + Horizon | AI calls, program generation, and scheduled scans are all async |
| Scheduler | Laravel Scheduler (cron-driven) | Daily/weekly jobs: AI Memory scan, reminders, health score computation |
| Auth | Laravel Sanctum | SPA cookie auth now; bearer tokens ready for future native apps |
| Authorization | Spatie-style role/permission tables, implemented custom against `roles`/`permissions`/`role_permissions` (see [03-Database-Dictionary](03-Database-Dictionary.md)) | Avoids a hard package dependency while matching a well-understood pattern |
| Search (v2 candidate) | MySQL full-text initially; Meilisearch if KB content grows | Deferred — not needed at v1 KB size |
| File storage | Laravel Filesystem (`local` in dev, S3-compatible in prod) | Progress photos, avatars |
| Error tracking | Sentry (or equivalent) | |
| Frontend build | Vite | Laravel 12 default |

## 2. Layered Architecture

```
┌─────────────────────────────────────────────────────────────┐
│  Presentation Layer                                          │
│  React components (Inertia pages) ── ShadCN/Tailwind         │
└───────────────────────────┬───────────────────────────────────┘
                            │ Inertia responses / JSON (mobile-ready API namespace)
┌───────────────────────────▼───────────────────────────────────┐
│  HTTP Layer                                                    │
│  Controllers (thin) → Form Requests (validation) → Resources  │
└───────────────────────────┬───────────────────────────────────┘
                            │
┌───────────────────────────▼───────────────────────────────────┐
│  Application / Service Layer                                   │
│  Services encapsulate use-cases:                               │
│  ProgramGenerationService, RuleEngineService, PromptBuilder-    │
│  Service, AIResponseProcessor, HealthScoreService, ...          │
└───────────────────────────┬───────────────────────────────────┘
                            │
┌───────────────────────────▼───────────────────────────────────┐
│  Domain Layer                                                  │
│  Eloquent Models + Repository interfaces (contracts) for       │
│  testability and to keep services storage-agnostic             │
└───────────────────────────┬───────────────────────────────────┘
                            │
┌───────────────────────────▼───────────────────────────────────┐
│  Infrastructure Layer                                          │
│  Eloquent Repository implementations, AI Provider adapters,    │
│  Queue jobs, external integrations (payment gateways, storage) │
└─────────────────────────────────────────────────────────────────┘
```

**Repository Pattern**: every Eloquent-touching operation used by a Service goes through a `*RepositoryInterface`, bound in a Service Provider. This is not cargo-culted abstraction — it exists specifically so `ProgramGenerationService` and `RuleEngineService` can be unit-tested without a database, and so the AI layer's data-fetching (profile, memory, KB lookups) is mockable in tests that assert prompt construction.

**Service Pattern**: business logic lives in single-purpose services, not controllers or models. Controllers only: validate (Form Request), call one service method, return a Resource/Inertia response.

## 3. Core Domain Services

| Service | Responsibility |
|---|---|
| `RuleEngineService` | Evaluates `rule_engine_rules` against a user's current profile/state, returns deterministic baseline output (calorie target, macro split, workout level, water target, disease restrictions) |
| `PromptBuilderService` | Assembles a final prompt string from an `ai_prompt_templates` row + resolved variables (profile, Rule Engine output, KB facts, AI Memory context) |
| `AIGatewayService` | Resolves the correct `AIProviderInterface` implementation for a user/purpose, sends the request, times it, logs it to `ai_request_logs` |
| `AIResponseProcessor` | Validates AI JSON response against `response_schema`; on failure, retries (≤2) with a corrective follow-up prompt; on repeated failure, falls back to Rule-Engine-only output and flags `ai_request_logs.status = invalid_json` |
| `ProgramGenerationService` | Orchestrates Goal → RuleEngine → AI Analyze → persist meal/workout/checklist/sleep/water/habit rows (FR-PROG-03) |
| `AIMemoryService` | Scheduled scan of logs → writes `ai_memories`; also prunes by `relevance_score` |
| `HealthScoreService` | Computes daily `health_scores` row from weighted components (formula in [08-Knowledge-Base.md](08-Knowledge-Base.md) §5) |
| `RecommendationApplierService` | Applies an `ai_recommendations` row automatically if within Rule-Engine bounds, else routes to Coach approval queue |

## 4. AI Provider Abstraction

Full interface contract lives in [06-AI-Provider-Interface.md](06-AI-Provider-Interface.md). Summary of the pattern:

```
interface AIProviderInterface {
    analyze(array $context): array;
    chat(array $messages, array $context = []): array;
    generatePlan(array $context): array;
    weeklyReview(array $context): array;
    dailyMotivation(array $context): array;
    mealSuggestion(array $context): array;
    workoutSuggestion(array $context): array;
}
```

Concrete adapters: `OpenAIProvider`, `ClaudeProvider`, `GroqProvider`, `GeminiProvider`, `OllamaProvider`, `LMStudioProvider` — each translates the same `$context` array into its provider-specific API call and normalizes the response back into the same array shape. `AIGatewayService` picks the adapter via `user_ai_settings` (or platform default), and on error/timeout can fail over to a user's secondary provider before falling back to Rule-Engine-only output (FR-AI-06).

This is the mechanism that makes the "if OpenAI goes down, switch provider" requirement from the original product discussion concrete: swapping providers is a config change (`user_ai_settings` row or platform default), never a code change.

## 5. Program Generation Pipeline (detail)

```
Goal (from onboarding or program creation)
        │
        ▼
RuleEngineService.evaluate(user)
   → { calorie_target, macro_split, workout_level, water_target_ml, restrictions[] }
        │
        ▼
PromptBuilderService.build('generate_plan', {
   profile, rule_engine_output, kb_context, ai_memory_context
})
        │
        ▼
AIGatewayService.send(prompt) → AIResponseProcessor.validate(response)
        │
        ▼
Persist: meal_plans + meal_plan_items, workout_plans + workout_plan_items,
         daily_tasks, checklist_items, weekly_plans (initial)
        │
        ▼
Dispatch: reminders scheduled, UI notified (plan is now available)
```

This entire pipeline runs as a **queued job** (`GenerateProgramJob`), never synchronously in an HTTP request — AI latency (2–30s depending on provider/model) must never block the request/response cycle. The frontend polls a status endpoint or subscribes to a broadcast event (Laravel Echo/Pusher-compatible) to know when generation completes.

## 6. Scheduled Jobs (Laravel Scheduler)

| Job | Frequency | Purpose |
|---|---|---|
| `ScanAIMemoryJob` | Daily | Detect trends/stagnation/milestones per active `user_program`, write `ai_memories` |
| `GenerateWeeklyReviewJob` | Weekly (per program's week boundary) | Calls `weeklyReview()`, writes `weekly_plans.ai_review` |
| `ComputeHealthScoreJob` | Daily | Writes `health_scores` row per user with a completed onboarding |
| `DispatchRemindersJob` | Every minute (checks `reminders.scheduled_at` against current time per user timezone) | Sends due reminders, updates `last_sent_at` |
| `EvaluateAchievementsJob` | Daily | Checks `achievements.criteria` against user logs, writes `user_achievements` |
| `PruneAIMemoryRelevanceJob` | Weekly | Decays `ai_memories.relevance_score` |
| `SubscriptionRenewalCheckJob` | Daily | Flags `subscriptions` nearing `ends_at`, triggers renewal/payment flow (v1.1+) |

## 7. Multi-Tenancy Readiness

v1 ships single-tenant (one platform instance). To keep the door open for a future white-label/multi-tenant offering without a rewrite:

- No table currently requires a `tenant_id`, but every core table's primary access pattern is already scoped by `user_id`/`coach_id`, which is the natural sharding key.
- If multi-tenancy is greenlit (v3 candidate per [10-Roadmap.md](10-Roadmap.md)), the recommended approach is a nullable `tenant_id` added to `users`, `programs`, and `plans`, with a global scope applied via a `TenantScope` — additive migration, not a redesign.
- Knowledge Base tables (`kb_*`) are intentionally global/shared, not tenant-scoped, since nutrition/exercise/disease facts don't vary by tenant — only `rule_engine_rules` and `ai_prompt_templates` might need tenant-level overrides in a white-label future, which the same additive pattern covers.

## 8. Security

| Concern | Mitigation |
|---|---|
| Broken access control | Server-side policy/permission check on every controller action, not just UI hiding; Coach actions scoped to `coach_members` assignment |
| API key exposure | `user_ai_settings.api_key_encrypted` via `Crypt`; never returned in API responses (write-only field) |
| Injection | Eloquent/query builder parameter binding everywhere; no raw string interpolation into SQL; `rule_engine_rules.condition`/`.action` JSON is evaluated by a constrained internal DSL interpreter, never `eval()` |
| SSRF via local AI providers | `ai_providers.base_url` for Ollama/LM Studio restricted to an admin-configured allowlist, not user-supplied per request |
| Sensitive data exposure | Health data (diseases, medications) never logged in plaintext application logs; `ai_request_logs.request_payload` should redact raw medical free-text where feasible, keep structured/coded fields only |
| CSRF | Laravel's built-in CSRF middleware for the Inertia SPA |
| Rate limiting | Throttle middleware on auth endpoints and AI-triggering endpoints (prevent cost abuse via repeated `generatePlan` calls) |
| File upload (progress photos) | Type/size validation, stored outside public web root or behind signed URLs, `is_private` default true |
| Mass assignment | Explicit `$fillable` per model, Form Request validation as the actual gate, Resources control response shape (never return raw models) |
| Dependency security | `composer audit` / `npm audit` in CI |

## 9. Framework-Provided Tables

Provisioned via Laravel's stock migrations, not hand-designed: `password_reset_tokens`, `sessions`, `personal_access_tokens` (Sanctum), `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `notifications`. See [03-Database-Dictionary.md](03-Database-Dictionary.md) Appendix.

## 10. Event-Driven Touchpoints

Laravel events/listeners used at these seams (decoupling side-effects from core service logic):

| Event | Listeners |
|---|---|
| `OnboardingCompleted` | Dispatch `GenerateProgramJob`; send welcome email |
| `ProgramGenerated` | Notify user (in-app/broadcast); schedule first `reminders` |
| `CheckInSubmitted` | Update `daily_tasks`/`checklist_items`; feed `AIMemoryService` incrementally |
| `AIRecommendationCreated` | If `status=pending`, notify assigned Coach |
| `WeightLogged` | Recalculate `health_profiles.bmi`; check achievement criteria |
| `SubscriptionExpiring` | Notify user, gate feature access per `plans` |

## 11. PWA / Native Readiness

- v1 ships as a responsive web app built PWA-ready: a web app manifest and service worker (offline shell + cached KB/static assets) are included from the start, even though full offline program-generation isn't a v1 requirement.
- API responses already flow through Laravel Resources (not raw Inertia-only responses) so a dedicated `/api/*` namespace can serve a future React Native/Flutter app without duplicating business logic — see [05-API-Specification.md](05-API-Specification.md) §1.

## 12. Deployment Topology (reference)

```
                     ┌────────────┐
                     │   CDN/WAF  │
                     └─────┬──────┘
                           │
                  ┌────────▼────────┐
                  │  Load Balancer   │
                  └───┬─────────┬────┘
                      │         │
              ┌───────▼──┐  ┌───▼──────┐
              │ App Node │  │ App Node │   (stateless, autoscale)
              └───┬──────┘  └────┬─────┘
                  │              │
        ┌─────────▼──────────────▼─────────┐
        │     Redis (cache/session/queue)   │
        └─────────────┬──────────────────────┘
                      │
              ┌───────▼────────┐
              │ Queue Workers   │ (Horizon, autoscale by queue depth)
              └───────┬────────┘
                      │
              ┌───────▼────────┐        ┌──────────────────┐
              │  MySQL 8 (RDS   │        │ Object storage    │
              │  or equivalent) │        │ (S3-compatible)   │
              └─────────────────┘        └──────────────────┘
```

External calls (AI providers, payment gateways) go out from queue workers, never from web-tier request threads, keeping web-tier latency independent of third-party AI latency.
