# API Specification
## AI Personal Health Coach — REST API

| | |
|---|---|
| Document | 05-API-Specification.md |
| Base path | `/api/v1` |
| Auth | Laravel Sanctum (SPA cookie session for the Inertia app; Bearer token for future native clients) |
| Format | JSON request/response; all list endpoints paginated |

## 1. Conventions

- **Versioning**: URI-versioned (`/api/v1/...`). Breaking changes ship as `/api/v2` with v1 maintained per the deprecation window in [10-Roadmap.md](10-Roadmap.md).
- **Auth header**: `Authorization: Bearer {token}` for token clients; cookie-based for the SPA (`withCredentials`).
- **Pagination**: `?page=1&per_page=20`, response includes `meta: { current_page, per_page, total, last_page }`.
- **Filtering/sorting**: `?filter[status]=active&sort=-created_at` (leading `-` = descending).
- **Standard success envelope**:
```json
{ "data": { }, "meta": { } }
```
- **Standard error envelope**:
```json
{ "error": { "code": "VALIDATION_ERROR", "message": "...", "fields": { "email": ["already taken"] } } }
```
- **HTTP status codes**: `200` OK, `201` Created, `204` No Content, `400` bad request, `401` unauthenticated, `403` forbidden (role/ownership), `404` not found, `422` validation, `429` rate-limited, `500` server error.
- **Role column** below: **M**ember, **C**oach, **A**dmin. Endpoints list roles that *can* call it; row-level ownership (e.g. a Member can only see their own data, a Coach only their assigned members) is enforced regardless of role.
- **Rate limiting**: auth endpoints throttled 5/min/IP; AI-triggering endpoints (`generate`, `chat`, `weekly-review`) throttled per-user per plan tier to control cost abuse.

## 2. Authentication

| Method | Endpoint | Roles | Description |
|---|---|---|---|
| POST | `/auth/register` | Public | Create Member account |
| POST | `/auth/login` | Public | Issue session/token |
| POST | `/auth/logout` | M/C/A | Revoke current session/token |
| POST | `/auth/forgot-password` | Public | Send reset link |
| POST | `/auth/reset-password` | Public | Consume reset token |
| GET | `/auth/me` | M/C/A | Current user + role + permissions |
| POST | `/auth/email/verify/{id}/{hash}` | M/C/A | Email verification callback |

## 3. Users, Roles & Permissions (Admin)

| Method | Endpoint | Roles | Description |
|---|---|---|---|
| GET | `/admin/users` | A | List/search users |
| POST | `/admin/users` | A | Create user (e.g. a Coach account) |
| GET | `/admin/users/{id}` | A | User detail |
| PATCH | `/admin/users/{id}` | A | Update user (status, role) |
| DELETE | `/admin/users/{id}` | A | Soft-delete user |
| GET | `/admin/roles` | A | List roles |
| GET | `/admin/permissions` | A | List permissions |
| PATCH | `/admin/roles/{id}/permissions` | A | Sync role→permission set |

## 4. Onboarding

| Method | Endpoint | Roles | Description |
|---|---|---|---|
| GET | `/onboarding/questions` | M | Ordered active question list (wizard content) |
| POST | `/onboarding/sessions` | M | Start (or resume) an onboarding session |
| GET | `/onboarding/sessions/current` | M | Current session + progress |
| POST | `/onboarding/sessions/{id}/answers` | M | Submit one step's answer(s) |
| POST | `/onboarding/sessions/{id}/complete` | M | Finalize → triggers `GenerateProgramJob` (FR-ONB-04) |
| GET | `/admin/onboarding/questions` | A | Manage question bank |
| POST | `/admin/onboarding/questions` | A | Create question |
| PATCH | `/admin/onboarding/questions/{id}` | A | Edit/reorder/deactivate |
| DELETE | `/admin/onboarding/questions/{id}` | A | Remove (blocked if answers exist — deactivate instead) |

## 5. Health Profile

