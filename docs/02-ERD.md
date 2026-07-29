# Entity Relationship Diagram (ERD)
## AI Personal Health Coach — 57 Tables Across 11 Modules

| | |
|---|---|
| Document | 02-ERD.md |
| Source of truth | [database-schema/mysql.sql](../database-schema/mysql.sql) — if this document and the SQL ever disagree, the SQL wins |
| Companion | [database-schema/erd.dbml](../database-schema/erd.dbml) — import at dbdiagram.io for an interactive visual diagram |
| Field-level detail | [03-Database-Dictionary.md](03-Database-Dictionary.md) |

This document shows the schema as diagrams grouped by module, since a single 57-table diagram is unreadable. Each diagram is valid Mermaid (`erDiagram`) and renders directly in GitHub/VS Code preview. Cross-module foreign keys are listed in §12.

---

## 1. Core / Auth / RBAC

```mermaid
erDiagram
    ROLES ||--o{ USERS : "has many"
    ROLES ||--o{ ROLE_PERMISSIONS : "has many"
    PERMISSIONS ||--o{ ROLE_PERMISSIONS : "has many"

    ROLES {
        bigint id PK
        string name
        string label
    }
    PERMISSIONS {
        bigint id PK
        string name
        string module
    }
    ROLE_PERMISSIONS {
        bigint role_id FK
        bigint permission_id FK
    }
    USERS {
        bigint id PK
        bigint role_id FK
        string name
        string email
        string status
        timestamp email_verified_at
        timestamp onboarding_completed_at
    }
```

---

## 2. AI Provider Layer

```mermaid
erDiagram
    AI_PROVIDERS ||--o{ AI_MODELS : "offers"
    AI_PROVIDERS ||--o{ USER_AI_SETTINGS : "configured by"
    AI_MODELS ||--o{ USER_AI_SETTINGS : "selected as"
    USERS ||--o{ USER_AI_SETTINGS : "configures"
    USERS ||--o{ AI_MEMORIES : "accumulates"
    USERS ||--o{ AI_RECOMMENDATIONS : "receives"
    USERS ||--o{ AI_REQUEST_LOGS : "generates"
    AI_PROVIDERS ||--o{ AI_REQUEST_LOGS : "logged for"
    AI_MODELS ||--o{ AI_REQUEST_LOGS : "logged for"

    AI_PROVIDERS {
        bigint id PK
        string name
        string slug
        string type "cloud|local"
        string driver_class
    }
    AI_MODELS {
        bigint id PK
        bigint provider_id FK
        string name
        string model_key
        boolean supports_json_mode
    }
    USER_AI_SETTINGS {
        bigint id PK
        bigint user_id FK
        bigint provider_id FK
        bigint model_id FK
        text api_key_encrypted
        boolean is_default
    }
    AI_PROMPT_TEMPLATES {
        bigint id PK
        string key
        text template
        json response_schema
        int version
    }
    RULE_ENGINE_RULES {
        bigint id PK
        string category
        json condition
        json action
        int priority
    }
    AI_MEMORIES {
        bigint id PK
        bigint user_id FK
        bigint user_program_id FK
        string memory_type
        string summary
        json data
    }
    AI_RECOMMENDATIONS {
        bigint id PK
        bigint user_id FK
        bigint user_program_id FK
        string type
        json content
        string status
    }
    AI_REQUEST_LOGS {
        bigint id PK
        bigint user_id FK
        bigint provider_id FK
        bigint model_id FK
        string purpose
        string status
    }
```

> Note: `AI_PROMPT_TEMPLATES` and `RULE_ENGINE_RULES` are standalone reference tables (no inbound FK) consumed by application services, not joined directly — shown here for module cohesion.

---

## 3. Knowledge Base

