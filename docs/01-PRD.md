# Product Requirements Document (PRD)
## AI Personal Health Coach — SaaS Platform

| | |
|---|---|
| Document | 01-PRD.md |
| Version | 1.0 |
| Status | Draft for engineering kickoff |
| Owner | Product |
| Related docs | [02-ERD](02-ERD.md) · [03-Database-Dictionary](03-Database-Dictionary.md) · [04-Architecture](04-Architecture.md) · [05-API-Specification](05-API-Specification.md) · [06-AI-Provider-Interface](06-AI-Provider-Interface.md) · [07-Prompt-Engineering](07-Prompt-Engineering.md) · [08-Knowledge-Base](08-Knowledge-Base.md) · [09-UI-UX-Wireframe](09-UI-UX-Wireframe.md) · [10-Roadmap](10-Roadmap.md) · [11-Development-Checklist](11-Development-Checklist.md) |

---

## 1. Executive Summary

**AI Personal Health Coach** is a SaaS platform that generates and continuously adapts personalized health programs (diet, workout, sleep, hydration, habits) using a hybrid **Rule Engine + Knowledge Base + AI** architecture, delivered through a human Coach layer.

It is **not** a calorie-counting app and **not** a raw AI chatbot wrapper. The distinguishing bet of this product is that the application — not the LLM — owns the domain logic. The Rule Engine and Knowledge Base compute deterministic, medically-grounded baselines (BMI, BMR, TDEE, calorie targets, contraindications); the AI layer is used for reasoning, natural-language explanation, personalized variation, and longitudinal pattern-reading ("AI Memory"), always constrained to return structured JSON that the backend renders — never freeform HTML or markdown that gets displayed directly.

The first shipped module is **"Diet & Transformasi 90 Hari"** (90-Day Diet & Transformation), but the platform, database, and architecture are designed from day one to support additional program categories (bulking, marathon training, diabetes/hypertension/cholesterol/gout management, prenatal, senior, vegetarian, intermittent fasting) without re-architecture.

## 2. Background & Problem Statement

Existing diet/fitness apps fall into two failure modes:

1. **Static calculators** (calorie counters, BMI tools) — accurate but not adaptive, no coaching, no continuity.
2. **Generic AI chatbot wrappers** — adaptive-sounding but unstable: identical inputs produce different outputs run to run, no medical guardrails, no persistent memory of the user's actual trend, and full vendor lock-in to one AI provider's pricing/availability.

Users managing real health conditions (diabetes, hypertension, cholesterol, gout, GERD/tukak lambung) need programs that respect medical constraints deterministically, not "AI vibes." Users also disengage from apps that don't feel like they have a coach who remembers where they left off.

## 3. Product Vision

> A platform where the app itself is the domain expert — encoding nutrition science, exercise physiology, and disease-specific constraints as data and rules — and AI is the reasoning/communication layer on top, swappable across providers, so the product is never dependent on a single AI vendor's uptime, pricing, or behavior drift.

## 4. Goals & Success Metrics

| Goal | Metric | v1 Target |
|---|---|---|
| Programs feel personalized & adaptive | % of active users with ≥1 AI recommendation applied per week | ≥ 60% |
| Consistency of AI output | % of AI responses passing JSON-schema validation on first try | ≥ 98% |
| Retention via coaching feel | D30 retention | ≥ 35% |
| Habit completion | Avg. daily checklist completion rate | ≥ 70% |
| Vendor independence | Time to add a new AI provider | ≤ 1 day of engineering work (interface-conformant) |
| Coach efficiency | Members per coach manageable without performance degradation | ≥ 50 |
| Health outcome signal | Users with improving Health Score trend over 4 weeks | ≥ 50% |

## 5. Target Users & Personas

