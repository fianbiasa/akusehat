# AI Provider Interface Specification

| | |
|---|---|
| Document | 06-AI-Provider-Interface.md |
| Related | [04-Architecture](04-Architecture.md) §4 · [07-Prompt-Engineering](07-Prompt-Engineering.md) |

## 1. Design Principle

> AI is not the brain of the application. It is a swappable reasoning/language module that receives ground truth (Rule Engine output + Knowledge Base facts + AI Memory) as input and must return structured JSON as output.

Every supported provider (OpenAI, Claude, Groq, Gemini, Ollama, LM Studio) implements the **same** PHP interface. `AIGatewayService` never calls a provider SDK directly — it resolves an implementation of `AIProviderInterface` at runtime based on `user_ai_settings` (or the platform default from `app_settings`), so switching providers is a configuration change, not a deployment.

## 2. The Interface Contract

```php
namespace App\Services\AI\Contracts;

interface AIProviderInterface
{
    /**
     * General-purpose structured analysis (e.g. Health Score explanation,
     * onboarding baseline narrative).
     * @param array $context  Structured input — see §4 per-capability schemas
     * @return array          Decoded JSON response, already schema-validated
     */
    public function analyze(array $context): array;

    /**
     * Free-form conversational turn (Member <-> AI assistant, or Coach
     * review assistant). Still returns structured JSON (message + optional
     * suggested actions), never raw markdown/HTML.
     */
    public function chat(array $messages, array $context = []): array;

    /**
     * Full program generation: meal plan, workout plan, checklist, sleep/
     * water targets, habits — for a new or regenerating program.
     */
    public function generatePlan(array $context): array;

    /** Weekly progress summary + next-week adjustments. */
    public function weeklyReview(array $context): array;

    /** Short motivational message, personalized to recent progress. */
    public function dailyMotivation(array $context): array;

    /** Alternative meal suggestion respecting the same macro/KB constraints. */
    public function mealSuggestion(array $context): array;

    /** Alternative workout suggestion respecting the same constraints. */
    public function workoutSuggestion(array $context): array;
}
```

Every method:
1. Receives a `$context` array already assembled by `PromptBuilderService` (never raw user text — see [07-Prompt-Engineering.md](07-Prompt-Engineering.md) for how `$context` is built from `ai_prompt_templates` + variables).
2. Internally translates `$context` into the provider's native request format (chat completion messages, generation config, etc.), requesting JSON-mode/structured output where the provider supports it natively (OpenAI, Gemini) and falling back to strict prompt-enforced JSON + a parser for providers without native JSON mode (Groq, some Ollama models).
3. Returns a plain PHP array decoded from the provider's JSON response — never the provider SDK's response object — so `AIResponseProcessor` and calling services are provider-agnostic.
4. Throws a normalized `AIProviderException` (with `->isRetryable()`, `->isTimeout()`) on failure, letting `AIGatewayService` apply the same retry/failover policy regardless of which provider threw it.

## 3. Concrete Adapters

| Class | Provider | Notes |
|---|---|---|
| `App\Services\AI\Providers\OpenAIProvider` | OpenAI | Uses `response_format: {type: "json_object"}` (or `json_schema` where available) |
| `App\Services\AI\Providers\ClaudeProvider` | Anthropic Claude | Uses tool-use / forced-JSON-via-prefill pattern; validates against `response_schema` post-hoc |
| `App\Services\AI\Providers\GroqProvider` | Groq (Llama-family, fast inference) | Prompt-enforced JSON; no native JSON mode assumed — stricter prompt + regex/braces extraction fallback |
| `App\Services\AI\Providers\GeminiProvider` | Google Gemini | Uses `responseMimeType: "application/json"` + `responseSchema` |
| `App\Services\AI\Providers\OllamaProvider` | Ollama (local) | `base_url` from `ai_providers.base_url`; `format: "json"` request param where the local model supports it |
| `App\Services\AI\Providers\LMStudioProvider` | LM Studio (local, OpenAI-compatible server) | Reuses the OpenAI wire format against a local `base_url` |

Each adapter is registered in `AIProviderServiceProvider` and resolved by `ai_providers.driver_class`, so adding a 7th provider is: (1) implement the interface, (2) add an `ai_providers` row pointing at the new class, (3) no other code changes.

## 4. `$context` and Response Shapes Per Capability

All shapes are formalized as JSON Schema in `ai_prompt_templates.response_schema` per `key`; the tables below are the human-readable summary.

### 4.1 `generatePlan`

**Input `$context` includes:** `user_profile` (age, gender, height, weight, BMI/BMR/TDEE), `rule_engine_output` (calorie_target, macro_split, workout_level, water_target_ml, restrictions[]), `kb_context` (candidate foods/exercises matching restrictions), `ai_memory_context` (relevant recent memories), `program_goal`.