```mermaid
erDiagram
    KB_DISEASES {
        bigint id PK
        string name
        string slug
        json dietary_restrictions
        json recommended_exercise
        json contraindicated_exercise
    }
    KB_FOODS {
        bigint id PK
        string name
        string name_local
        string category
        decimal calories
        decimal protein_g
        decimal carbs_g
        decimal fat_g
        int glycemic_index
        json tags
    }
    KB_EXERCISES {
        bigint id PK
        string name
        string category
        decimal met_value
        string difficulty
        json contraindications
    }
    KB_NUTRITION_ARTICLES {
        bigint id PK
        string title
        string slug
        text content
    }
    KB_FAQS {
        bigint id PK
        string question
        text answer
        string category
    }
```

`kb_diseases` is referenced by `user_diseases` (Module 4) and by `kb_exercises.contraindications` / `kb_foods.tags` at the application layer (slug lookup, not enforced FK, since these are array fields).

---

## 4. Health Profile

```mermaid
erDiagram
    USERS ||--|| HEALTH_PROFILES : "has one"
    USERS ||--|| LIFESTYLE_PROFILES : "has one"
    USERS ||--o{ USER_DISEASES : "has many"
    USERS ||--o{ USER_ALLERGIES : "has many"
    USERS ||--o{ USER_MEDICATIONS : "has many"
    USERS ||--o{ BODY_MEASUREMENTS : "logs many"
    KB_DISEASES ||--o{ USER_DISEASES : "diagnosed as"

    HEALTH_PROFILES {
        bigint id PK
        bigint user_id FK
        date date_of_birth
        string gender
        decimal height_cm
        decimal bmi
        decimal bmr
        decimal tdee
    }
    LIFESTYLE_PROFILES {
        bigint id PK
        bigint user_id FK
        string activity_level
        decimal avg_sleep_hours
        string diet_pattern
        string smoking_status
        string alcohol_frequency
    }
    USER_DISEASES {
        bigint id PK
        bigint user_id FK
        bigint kb_disease_id FK
        date diagnosed_at
        string status
    }
    USER_ALLERGIES {
        bigint id PK
        bigint user_id FK
        string allergen
        string severity
    }
    USER_MEDICATIONS {
        bigint id PK
        bigint user_id FK
        string name
        string dosage
    }
    BODY_MEASUREMENTS {
        bigint id PK
        bigint user_id FK
        date measured_at
        decimal weight_kg
        decimal waist_cm
        decimal body_fat_pct
    }
```

---

## 5. Onboarding

```mermaid
erDiagram
    USERS ||--o{ ONBOARDING_SESSIONS : "starts"
    ONBOARDING_SESSIONS ||--o{ ONBOARDING_ANSWERS : "contains"
    ONBOARDING_QUESTIONS ||--o{ ONBOARDING_ANSWERS : "answered via"

    ONBOARDING_QUESTIONS {
        bigint id PK
        int step
        string category
        string question_text
        string input_type
        json options
        boolean is_required
    }
    ONBOARDING_SESSIONS {
        bigint id PK
        bigint user_id FK
        string status
        int current_step
    }
    ONBOARDING_ANSWERS {
        bigint id PK
        bigint onboarding_session_id FK
        bigint question_id FK
        json answer_value
    }
```

---

## 6. Program (the core domain)