1. **Member (primary)** — an adult (typically 25–55) managing weight and/or a chronic condition (diabetes, hypertension, cholesterol, gout, GERD), wants a program that adapts without needing to think about the science themselves.
2. **Coach** — a certified nutrition/fitness professional overseeing a caseload of Members, reviewing AI-generated plans, intervening when needed, chatting with Members.
3. **Admin** — platform operator managing Knowledge Base content, AI provider configuration, rule engine rules, coach onboarding, and subscription/billing oversight.

Out of scope for v1 personas: standalone Coach-marketplace discovery (Members are assigned, not shopping), corporate/enterprise wellness accounts (candidate for v3, see [Roadmap](10-Roadmap.md)).

## 6. Scope

### 6.1 In scope — v1 ("Diet & Transformasi 90 Hari" module on the platform foundation)

- Registration/authentication, RBAC (Admin / Coach / Member)
- 50–60 question wizard-style onboarding
- Rule Engine computing BMI/BMR/TDEE and baseline targets
- Knowledge Base (Indonesian food database, exercise database, disease reference data, nutrition articles, FAQ)
- AI Provider abstraction layer supporting OpenAI, Claude, Groq, Gemini, Ollama, LM Studio — user-configurable per account
- Program generation (meal plan, workout plan, daily checklist, weekly plan, sleep/water targets, reminders) combining Rule Engine + Knowledge Base + AI
- Daily check-in flow; AI Memory that reads longitudinal trends and triggers automatic program revision
- Multiple concurrent/sequential programs per user
- Progress tracking (weight, waist, body fat, sleep, water, photos) and computed Health Score (0–100) with AI-generated explanation
- Coach dashboard: member management, notes, chat, plan review/override
- Admin panel: Knowledge Base CRUD, Rule Engine CRUD, AI provider/model config, user & role management, analytics
- Achievements/gamification (basic)
- Subscription/plan scaffolding (schema + gating, not full payment gateway integration in v1 — see §6.3)

### 6.2 Explicitly out of scope for v1

- Native Android/iOS apps (architecture is PWA-ready; native wrapper is v2+)
- Wearable device integrations (Apple Health, Google Fit, Fitbit) — v2
- Multi-tenant white-label — schema is multi-tenant-ready but tenant isolation UI/ops tooling is v3
- Real payment gateway processing (Stripe/Xendit/Midtrans) beyond sandbox/stub — v1.1
- Marketplace-style coach discovery/booking

### 6.3 Assumptions

- Initial content (food DB, exercise DB, disease reference data, rule engine seed rules) will be authored/curated by the product owner and a nutrition/fitness SME before v1 launch; the platform provides the data model and Admin CRUD, not the source content itself.
- Each Member brings their own AI provider API key OR the platform provides a default shared provider/key with usage metering (business decision — see open question in §12).

## 7. System Concept & Architecture Summary

Full detail in [04-Architecture.md](04-Architecture.md). Summary:

```
                        User
                          │
                          ▼
                 Laravel Backend (API)
                          │
        ┌─────────────────┼─────────────────┐
        ▼                 ▼                 ▼
   Rule Engine      Knowledge Base      AI Provider
  (deterministic)      (reference          (reasoning,
                        data, no            language,
                        AI involved)        JSON output)
        └─────────────────┼─────────────────┘
                          ▼
               AI Response Processor
              (validates JSON schema,
               merges with rule engine
               output, persists)
                          ▼
              React (Inertia) Frontend
```

**Non-negotiable architectural principle:** the AI never decides medical/nutritional facts unilaterally. It receives Rule Engine output + Knowledge Base facts + AI Memory context as *input*, and returns structured JSON *output* per a fixed schema (see [07-Prompt-Engineering.md](07-Prompt-Engineering.md)). The AI Response Processor validates the JSON against a schema before it touches the database or UI; invalid JSON is retried (max 2 retries) then falls back to the last valid Rule-Engine-only plan.

## 8. Functional Requirements

Requirement IDs are stable across docs — the API spec and dev checklist reference these same IDs.

### 8.1 Authentication & RBAC (FR-AUTH)