**Output:**
```json
{
  "summary": "string",
  "meal_plan": [
    { "meal_type": "breakfast", "items": [{"name":"string","portion":1,"calories":0}], "total_calories": 0 }
  ],
  "workout_plan": [
    { "type": "cardio", "exercises": [{"name":"string","sets":3,"reps":12}], "duration_minutes": 30, "intensity": "low" }
  ],
  "daily_tasks": [ {"task_type":"water","title":"string"} ],
  "water_target_ml": 2000,
  "sleep_target_hours": 7.5,
  "motivation": "string"
}
```

### 4.2 `weeklyReview`

**Input adds:** 7-day logs (weight, checklist completion %, workout adherence), prior week's plan.

**Output:**
```json
{
  "summary": "string",
  "trend": "improving | stagnant | declining",
  "adjustments": [
    { "type": "meal_adjustment | workout_adjustment | habit", "detail": "string", "auto_applicable": true }
  ],
  "motivation": "string"
}
```
`auto_applicable` is advisory from the AI; the authoritative decision on whether it's within bounds still runs through `RuleEngineService` server-side before `RecommendationApplierService` sets `ai_recommendations.status = applied` vs `pending`.

### 4.3 `analyze` (Health Score explanation example)

**Input adds:** `health_score_breakdown` (component scores).

**Output:**
```json
{ "summary": "string", "explanation": "string", "key_factors": ["string"] }
```

### 4.4 `dailyMotivation`

**Output:** `{ "message": "string" }`

### 4.5 `mealSuggestion` / `workoutSuggestion`

**Output (meal):** `{ "alternatives": [{"name":"string","calories":0,"reason":"string"}] }`
**Output (workout):** `{ "alternatives": [{"name":"string","sets":0,"reps":0,"reason":"string"}] }`

### 4.6 `chat`

**Input:** `messages` (role/content array, prior conversation turns), `$context` (profile summary, current program state, relevant KB snippets).

**Output:**
```json
{ "reply": "string", "suggested_actions": [{"type":"string","label":"string","payload":{}}] }
```
`suggested_actions` lets the AI propose e.g. "log today's weight" without executing it — the frontend renders it as a tappable suggestion, keeping the AI from silently mutating data via chat.

## 5. Validation, Retry & Fallback Policy

Implemented in `AIResponseProcessor`, applied identically regardless of provider:

1. Decode response as JSON. If decode fails → retry #1 with an appended corrective instruction ("Your previous response was not valid JSON. Return ONLY valid JSON matching the schema.").
2. If decoded but fails `response_schema` validation → retry #2 with the validation errors included in the corrective prompt.
3. If still failing after 2 retries → log `ai_request_logs.status = invalid_json`, return the **last known-good Rule-Engine-only output** for that capability (never surface broken AI output to the UI), and create an `ai_recommendations` row with `status = expired` for audit visibility to Admin.
4. If the provider call itself errors/times out (not a JSON problem) → `AIGatewayService` checks whether the user has a secondary `user_ai_settings` row; if so, retries once against the secondary provider before falling back to Rule-Engine-only (FR-AI-06).

## 6. Cost & Usage Metering

Every call populates `ai_request_logs` with `prompt_tokens`, `completion_tokens`, and `estimated_cost` (computed from `ai_models.input_cost_per_1k` / `output_cost_per_1k`). Local providers (Ollama/LM Studio) log token counts but `estimated_cost = 0`. This log is the sole source for the Admin cost dashboard ([05-API-Specification.md](05-API-Specification.md) §10) and for any future usage-based billing tier.

## 7. Provider Capability Matrix

| Provider | Native JSON mode | Typical use case | Notes |
|---|---|---|---|
| OpenAI | Yes | Default cloud option, broad reliability | |
| Claude | Partial (tool-use pattern) | Strong reasoning/explanation quality (weekly reviews, motivation) | |
| Groq | No (prompt-enforced) | Low-latency chat, cheap high-volume calls | Fastest inference; use for `dailyMotivation`/`chat` where cost/speed matter most |
| Gemini | Yes | Alternative cloud option, competitive pricing | |
| Ollama | Model-dependent | Privacy-sensitive / offline deployments, zero marginal cost | Requires the user (or platform) to host a model server |
| LM Studio | Yes (OpenAI-compatible) | Local desktop testing during development | Primarily a dev/QA convenience |

## 8. Settings UI Contract (Member-facing)

Maps directly to `user_ai_settings` (see [03-Database-Dictionary.md](03-Database-Dictionary.md)):

```
Settings → AI Provider
  ( ) OpenAI      API Key: [__________]   Model: [GPT-5.5 ▾]
  ( ) Claude      API Key: [__________]   Model: [Claude Sonnet ▾]
  (•) Groq        API Key: [__________]   Model: [Llama 4 ▾]
  ( ) Gemini      API Key: [__________]   Model: [Gemini 2.5 ▾]
  ( ) Ollama      Base URL: [__________]  Model: [llama3:8b ▾]
  ( ) LM Studio   Base URL: [__________]  Model: [local-model ▾]

  [Test Connection]   [Set as Default]
```
Full wireframe in [09-UI-UX-Wireframe.md](09-UI-UX-Wireframe.md) §6.