| Method | Endpoint | Roles | Description |
|---|---|---|---|
| GET | `/profile/health` | M (own) / C (assigned) / A | Health profile + computed BMI/BMR/TDEE |
| PATCH | `/profile/health` | M | Update anthropometric baseline |
| GET | `/profile/lifestyle` | M/C/A | Lifestyle profile |
| PATCH | `/profile/lifestyle` | M | Update lifestyle answers post-onboarding |
| GET | `/profile/diseases` | M/C/A | User's disease list |
| POST | `/profile/diseases` | M | Add a disease (from KB) |
| DELETE | `/profile/diseases/{id}` | M | Remove/mark resolved |
| GET | `/profile/allergies` | M/C/A | |
| POST | `/profile/allergies` | M | |
| DELETE | `/profile/allergies/{id}` | M | |
| GET | `/profile/medications` | M/C/A | |
| POST | `/profile/medications` | M | |
| PATCH | `/profile/medications/{id}` | M | |
| DELETE | `/profile/medications/{id}` | M | |
| GET | `/profile/measurements` | M/C/A | Paginated body measurement history |
| POST | `/profile/measurements` | M | Log a full measurement snapshot |

## 6. Programs

| Method | Endpoint | Roles | Description |
|---|---|---|---|
| GET | `/programs/catalog` | M/C/A | Browsable program templates (`programs` table) |
| GET | `/user-programs` | M (own) / C (assigned members') / A | List a user's program runs |
| POST | `/user-programs` | M/C | Start a new program run (FR-PROG-01, multiple allowed) |
| GET | `/user-programs/{id}` | M/C/A | Program detail incl. goal, status |
| PATCH | `/user-programs/{id}` | M/C | Pause/cancel/update dates |
| POST | `/user-programs/{id}/regenerate` | M/C | Force AI to regenerate remaining plan (queues `GenerateProgramJob`) |
| GET | `/user-programs/{id}/goals` | M/C/A | |
| POST | `/user-programs/{id}/goals` | M/C | |
| GET | `/user-programs/{id}/weekly-plans` | M/C/A | |
| GET | `/user-programs/{id}/weekly-plans/{week}` | M/C/A | Includes `ai_review` |
| GET | `/user-programs/{id}/daily-tasks` | M/C/A | `?date=YYYY-MM-DD` |
| PATCH | `/daily-tasks/{id}` | M | Mark complete/incomplete |
| GET | `/user-programs/{id}/meal-plans` | M/C/A | `?date=` |
| GET | `/meal-plans/{id}` | M/C/A | Includes `meal_plan_items` |
| PATCH | `/meal-plans/{id}` | M/C | Manual override |
| GET | `/user-programs/{id}/workout-plans` | M/C/A | `?date=` |
| GET | `/workout-plans/{id}` | M/C/A | Includes `workout_plan_items` |
| PATCH | `/workout-plans/{id}` | M/C | Manual override |
| GET | `/user-programs/{id}/checklist` | M/C/A | `?date=` |
| PATCH | `/checklist-items/{id}` | M | Toggle checked |
| GET | `/reminders` | M | List own reminders |
| POST | `/reminders` | M | Create custom reminder |
| PATCH | `/reminders/{id}` | M | Edit/toggle active |
| DELETE | `/reminders/{id}` | M | |

## 7. Progress & Health Score

| Method | Endpoint | Roles | Description |
|---|---|---|---|
| GET | `/progress/weight` | M/C/A | Time-series, `?from=&to=` |
| POST | `/progress/weight` | M | Log today's weight |
| GET | `/progress/waist` | M/C/A | |
| POST | `/progress/waist` | M | |
| GET | `/progress/body-fat` | M/C/A | |
| POST | `/progress/body-fat` | M | |
| GET | `/progress/sleep` | M/C/A | |
| POST | `/progress/sleep` | M | |
| GET | `/progress/water` | M/C/A | Daily aggregate + entries |
| POST | `/progress/water` | M | Log a water entry (summed per day) |
| GET | `/progress/photos` | M (own) / C (if shared) | |
| POST | `/progress/photos` | M | Multipart upload |
| DELETE | `/progress/photos/{id}` | M | |
| GET | `/progress/health-score` | M/C/A | Latest + trend series, `?from=&to=` |
| GET | `/progress/health-score/today` | M/C/A | Today's score + `explanation` |

## 8. Coach

| Method | Endpoint | Roles | Description |
|---|---|---|---|
| GET | `/coach/members` | C | Assigned member list with status/alert flags |
| GET | `/coach/members/{id}` | C | Member detail (profile, program, progress) |
| POST | `/coach/members/{id}/notes` | C | Add note |
| GET | `/coach/members/{id}/notes` | C | List notes |
| PATCH | `/coach/notes/{id}` | C | Edit / toggle `is_visible_to_member` |
| GET | `/coach/members/{id}/recommendations` | C | `ai_recommendations` with `status=pending` awaiting approval |
| POST | `/coach/recommendations/{id}/approve` | C | Apply recommendation |
| POST | `/coach/recommendations/{id}/reject` | C | Reject with optional reason |
| GET | `/coach/dashboard` | C | Aggregated caseload metrics |
| POST | `/admin/coach-members` | A | Assign/reassign a member to a coach |
| GET | `/reviews` | C (own) / A | List reviews received |
| POST | `/user-programs/{id}/review` | M | Submit rating/comment for assigned coach |

## 9. Chat / Conversations

| Method | Endpoint | Roles | Description |
|---|---|---|---|
| GET | `/conversations` | M/C | List own conversations |
| POST | `/conversations` | M | Start AI assistant conversation (`type=ai_assistant`) |
| GET | `/conversations/{id}/messages` | M/C | Paginated messages |
| POST | `/conversations/{id}/messages` | M/C | Send message; if `type=ai_assistant`, triggers `chat()` AI capability async and appends AI reply |
| PATCH | `/conversations/{id}/read` | M/C | Mark read |

## 10. AI Settings

| Method | Endpoint | Roles | Description |
|---|---|---|---|
| GET | `/ai/providers` | M/C/A | Active providers + models (public config, no keys) |
| GET | `/ai/settings` | M | Own configured provider/model/key (key masked) |
| POST | `/ai/settings` | M | Set provider/model/API key/temperature |
| PATCH | `/ai/settings/{id}` | M | Update |
| DELETE | `/ai/settings/{id}` | M | Remove a configured provider |
| POST | `/ai/settings/{id}/set-default` | M | Mark as default |
| POST | `/ai/settings/{id}/test` | M | Fire a lightweight test call, return latency/success |
| GET | `/admin/ai/providers` | A | Manage provider catalog |
| POST | `/admin/ai/providers` | A | Add provider |
| PATCH | `/admin/ai/providers/{id}` | A | Edit/enable/disable |
| GET | `/admin/ai/models` | A | Manage model catalog |
| POST | `/admin/ai/models` | A | Add model under a provider |
| PATCH | `/admin/ai/models/{id}` | A | Edit pricing/context length/active state |
| GET | `/admin/ai/prompt-templates` | A | List templates |
| GET | `/admin/ai/prompt-templates/{key}` | A | Detail incl. `variables`, `response_schema` |
| PATCH | `/admin/ai/prompt-templates/{key}` | A | Edit template (bumps `version`) |
| GET | `/admin/ai/request-logs` | A | Paginated, filterable by user/provider/status |
| GET | `/admin/ai/cost-summary` | A | Aggregated cost dashboard data |

## 11. Rule Engine (Admin)

| Method | Endpoint | Roles | Description |
|---|---|---|---|
| GET | `/admin/rule-engine/rules` | A | List, filterable by `category` |
| POST | `/admin/rule-engine/rules` | A | Create rule |
| GET | `/admin/rule-engine/rules/{id}` | A | Detail |
| PATCH | `/admin/rule-engine/rules/{id}` | A | Edit condition/action/priority |
| DELETE | `/admin/rule-engine/rules/{id}` | A | Remove (soft — deactivate recommended over hard delete) |
| POST | `/admin/rule-engine/rules/{id}/test` | A | Dry-run a rule against a sample profile payload |

## 12. Knowledge Base

| Method | Endpoint | Roles | Description |
|---|---|---|---|
| GET | `/kb/foods` | M/C/A | Search/browse (`?q=&category=&tags[]=`) |
| GET | `/kb/foods/{id}` | M/C/A | |
| GET | `/kb/exercises` | M/C/A | Search/browse |
| GET | `/kb/exercises/{id}` | M/C/A | |
| GET | `/kb/diseases` | M/C/A | |
| GET | `/kb/diseases/{id}` | M/C/A | |
| GET | `/kb/articles` | M/C/A | Published nutrition articles |
| GET | `/kb/articles/{slug}` | M/C/A | |
| GET | `/kb/faqs` | M/C/A | |
| POST | `/admin/kb/foods` | A | Create |
| PATCH | `/admin/kb/foods/{id}` | A | Edit |
| DELETE | `/admin/kb/foods/{id}` | A | |
| POST | `/admin/kb/exercises` | A | Create |
| PATCH | `/admin/kb/exercises/{id}` | A | Edit |
| DELETE | `/admin/kb/exercises/{id}` | A | |
| POST | `/admin/kb/diseases` | A | Create |
| PATCH | `/admin/kb/diseases/{id}` | A | Edit |
| POST | `/admin/kb/articles` | A | Create |
| PATCH | `/admin/kb/articles/{id}` | A | Edit/publish toggle |
| POST | `/admin/kb/faqs` | A | Create |
| PATCH | `/admin/kb/faqs/{id}` | A | Edit/reorder |

## 13. Achievements

| Method | Endpoint | Roles | Description |
|---|---|---|---|
| GET | `/achievements` | M/C/A | Catalog |
| GET | `/profile/achievements` | M (own) / C (assigned) | Earned achievements |
| POST | `/admin/achievements` | A | Create |
| PATCH | `/admin/achievements/{id}` | A | Edit criteria |

## 14. Subscription / Billing

| Method | Endpoint | Roles | Description |
|---|---|---|---|
| GET | `/plans` | Public | Plan catalog |
| GET | `/subscription` | M | Own subscription status |
| POST | `/subscription/subscribe` | M | Start subscription (sandbox in v1, see [10-Roadmap.md](10-Roadmap.md)) |
| POST | `/subscription/cancel` | M | Cancel at period end |
| GET | `/subscription/payments` | M | Own payment history |
| GET | `/admin/subscriptions` | A | All subscriptions, filterable |
| GET | `/admin/plans` | A | Manage plans |
| POST | `/admin/plans` | A | Create plan |
| PATCH | `/admin/plans/{id}` | A | Edit |

## 15. Analytics (Admin)

| Method | Endpoint | Roles | Description |
|---|---|---|---|
| GET | `/admin/analytics/overview` | A | Active users, program completion rate, retention |
| GET | `/admin/analytics/ai-cost` | A | Cost by provider/model/time range |
| GET | `/admin/analytics/health-outcomes` | A | Aggregate health score trend across cohort |

## 16. AI-Triggering Endpoints (cross-cutting, higher latency)

These specifically invoke an `AIProviderInterface` capability (see [06-AI-Provider-Interface.md](06-AI-Provider-Interface.md)) and are always processed via queued job + async status, never synchronous:

| Method | Endpoint | Capability invoked | Roles |
|---|---|---|---|
| POST | `/user-programs/{id}/generate` | `generatePlan()` | M/C |
| GET | `/user-programs/{id}/generate/status` | — | M/C |
| POST | `/user-programs/{id}/weekly-review/generate` | `weeklyReview()` | System/Coach-triggered |
| GET | `/progress/health-score/today/explain` | `analyze()` | M/C/A |
| POST | `/conversations/{id}/messages` (async reply) | `chat()` | M/C |
| GET | `/daily-motivation` | `dailyMotivation()` | M |
| POST | `/meal-plans/{id}/suggest-alternative` | `mealSuggestion()` | M |
| POST | `/workout-plans/{id}/suggest-alternative` | `workoutSuggestion()` | M |

## 17. Error Codes Reference

| Code | HTTP | Meaning |
|---|---|---|
| `VALIDATION_ERROR` | 422 | Request body failed validation |
| `UNAUTHENTICATED` | 401 | Missing/invalid session or token |
| `FORBIDDEN` | 403 | Authenticated but lacks permission or ownership |
| `NOT_FOUND` | 404 | Resource doesn't exist or isn't visible to caller |
| `RATE_LIMITED` | 429 | Throttle exceeded |
| `AI_PROVIDER_ERROR` | 502 | Upstream AI provider failed after retries |
| `AI_RESPONSE_INVALID` | 502 | AI response failed JSON-schema validation after retries; Rule-Engine fallback was used instead — not necessarily a user-facing failure, but logged |
| `PLAN_LIMIT_REACHED` | 403 | `plans.max_programs` exceeded for the user's subscription tier |