| ID | Requirement | Acceptance Criteria |
|---|---|---|
| FR-AUTH-01 | Users register with email/password | Email uniqueness enforced; verification email sent |
| FR-AUTH-02 | Roles: Admin, Coach, Member | Role assigned at creation; only Admin can change roles |
| FR-AUTH-03 | Permission-based access control | Route/action gated by `permissions` via `role_permissions`; middleware rejects unauthorized with 403 |
| FR-AUTH-04 | Session/token auth for SPA + future mobile | Laravel Sanctum; SPA cookie auth for web, bearer tokens for future native apps |
| FR-AUTH-05 | Password reset flow | Standard Laravel reset-token flow |

### 8.2 Onboarding (FR-ONB)

| ID | Requirement | Acceptance Criteria |
|---|---|---|
| FR-ONB-01 | Wizard-style onboarding, 50–60 questions, one (or few) per screen | No traditional long-form; progress bar; back/skip-if-optional supported |
| FR-ONB-02 | Question categories: identity, body measurements, goals, activity/lifestyle, sleep, diet habits, vices (smoking/alcohol/sugar), exercise habits, medical history (diseases), medications, allergies | Matches `onboarding_questions.category`; content seeded by Admin |
| FR-ONB-03 | Answers persisted incrementally (resumable) | `onboarding_sessions.current_step` tracked; user can resume after app close |
| FR-ONB-04 | On completion, triggers Rule Engine baseline calc + first Program Generation job | `users.onboarding_completed_at` set; `GenerateInitialProgram` job dispatched to queue |
| FR-ONB-05 | Admin can add/edit/reorder/deactivate questions without a deploy | CRUD UI backed by `onboarding_questions` |

### 8.3 Health Profile (FR-HP)

| ID | Requirement | Acceptance Criteria |
|---|---|---|
| FR-HP-01 | Capture demographic + anthropometric baseline | `health_profiles` populated from onboarding answers |
| FR-HP-02 | Auto-compute BMI, BMR (Mifflin-St Jeor), TDEE | Recalculated whenever weight or activity level changes |
| FR-HP-03 | Track diseases, medications, allergies, lifestyle | `user_diseases`, `user_medications`, `user_allergies`, `lifestyle_profiles` |
| FR-HP-04 | Disease selection triggers dietary/exercise restriction lookup | Joins `user_diseases` → `kb_diseases.dietary_restrictions` / `contraindicated_exercise`, fed into Rule Engine |

### 8.4 Rule Engine (FR-RULE)

| ID | Requirement | Acceptance Criteria |
|---|---|---|
| FR-RULE-01 | Deterministic condition→action rules stored as data, not code | `rule_engine_rules.condition` / `.action` as JSON, evaluated by a rule evaluation service |
| FR-RULE-02 | Rules cover: calorie target, macro split, workout level/intensity, water target, disease-based restrictions | Seed set ships with v1 (documented in [08-Knowledge-Base.md](08-Knowledge-Base.md)) |
| FR-RULE-03 | Rules are versionable and admin-editable | Admin CRUD; `priority` resolves conflicts (highest priority wins per category) |
| FR-RULE-04 | Rule Engine output is always computed first and passed to AI as ground truth | AI prompt always includes Rule Engine output block (see [07-Prompt-Engineering.md](07-Prompt-Engineering.md) §2) |

### 8.5 AI Provider Layer (FR-AI)

