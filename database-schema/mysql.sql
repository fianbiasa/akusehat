-- =====================================================================
-- AI PERSONAL HEALTH COACH — DATABASE SCHEMA
-- Engine: MySQL 8.0+ | Charset: utf8mb4 | Collation: utf8mb4_unicode_ci
-- Framework: Laravel 12 (migrations should mirror this file 1:1)
-- Generated as the canonical schema referenced by docs/02-ERD.md and
-- docs/03-Database-Dictionary.md. Do not let those documents drift
-- from this file — this file is the source of truth.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================================
-- MODULE 01 — CORE / AUTH / RBAC
-- =====================================================================

CREATE TABLE roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,          -- admin | coach | member
    label VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,         -- e.g. program.manage, member.view
    module VARCHAR(50) NOT NULL,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE role_permissions (
    role_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(30) NULL,
    password VARCHAR(255) NOT NULL,
    avatar_path VARCHAR(255) NULL,
    timezone VARCHAR(50) NOT NULL DEFAULT 'Asia/Jakarta',
    locale VARCHAR(10) NOT NULL DEFAULT 'id',
    status ENUM('active','suspended','pending') NOT NULL DEFAULT 'pending',
    email_verified_at TIMESTAMP NULL,
    onboarding_completed_at TIMESTAMP NULL,
    last_login_at TIMESTAMP NULL,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Standard Laravel/Sanctum framework tables (password_reset_tokens, sessions,
-- personal_access_tokens, cache, jobs, failed_jobs, notifications) are
-- provisioned via their stock migrations and intentionally omitted here —
-- see docs/04-Architecture.md §9 for the full list.

-- =====================================================================
-- MODULE 02 — AI PROVIDER LAYER
-- =====================================================================

CREATE TABLE ai_providers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,                 -- OpenAI, Claude, Groq, Gemini, Ollama, LM Studio
    slug VARCHAR(50) NOT NULL UNIQUE,
    type ENUM('cloud','local') NOT NULL DEFAULT 'cloud',
    base_url VARCHAR(255) NULL,                -- override for Ollama/LM Studio/self-hosted
    driver_class VARCHAR(150) NOT NULL,         -- e.g. App\Services\AI\Providers\OpenAIProvider
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ai_models (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,                -- display name, e.g. "Claude Sonnet"
    model_key VARCHAR(100) NOT NULL,           -- API model identifier
    context_length INT UNSIGNED NULL,
    supports_json_mode TINYINT(1) NOT NULL DEFAULT 1,
    input_cost_per_1k DECIMAL(10,6) NULL,
    output_cost_per_1k DECIMAL(10,6) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (provider_id) REFERENCES ai_providers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_ai_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    provider_id BIGINT UNSIGNED NOT NULL,
    model_id BIGINT UNSIGNED NOT NULL,
    api_key_encrypted TEXT NULL,               -- encrypted via Laravel Crypt; NULL for local providers
    temperature DECIMAL(3,2) NOT NULL DEFAULT 0.70,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (provider_id) REFERENCES ai_providers(id),
    FOREIGN KEY (model_id) REFERENCES ai_models(id),
    UNIQUE KEY uniq_user_default (user_id, is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ai_prompt_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) NOT NULL UNIQUE,        -- onboarding_analysis, meal_plan, workout_plan, weekly_review, daily_motivation, coach_review, daily_chat
    purpose VARCHAR(255) NOT NULL,
    template LONGTEXT NOT NULL,                -- Blade-style {{ variable }} placeholders
    variables JSON NOT NULL,                   -- documents expected variables
    response_schema JSON NOT NULL,             -- JSON schema the AI response must satisfy
    version INT UNSIGNED NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE rule_engine_rules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(50) NOT NULL,             -- calorie_target, workout_level, water_target, disease_restriction
    name VARCHAR(150) NOT NULL,
    condition JSON NOT NULL,                   -- e.g. {"bmi": {">=": 27}}
    action JSON NOT NULL,                      -- e.g. {"calorie_deficit_pct": 20, "workout_level": "beginner"}
    priority INT UNSIGNED NOT NULL DEFAULT 100,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ai_memories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    user_program_id BIGINT UNSIGNED NULL,
    memory_type ENUM('trend','pattern','milestone','concern') NOT NULL,
    summary VARCHAR(500) NOT NULL,             -- e.g. "Weight stagnant 20 days"
    data JSON NOT NULL,                        -- structured evidence backing the summary
    relevance_score DECIMAL(4,2) NOT NULL DEFAULT 1.00, -- decays over time, used to prune context sent to AI
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user_program_id) REFERENCES user_programs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ai_recommendations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    user_program_id BIGINT UNSIGNED NULL,
    type ENUM('meal_adjustment','workout_adjustment','habit','motivation','alert') NOT NULL,
    content JSON NOT NULL,                     -- raw structured AI output (see docs/07-Prompt-Engineering.md)
    rationale TEXT NULL,
    status ENUM('pending','applied','rejected','expired') NOT NULL DEFAULT 'pending',
    applied_at TIMESTAMP NULL,
    reviewed_by BIGINT UNSIGNED NULL,          -- coach who approved/rejected, if applicable
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user_program_id) REFERENCES user_programs(id) ON DELETE SET NULL,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ai_request_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    provider_id BIGINT UNSIGNED NOT NULL,
    model_id BIGINT UNSIGNED NOT NULL,
    purpose VARCHAR(100) NOT NULL,             -- matches ai_prompt_templates.key
    request_payload JSON NULL,
    response_payload JSON NULL,
    prompt_tokens INT UNSIGNED NULL,
    completion_tokens INT UNSIGNED NULL,
    estimated_cost DECIMAL(10,6) NULL,
    latency_ms INT UNSIGNED NULL,
    status ENUM('success','error','timeout','invalid_json') NOT NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (provider_id) REFERENCES ai_providers(id),
    FOREIGN KEY (model_id) REFERENCES ai_models(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- MODULE 03 — KNOWLEDGE BASE (master/reference data)
-- =====================================================================

CREATE TABLE kb_diseases (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    category VARCHAR(50) NULL,                 -- metabolic, cardiovascular, digestive
    description TEXT NULL,
    dietary_restrictions JSON NULL,            -- e.g. ["low_sodium","low_sugar"]
    recommended_exercise JSON NULL,
    contraindicated_exercise JSON NULL,
    reference_source VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE kb_foods (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    name_local VARCHAR(150) NULL,              -- Indonesian name, e.g. "Nasi Padang"
    category VARCHAR(50) NULL,                 -- staple, protein, vegetable, snack, drink
    serving_unit VARCHAR(30) NOT NULL DEFAULT 'gram',
    serving_size DECIMAL(8,2) NOT NULL DEFAULT 100.00,
    calories DECIMAL(8,2) NOT NULL,
    protein_g DECIMAL(6,2) NOT NULL DEFAULT 0,
    carbs_g DECIMAL(6,2) NOT NULL DEFAULT 0,
    fat_g DECIMAL(6,2) NOT NULL DEFAULT 0,
    fiber_g DECIMAL(6,2) NULL,
    sodium_mg DECIMAL(8,2) NULL,
    glycemic_index INT UNSIGNED NULL,
    tags JSON NULL,                            -- ["halal","vegetarian","low_purine"]
    source VARCHAR(150) NULL,                  -- e.g. TKPI (Tabel Komposisi Pangan Indonesia)
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE kb_exercises (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    category VARCHAR(50) NULL,                 -- cardio, strength, flexibility, sport
    target_muscle VARCHAR(100) NULL,
    met_value DECIMAL(5,2) NULL,               -- for calorie-burn estimation
    difficulty ENUM('beginner','intermediate','advanced') NOT NULL DEFAULT 'beginner',
    equipment VARCHAR(150) NULL,
    instructions TEXT NULL,
    video_url VARCHAR(255) NULL,
    contraindications JSON NULL,               -- disease slugs to avoid this exercise
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE kb_nutrition_articles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    category VARCHAR(50) NULL,
    content LONGTEXT NOT NULL,
    tags JSON NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE kb_faqs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question VARCHAR(255) NOT NULL,
    answer TEXT NOT NULL,
    category VARCHAR(50) NULL,
    `order` INT UNSIGNED NOT NULL DEFAULT 0,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- MODULE 04 — HEALTH PROFILE
-- =====================================================================

CREATE TABLE health_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    date_of_birth DATE NULL,
    gender ENUM('male','female') NULL,
    height_cm DECIMAL(5,2) NULL,
    initial_weight_kg DECIMAL(5,2) NULL,
    blood_type VARCHAR(5) NULL,
    bmi DECIMAL(5,2) NULL,                     -- derived, recalculated on weight update
    bmr DECIMAL(8,2) NULL,                     -- Mifflin-St Jeor
    tdee DECIMAL(8,2) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE lifestyle_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    activity_level ENUM('sedentary','light','moderate','heavy') NOT NULL DEFAULT 'sedentary',
    sleep_time TIME NULL,
    wake_time TIME NULL,
    avg_sleep_hours DECIMAL(3,1) NULL,
    work_hours_per_day DECIMAL(3,1) NULL,
    diet_pattern VARCHAR(50) NULL,             -- regular, intermittent_fasting, vegetarian, vegan
    sugary_drinks_frequency ENUM('never','rarely','often','daily') NULL,
    smoking_status ENUM('never','former','current') NULL,
    alcohol_frequency ENUM('never','rarely','often','daily') NULL,
    exercise_frequency ENUM('never','1_2_week','3_4_week','5plus_week') NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_diseases (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    kb_disease_id BIGINT UNSIGNED NOT NULL,
    diagnosed_at DATE NULL,
    status ENUM('active','managed','resolved') NOT NULL DEFAULT 'active',
    notes VARCHAR(500) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (kb_disease_id) REFERENCES kb_diseases(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_allergies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    allergen VARCHAR(150) NOT NULL,
    severity ENUM('mild','moderate','severe') NOT NULL DEFAULT 'mild',
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_medications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    dosage VARCHAR(100) NULL,
    frequency VARCHAR(100) NULL,
    started_at DATE NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE body_measurements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    measured_at DATE NOT NULL,
    weight_kg DECIMAL(5,2) NULL,
    waist_cm DECIMAL(5,2) NULL,
    chest_cm DECIMAL(5,2) NULL,
    hip_cm DECIMAL(5,2) NULL,
    arm_cm DECIMAL(5,2) NULL,
    thigh_cm DECIMAL(5,2) NULL,
    body_fat_pct DECIMAL(4,2) NULL,
    note VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_user_date (user_id, measured_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- MODULE 05 — ONBOARDING
-- =====================================================================

CREATE TABLE onboarding_questions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    step INT UNSIGNED NOT NULL,
    category VARCHAR(50) NOT NULL,             -- identity, body, goal, lifestyle, medical
    question_text VARCHAR(255) NOT NULL,
    input_type ENUM('text','number','date','single_choice','multi_choice','time','scale') NOT NULL,
    options JSON NULL,                         -- for choice-based inputs
    validation_rules JSON NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 1,
    `order` INT UNSIGNED NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE onboarding_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    status ENUM('in_progress','completed','abandoned') NOT NULL DEFAULT 'in_progress',
    current_step INT UNSIGNED NOT NULL DEFAULT 1,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE onboarding_answers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    onboarding_session_id BIGINT UNSIGNED NOT NULL,
    question_id BIGINT UNSIGNED NOT NULL,
    answer_value JSON NOT NULL,
    answered_at TIMESTAMP NULL,
    FOREIGN KEY (onboarding_session_id) REFERENCES onboarding_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES onboarding_questions(id),
    UNIQUE KEY uniq_session_question (onboarding_session_id, question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- MODULE 06 — PROGRAM
-- =====================================================================

CREATE TABLE programs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,                -- Diet & Transformasi 90 Hari, Bulking, Marathon Prep...
    slug VARCHAR(100) NOT NULL UNIQUE,
    category VARCHAR(50) NOT NULL,             -- diet, bulking, cardio, disease_management, prenatal, senior
    description TEXT NULL,
    default_duration_days INT UNSIGNED NOT NULL DEFAULT 90,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_programs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    program_id BIGINT UNSIGNED NOT NULL,
    coach_id BIGINT UNSIGNED NULL,
    status ENUM('active','paused','completed','cancelled') NOT NULL DEFAULT 'active',
    start_date DATE NOT NULL,
    end_date DATE NULL,
    created_by ENUM('user','coach','ai') NOT NULL DEFAULT 'ai',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (program_id) REFERENCES programs(id),
    FOREIGN KEY (coach_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE program_goals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_program_id BIGINT UNSIGNED NOT NULL,
    goal_type VARCHAR(50) NOT NULL,            -- weight_loss, weight_gain, maintenance, endurance
    target_weight_kg DECIMAL(5,2) NULL,
    target_waist_cm DECIMAL(5,2) NULL,
    target_date DATE NULL,
    notes VARCHAR(500) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_program_id) REFERENCES user_programs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE weekly_plans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_program_id BIGINT UNSIGNED NOT NULL,
    week_number INT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    ai_summary TEXT NULL,
    ai_review JSON NULL,                       -- raw AI weekly_review response
    generated_by ENUM('rule_engine','ai') NOT NULL DEFAULT 'rule_engine',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_program_id) REFERENCES user_programs(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_program_week (user_program_id, week_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE daily_tasks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_program_id BIGINT UNSIGNED NOT NULL,
    task_date DATE NOT NULL,
    task_type VARCHAR(50) NOT NULL,            -- meal, workout, water, sleep, habit, checkin
    title VARCHAR(200) NOT NULL,
    description VARCHAR(500) NULL,
    is_completed TINYINT(1) NOT NULL DEFAULT 0,
    completed_at TIMESTAMP NULL,
    source ENUM('rule_engine','ai','coach') NOT NULL DEFAULT 'rule_engine',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_program_id) REFERENCES user_programs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE meal_plans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_program_id BIGINT UNSIGNED NOT NULL,
    plan_date DATE NOT NULL,
    meal_type ENUM('breakfast','lunch','dinner','snack') NOT NULL,
    total_calories DECIMAL(8,2) NULL,
    total_protein_g DECIMAL(6,2) NULL,
    total_carbs_g DECIMAL(6,2) NULL,
    total_fat_g DECIMAL(6,2) NULL,
    is_completed TINYINT(1) NOT NULL DEFAULT 0,
    source ENUM('rule_engine','ai','coach','manual') NOT NULL DEFAULT 'ai',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_program_id) REFERENCES user_programs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE meal_plan_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meal_plan_id BIGINT UNSIGNED NOT NULL,
    kb_food_id BIGINT UNSIGNED NULL,           -- NULL when AI suggests a food not yet in KB
    custom_name VARCHAR(150) NULL,
    portion DECIMAL(6,2) NOT NULL DEFAULT 1,
    calories DECIMAL(8,2) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (meal_plan_id) REFERENCES meal_plans(id) ON DELETE CASCADE,
    FOREIGN KEY (kb_food_id) REFERENCES kb_foods(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE workout_plans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_program_id BIGINT UNSIGNED NOT NULL,
    plan_date DATE NOT NULL,
    workout_type VARCHAR(50) NULL,             -- cardio, strength, yoga, rest
    duration_minutes INT UNSIGNED NULL,
    intensity ENUM('low','moderate','high') NOT NULL DEFAULT 'low',
    is_completed TINYINT(1) NOT NULL DEFAULT 0,
    source ENUM('rule_engine','ai','coach','manual') NOT NULL DEFAULT 'ai',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_program_id) REFERENCES user_programs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE workout_plan_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workout_plan_id BIGINT UNSIGNED NOT NULL,
    kb_exercise_id BIGINT UNSIGNED NULL,
    custom_name VARCHAR(150) NULL,
    sets INT UNSIGNED NULL,
    reps INT UNSIGNED NULL,
    duration_seconds INT UNSIGNED NULL,
    `order` INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (workout_plan_id) REFERENCES workout_plans(id) ON DELETE CASCADE,
    FOREIGN KEY (kb_exercise_id) REFERENCES kb_exercises(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE checklist_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_program_id BIGINT UNSIGNED NOT NULL,
    item_date DATE NOT NULL,
    label VARCHAR(200) NOT NULL,
    is_checked TINYINT(1) NOT NULL DEFAULT 0,
    checked_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_program_id) REFERENCES user_programs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE reminders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    type VARCHAR(50) NOT NULL,                 -- water, meal, workout, checkin, medication
    title VARCHAR(150) NOT NULL,
    message VARCHAR(255) NULL,
    scheduled_at TIME NOT NULL,
    is_recurring TINYINT(1) NOT NULL DEFAULT 1,
    recurrence_rule VARCHAR(100) NULL,         -- e.g. RRULE:FREQ=DAILY
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_sent_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- MODULE 07 — PROGRESS TRACKING
-- =====================================================================

CREATE TABLE weight_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    logged_at DATE NOT NULL,
    weight_kg DECIMAL(5,2) NOT NULL,
    note VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_user_day (user_id, logged_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE waist_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    logged_at DATE NOT NULL,
    waist_cm DECIMAL(5,2) NOT NULL,
    created_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_user_day (user_id, logged_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE body_fat_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    logged_at DATE NOT NULL,
    body_fat_pct DECIMAL(4,2) NOT NULL,
    created_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_user_day (user_id, logged_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE progress_photos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    logged_at DATE NOT NULL,
    angle ENUM('front','side','back') NOT NULL,
    photo_path VARCHAR(255) NOT NULL,
    is_private TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE water_intake_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    logged_at DATE NOT NULL,
    amount_ml INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sleep_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    logged_at DATE NOT NULL,
    sleep_hours DECIMAL(3,1) NOT NULL,
    quality ENUM('poor','fair','good','excellent') NULL,
    created_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_user_day (user_id, logged_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE health_scores (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    scored_at DATE NOT NULL,
    score DECIMAL(5,2) NOT NULL,               -- 0-100
    breakdown JSON NOT NULL,                   -- {"bmi":18,"waist":9,"sleep":12,...}
    explanation TEXT NULL,                     -- AI-generated narrative
    created_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_user_day (user_id, scored_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- MODULE 08 — COACH
-- =====================================================================

CREATE TABLE coach_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    bio TEXT NULL,
    specialization VARCHAR(150) NULL,
    certification VARCHAR(255) NULL,
    max_members INT UNSIGNED NOT NULL DEFAULT 50,
    rating_avg DECIMAL(3,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE coach_members (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    coach_id BIGINT UNSIGNED NOT NULL,
    member_id BIGINT UNSIGNED NOT NULL,
    status ENUM('active','paused','ended') NOT NULL DEFAULT 'active',
    assigned_at TIMESTAMP NULL,
    ended_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (coach_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_coach_member_active (coach_id, member_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE coach_notes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    coach_id BIGINT UNSIGNED NOT NULL,
    member_id BIGINT UNSIGNED NOT NULL,
    note TEXT NOT NULL,
    is_visible_to_member TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (coach_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE conversations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type ENUM('coach_member','ai_assistant') NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    coach_id BIGINT UNSIGNED NULL,             -- NULL when type = ai_assistant
    last_message_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (coach_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id BIGINT UNSIGNED NOT NULL,
    sender_type ENUM('user','coach','ai','system') NOT NULL,
    sender_id BIGINT UNSIGNED NULL,            -- NULL for ai/system
    content TEXT NOT NULL,
    meta JSON NULL,                            -- attachments, AI provider used, tokens, etc.
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE reviews (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    coach_id BIGINT UNSIGNED NOT NULL,
    member_id BIGINT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,          -- 1-5
    comment VARCHAR(500) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (coach_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_member_coach_review (coach_id, member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- MODULE 09 — ANALYTICS / GAMIFICATION
-- =====================================================================

CREATE TABLE achievements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description VARCHAR(255) NULL,
    icon VARCHAR(150) NULL,
    criteria JSON NOT NULL,                    -- e.g. {"streak_days": 30}
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_achievements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    achievement_id BIGINT UNSIGNED NOT NULL,
    earned_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_user_achievement (user_id, achievement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE activity_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    subject_type VARCHAR(100) NULL,
    subject_id BIGINT UNSIGNED NULL,
    meta JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- MODULE 10 — SUBSCRIPTION / BILLING (SaaS-ready)
-- =====================================================================

CREATE TABLE plans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    billing_cycle ENUM('monthly','yearly','lifetime') NOT NULL DEFAULT 'monthly',
    features JSON NULL,
    max_programs INT UNSIGNED NOT NULL DEFAULT 1,
    has_coach_access TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE subscriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    plan_id BIGINT UNSIGNED NOT NULL,
    status ENUM('trialing','active','past_due','cancelled','expired') NOT NULL DEFAULT 'trialing',
    starts_at TIMESTAMP NOT NULL,
    ends_at TIMESTAMP NULL,
    cancelled_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES plans(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subscription_id BIGINT UNSIGNED NOT NULL,
    provider ENUM('stripe','xendit','midtrans') NOT NULL,
    provider_reference VARCHAR(150) NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'IDR',
    status ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- MODULE 11 — APP SETTINGS
-- =====================================================================

CREATE TABLE app_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) NOT NULL UNIQUE,
    value JSON NULL,
    description VARCHAR(255) NULL,
    updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- INDEX RECOMMENDATIONS (add beyond PK/FK/unique already declared above)
-- =====================================================================
CREATE INDEX idx_daily_tasks_program_date ON daily_tasks (user_program_id, task_date);
CREATE INDEX idx_meal_plans_program_date ON meal_plans (user_program_id, plan_date);
CREATE INDEX idx_workout_plans_program_date ON workout_plans (user_program_id, plan_date);
CREATE INDEX idx_checklist_program_date ON checklist_items (user_program_id, item_date);
CREATE INDEX idx_weight_logs_user_date ON weight_logs (user_id, logged_at);
CREATE INDEX idx_health_scores_user_date ON health_scores (user_id, scored_at);
CREATE INDEX idx_ai_request_logs_user_purpose ON ai_request_logs (user_id, purpose);
CREATE INDEX idx_messages_conversation_created ON messages (conversation_id, created_at);
CREATE INDEX idx_ai_memories_user_type ON ai_memories (user_id, memory_type);
CREATE INDEX idx_kb_foods_category ON kb_foods (category);
CREATE INDEX idx_kb_exercises_category ON kb_exercises (category);