```mermaid
erDiagram
    USERS ||--o{ USER_PROGRAMS : "runs"
    PROGRAMS ||--o{ USER_PROGRAMS : "instantiated as"
    USERS ||--o{ USER_PROGRAMS : "coaches (optional)"
    USER_PROGRAMS ||--o{ PROGRAM_GOALS : "has"
    USER_PROGRAMS ||--o{ WEEKLY_PLANS : "has many"
    USER_PROGRAMS ||--o{ DAILY_TASKS : "has many"
    USER_PROGRAMS ||--o{ MEAL_PLANS : "has many"
    USER_PROGRAMS ||--o{ WORKOUT_PLANS : "has many"
    USER_PROGRAMS ||--o{ CHECKLIST_ITEMS : "has many"
    MEAL_PLANS ||--o{ MEAL_PLAN_ITEMS : "contains"
    KB_FOODS ||--o{ MEAL_PLAN_ITEMS : "referenced by"
    WORKOUT_PLANS ||--o{ WORKOUT_PLAN_ITEMS : "contains"
    KB_EXERCISES ||--o{ WORKOUT_PLAN_ITEMS : "referenced by"

    PROGRAMS {
        bigint id PK
        string name
        string slug
        string category
        int default_duration_days
    }
    USER_PROGRAMS {
        bigint id PK
        bigint user_id FK
        bigint program_id FK
        bigint coach_id FK
        string status
        date start_date
        date end_date
        string created_by
    }
    PROGRAM_GOALS {
        bigint id PK
        bigint user_program_id FK
        string goal_type
        decimal target_weight_kg
        date target_date
    }
    WEEKLY_PLANS {
        bigint id PK
        bigint user_program_id FK
        int week_number
        text ai_summary
        json ai_review
    }
    DAILY_TASKS {
        bigint id PK
        bigint user_program_id FK
        date task_date
        string task_type
        boolean is_completed
        string source
    }
    MEAL_PLANS {
        bigint id PK
        bigint user_program_id FK
        date plan_date
        string meal_type
        decimal total_calories
        string source
    }
    MEAL_PLAN_ITEMS {
        bigint id PK
        bigint meal_plan_id FK
        bigint kb_food_id FK
        decimal portion
        decimal calories
    }
    WORKOUT_PLANS {
        bigint id PK
        bigint user_program_id FK
        date plan_date
        string workout_type
        string intensity
        string source
    }
    WORKOUT_PLAN_ITEMS {
        bigint id PK
        bigint workout_plan_id FK
        bigint kb_exercise_id FK
        int sets
        int reps
    }
    CHECKLIST_ITEMS {
        bigint id PK
        bigint user_program_id FK
        date item_date
        string label
        boolean is_checked
    }
```

`reminders` (per-user, not per-program) is covered in the Database Dictionary but omitted from this diagram to avoid clutter — it FKs to `users.id` only.

---

## 7. Progress Tracking

```mermaid
erDiagram
    USERS ||--o{ WEIGHT_LOGS : "logs"
    USERS ||--o{ WAIST_LOGS : "logs"
    USERS ||--o{ BODY_FAT_LOGS : "logs"
    USERS ||--o{ PROGRESS_PHOTOS : "uploads"
    USERS ||--o{ WATER_INTAKE_LOGS : "logs"
    USERS ||--o{ SLEEP_LOGS : "logs"
    USERS ||--o{ HEALTH_SCORES : "scored"

    WEIGHT_LOGS {
        bigint id PK
        bigint user_id FK
        date logged_at
        decimal weight_kg
    }
    WAIST_LOGS {
        bigint id PK
        bigint user_id FK
        date logged_at
        decimal waist_cm
    }
    BODY_FAT_LOGS {
        bigint id PK
        bigint user_id FK
        date logged_at
        decimal body_fat_pct
    }
    PROGRESS_PHOTOS {
        bigint id PK
        bigint user_id FK
        date logged_at
        string angle
        string photo_path
    }
    WATER_INTAKE_LOGS {
        bigint id PK
        bigint user_id FK
        date logged_at
        int amount_ml
    }
    SLEEP_LOGS {
        bigint id PK
        bigint user_id FK
        date logged_at
        decimal sleep_hours
    }
    HEALTH_SCORES {
        bigint id PK
        bigint user_id FK
        date scored_at
        decimal score
        json breakdown
    }
```

---

## 8. Coach