| ID | Requirement | Acceptance Criteria |
|---|---|---|
| FR-AI-01 | Multi-provider support: OpenAI, Claude, Groq, Gemini, Ollama, LM Studio | Each implements `AIProviderInterface` (see [06-AI-Provider-Interface.md](06-AI-Provider-Interface.md)) |
| FR-AI-02 | Per-user provider/model/API-key configuration in Settings | `user_ai_settings`; API key encrypted at rest via `Crypt` |
| FR-AI-03 | All AI responses must be valid JSON conforming to a per-purpose schema | Enforced by `AIResponseProcessor`; invalid → retry ≤2 → fallback to Rule-Engine-only output |
| FR-AI-04 | Every AI call is logged (tokens, cost, latency, status) | `ai_request_logs` row per call, used for cost dashboards and debugging |
| FR-AI-05 | AI capabilities exposed: `analyze`, `chat`, `generatePlan`, `weeklyReview`, `dailyMotivation`, `mealSuggestion`, `workoutSuggestion` | One interface method per capability; see §9 of Architecture doc |
| FR-AI-06 | Provider failover | If default provider errors/times out, and user has a secondary configured, retry against secondary before falling back to Rule-Engine-only |

### 8.6 AI Memory (FR-MEM)

| ID | Requirement | Acceptance Criteria |
|---|---|---|
| FR-MEM-01 | System detects trends from longitudinal logs (weight stagnation, consistent improvement, missed check-ins) | Scheduled job scans `weight_logs`/`checklist_items` per active `user_program`; writes `ai_memories` rows |
| FR-MEM-02 | AI Memory entries are summarized, structured, and scoped per user/program | `ai_memories.summary` + `.data` JSON; `memory_type` enum |
| FR-MEM-03 | AI Memory feeds into weekly review & program revision prompts | Included in prompt context per [07-Prompt-Engineering.md](07-Prompt-Engineering.md) |
| FR-MEM-04 | Old/low-relevance memories decay and are pruned from prompt context (not deleted) | `relevance_score` decays on a schedule; only top-N by relevance included per prompt to control token cost |

### 8.7 Program Generation & Management (FR-PROG)

| ID | Requirement | Acceptance Criteria |
|---|---|---|
| FR-PROG-01 | User can run multiple programs (concurrently across categories, or sequentially) | `user_programs` many-per-user; no unique constraint on (user_id) |
| FR-PROG-02 | Program categories ship extensible via `programs` table, not hardcoded | v1 ships "Diet & Transformasi 90 Hari"; category taxonomy supports bulking/marathon/disease-management/etc. |
| FR-PROG-03 | Program generation pipeline: Goal → Rule Engine → AI Analyze → Generate (meal/workout/checklist/sleep/water/habits) → persist, not shown live | `GenerateProgramJob` queued; results saved before any UI render — no direct-to-screen AI streaming of authoritative plan data |
| FR-PROG-04 | AI may auto-adjust program parameters over time (e.g., push-up reps 10→20) within Rule-Engine-defined bounds | Adjustment written as `ai_recommendations` with `status=applied` automatically only if within bounds; otherwise `status=pending` for Coach approval |
| FR-PROG-05 | Weekly plan auto-generated summarizing the week + AI review | `weekly_plans` row per program per week |
| FR-PROG-06 | Daily check-in updates checklist/task completion and feeds AI Memory | `checklist_items.is_checked`, `daily_tasks.is_completed` |

### 8.8 Progress Tracking & Health Score (FR-PROG-TRACK)

| ID | Requirement | Acceptance Criteria |
|---|---|---|
| FR-TRK-01 | Log weight, waist, body fat, sleep, water, progress photos | One table per metric, unique per user/day where applicable (see schema) |
| FR-TRK-02 | Health Score (0–100) computed daily from weighted components (BMI, waist, sleep, water, activity, weight trend, consistency, nutrition adherence, disease management) | `health_scores` row/day; weighting documented in [08-Knowledge-Base.md](08-Knowledge-Base.md) §5 |
| FR-TRK-03 | AI generates a natural-language explanation of score change | `health_scores.explanation`, generated via `analyze()` capability |
| FR-TRK-04 | Charts: weight trend, waist trend, health score trend, checklist adherence | Frontend charts consuming time-series endpoints (see API spec) |

### 8.9 Coach Module (FR-COACH)

| ID | Requirement | Acceptance Criteria |
|---|---|---|
| FR-COACH-01 | Coach assigned 1..N Members | `coach_members` |
| FR-COACH-02 | Coach dashboard lists members, statuses, alerts (e.g., stagnation flagged by AI Memory) | Dashboard queries `ai_memories` where `memory_type=concern` |
| FR-COACH-03 | Coach can view/edit/override AI-generated plans before or after they apply | Coach edits create a new plan version; `ai_recommendations.reviewed_by` set |
| FR-COACH-04 | Coach ↔ Member chat | `conversations`/`messages`, `type=coach_member` |
| FR-COACH-05 | Member can rate/review Coach | `reviews`, one per (coach, member) pair |
| FR-COACH-06 | Private coach notes not visible to member unless flagged | `coach_notes.is_visible_to_member` |

### 8.10 Knowledge Base (FR-KB)

| ID | Requirement | Acceptance Criteria |
|---|---|---|
| FR-KB-01 | Indonesian food database with macros, GI, tags | `kb_foods`; seed data referenced from TKPI or equivalent public source |
| FR-KB-02 | Exercise database with MET values, difficulty, contraindications | `kb_exercises` |
| FR-KB-03 | Disease reference data with dietary/exercise implications | `kb_diseases` |
| FR-KB-04 | Nutrition articles & FAQ, admin-editable, used as AI grounding context (RAG-lite) | `kb_nutrition_articles`, `kb_faqs` |
| FR-KB-05 | Knowledge Base content is queried, never hallucinated — AI prompts explicitly cite KB facts | Prompt Builder injects KB lookups as prompt context, see [07-Prompt-Engineering.md](07-Prompt-Engineering.md) |

### 8.11 Prompt Builder (FR-PB)

| ID | Requirement | Acceptance Criteria |
|---|---|---|
| FR-PB-01 | Prompts are assembled programmatically from structured data, never hand-typed by end users | `PromptBuilderService` composes `ai_prompt_templates.template` + variables |
| FR-PB-02 | Every prompt enforces JSON-only output with an explicit schema | `response_schema` column validated post-response |
| FR-PB-03 | Prompt templates are versioned and admin-editable | `ai_prompt_templates.version`; changing a template does not retroactively alter historic `ai_request_logs` |

### 8.12 Admin (FR-ADMIN)

| ID | Requirement | Acceptance Criteria |
|---|---|---|
| FR-ADMIN-01 | Manage users/roles/permissions | CRUD UI |
| FR-ADMIN-02 | Manage AI providers/models, set platform defaults | CRUD UI over `ai_providers`/`ai_models` |
| FR-ADMIN-03 | Manage Rule Engine rules | CRUD UI over `rule_engine_rules` with condition/action JSON editor |
| FR-ADMIN-04 | Manage Knowledge Base content | CRUD UI over all `kb_*` tables |
| FR-ADMIN-05 | View platform analytics (active users, AI cost, program completion) | Dashboard aggregating `ai_request_logs`, `user_programs`, `health_scores` |

### 8.13 Notifications & Reminders (FR-NOTIF)

| ID | Requirement | Acceptance Criteria |
|---|---|---|
| FR-NOTIF-01 | Scheduled reminders (water, meal, workout, check-in, medication) | `reminders` + Laravel Scheduler + Queue dispatch |
| FR-NOTIF-02 | In-app + (v1.1) push/WhatsApp/email delivery channels | v1 ships in-app + email; channel abstraction ready for push |

### 8.14 Achievements (FR-ACH)

| ID | Requirement | Acceptance Criteria |
|---|---|---|
| FR-ACH-01 | Criteria-based achievements (streaks, milestones) | `achievements.criteria` JSON evaluated by scheduled job |
| FR-ACH-02 | Member sees earned achievements on profile/dashboard | `user_achievements` |

## 9. Non-Functional Requirements