```mermaid
erDiagram
    USERS ||--|| COACH_PROFILES : "has one (if coach)"
    USERS ||--o{ COACH_MEMBERS : "is coach in"
    USERS ||--o{ COACH_MEMBERS : "is member in"
    USERS ||--o{ COACH_NOTES : "authors"
    USERS ||--o{ CONVERSATIONS : "participates as member"
    USERS ||--o{ CONVERSATIONS : "participates as coach"
    CONVERSATIONS ||--o{ MESSAGES : "contains"
    USERS ||--o{ REVIEWS : "writes"

    COACH_PROFILES {
        bigint id PK
        bigint user_id FK
        string specialization
        decimal rating_avg
    }
    COACH_MEMBERS {
        bigint id PK
        bigint coach_id FK
        bigint member_id FK
        string status
    }
    COACH_NOTES {
        bigint id PK
        bigint coach_id FK
        bigint member_id FK
        text note
        boolean is_visible_to_member
    }
    CONVERSATIONS {
        bigint id PK
        string type
        bigint user_id FK
        bigint coach_id FK
    }
    MESSAGES {
        bigint id PK
        bigint conversation_id FK
        string sender_type
        text content
    }
    REVIEWS {
        bigint id PK
        bigint coach_id FK
        bigint member_id FK
        int rating
    }
```

---

## 9. Analytics / Gamification

```mermaid
erDiagram
    USERS ||--o{ USER_ACHIEVEMENTS : "earns"
    ACHIEVEMENTS ||--o{ USER_ACHIEVEMENTS : "earned by"
    USERS ||--o{ ACTIVITY_LOGS : "generates"

    ACHIEVEMENTS {
        bigint id PK
        string name
        json criteria
    }
    USER_ACHIEVEMENTS {
        bigint id PK
        bigint user_id FK
        bigint achievement_id FK
        timestamp earned_at
    }
    ACTIVITY_LOGS {
        bigint id PK
        bigint user_id FK
        string action
        string subject_type
        bigint subject_id
    }
```

---

## 10. Subscription / Billing

```mermaid
erDiagram
    USERS ||--o{ SUBSCRIPTIONS : "subscribes"
    PLANS ||--o{ SUBSCRIPTIONS : "subscribed as"
    SUBSCRIPTIONS ||--o{ PAYMENTS : "billed via"

    PLANS {
        bigint id PK
        string name
        decimal price
        string billing_cycle
        int max_programs
        boolean has_coach_access
    }
    SUBSCRIPTIONS {
        bigint id PK
        bigint user_id FK
        bigint plan_id FK
        string status
        timestamp starts_at
        timestamp ends_at
    }
    PAYMENTS {
        bigint id PK
        bigint subscription_id FK
        string provider
        decimal amount
        string status
    }
```

---

## 11. App Settings

```mermaid
erDiagram
    APP_SETTINGS {
        bigint id PK
        string key
        json value
    }
```

Standalone key-value store; no FKs.

---

## 12. Cross-Module Foreign Key Index

For quick lookup — every FK that crosses the module boundaries above:

| From table | Column | To table |
|---|---|---|
| `ai_memories` | `user_program_id` | `user_programs.id` |
| `ai_recommendations` | `user_program_id` | `user_programs.id` |
| `ai_recommendations` | `reviewed_by` | `users.id` |
| `user_diseases` | `kb_disease_id` | `kb_diseases.id` |
| `meal_plan_items` | `kb_food_id` | `kb_foods.id` |
| `workout_plan_items` | `kb_exercise_id` | `kb_exercises.id` |
| `user_programs` | `coach_id` | `users.id` |
| `coach_members` | `coach_id`, `member_id` | `users.id` (both) |
| `conversations` | `coach_id` | `users.id` |

## 13. Cardinality Notes

- `users` ↔ `health_profiles`, `users` ↔ `lifestyle_profiles`, `users` ↔ `coach_profiles`: **1:1** (a coach profile only exists for users with role = coach; enforced at application layer, not DB constraint, to keep `users` role-agnostic).
- `users` ↔ `user_programs`: **1:N** — a Member can run multiple programs concurrently (FR-PROG-01).
- `user_programs` ↔ `meal_plans`/`workout_plans`/`daily_tasks`/`checklist_items`: **1:N**, partitioned by date — these are the day-by-day generated content of a program.
- `kb_foods`/`kb_exercises` ↔ plan items: **1:N**, nullable — AI may suggest an item not yet catalogued in the KB (`custom_name` used instead), which is a signal for Admin to add it to the KB (see [08-Knowledge-Base.md](08-Knowledge-Base.md) §6 curation loop).