| Category | Requirement |
|---|---|
| Performance | API P95 response time < 400ms excluding AI-bound calls; AI-bound calls must be async (queued) with UI polling/websocket status, never a blocking HTTP request > 10s |
| Reliability | AI provider outage must not break core app usage — Rule Engine + last-known-good plan always available offline of AI |
| Security | API keys encrypted at rest; RBAC enforced server-side on every endpoint; OWASP Top 10 mitigations (see [04-Architecture.md](04-Architecture.md) §8) |
| Scalability | Stateless API workers behind load balancer; queue workers horizontally scalable; schema multi-tenant-ready (see Architecture §7) |
| Data privacy | Health data classified as sensitive; encrypted at rest for medical fields where feasible; audit trail via `activity_logs` |
| Internationalization | `users.locale`; UI copy externalized; Knowledge Base content supports `name_local` for Indonesian terms |
| Accessibility | WCAG 2.1 AA target for core flows (onboarding, dashboard, check-in) |
| Observability | Structured logging, `ai_request_logs` for AI cost/latency, error tracking (Sentry-class tool) |

## 10. User Roles & Permission Model

| Role | Description | Representative permissions |
|---|---|---|
| Admin | Platform operator | `*.manage` across all modules |
| Coach | Health professional managing assigned Members | `member.view`, `program.review`, `chat.send`, `note.manage` (scoped to assigned members only) |
| Member | End user | `own_profile.manage`, `own_program.view`, `checkin.submit`, `chat.send` (to own coach) |

Enforcement detail in [05-API-Specification.md](05-API-Specification.md) §3 (auth & scoping).

## 11. Key User Flows (summary — full wireframes in [09-UI-UX-Wireframe.md](09-UI-UX-Wireframe.md))

1. **Register → Onboarding Wizard (~55 questions) → AI Analyze → Program Generated → Dashboard**
2. **Daily loop:** Dashboard → Check-in (meals eaten, workout done, water, sleep) → Checklist updates → (nightly job) AI Memory scan → (weekly) Weekly Review generated → Program auto-adjusts within bounds or queues Coach approval
3. **Coach loop:** Coach Dashboard → Member list with AI-flagged concerns → Review member's plan/progress → Chat or override plan
4. **Admin loop:** Admin Panel → Knowledge Base / Rule Engine / AI Provider config → Analytics

## 12. Open Questions (need product decision before/near launch)

1. **AI cost model:** Does the platform provide a default shared AI provider/key (metered, cost passed into subscription pricing), or is bring-your-own-key mandatory for all tiers? This affects `plans.features` design and margin model.
2. **Coach assignment:** Automatic (round-robin/load-balanced) vs. manual admin assignment vs. member-selectable from a shortlist?
3. **Medical liability posture:** Does the product need a licensed-nutritionist sign-off step for disease-management programs (diabetes/hypertension/etc.), or is it positioned as wellness guidance with a disclaimer? Affects onboarding copy and Coach workflow requirements.
4. **Data residency:** Any requirement to keep Indonesian user health data within Indonesia-based infrastructure (relevant given PDP Law / UU PDP compliance)?

## 13. Compliance Note

Health data handled here is sensitive personal data under Indonesia's UU PDP (Personal Data Protection Law) and comparable frameworks if the product expands internationally. Recommend: explicit consent capture at registration, right-to-export/delete flows (map to `users` soft-delete + data export job), and a documented data retention policy before public launch. This PRD flags the requirement; legal review is out of scope for this document.

## 14. Glossary

| Term | Meaning |
|---|---|
| Rule Engine | Deterministic condition→action evaluator using data-defined rules, not AI |
| Knowledge Base (KB) | Curated reference data (foods, exercises, diseases, articles) the AI is grounded against |
| AI Memory | Persisted, structured longitudinal observations about a user's progress, used as AI prompt context |
| Health Score | 0–100 composite score summarizing a user's current health trajectory |
| Program | An instance of a `program` template assigned to a user (e.g., a specific user's 90-day diet run) |
| Prompt Builder | Service that programmatically assembles AI prompts from structured data instead of hand-written text |
