<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->string('category', 50);
            $table->text('description')->nullable();
            $table->unsignedInteger('default_duration_days')->default(90);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('user_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained('programs');
            $table->foreignId('coach_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['active', 'paused', 'completed', 'cancelled'])->default('active');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->enum('created_by', ['user', 'coach', 'ai'])->default('ai');
            $table->timestamps();
        });

        Schema::create('program_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_program_id')->constrained('user_programs')->cascadeOnDelete();
            $table->string('goal_type', 50);
            $table->decimal('target_weight_kg', 5, 2)->nullable();
            $table->decimal('target_waist_cm', 5, 2)->nullable();
            $table->date('target_date')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('weekly_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_program_id')->constrained('user_programs')->cascadeOnDelete();
            $table->unsignedInteger('week_number');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('ai_summary')->nullable();
            $table->json('ai_review')->nullable();
            $table->enum('generated_by', ['rule_engine', 'ai'])->default('rule_engine');
            // Not in mysql.sql - added to support wireframe/dashboard.md's
            // "Weekly Review card only appears ... that the user hasn't
            // viewed ... tapping 'Lihat Detail' ... marks it read", which
            // the documented schema has no column for. Additive/nullable,
            // no conflict with anything else - see docs/11-Development-
            // Checklist.md Phase 6 notes.
            $table->timestamp('viewed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_program_id', 'week_number'], 'uniq_program_week');
        });

        Schema::create('daily_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_program_id')->constrained('user_programs')->cascadeOnDelete();
            $table->date('task_date');
            $table->string('task_type', 50);
            $table->string('title', 200);
            $table->string('description', 500)->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->enum('source', ['rule_engine', 'ai', 'coach'])->default('rule_engine');
            $table->timestamps();
        });

        Schema::create('meal_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_program_id')->constrained('user_programs')->cascadeOnDelete();
            $table->date('plan_date');
            $table->enum('meal_type', ['breakfast', 'lunch', 'dinner', 'snack']);
            $table->decimal('total_calories', 8, 2)->nullable();
            $table->decimal('total_protein_g', 6, 2)->nullable();
            $table->decimal('total_carbs_g', 6, 2)->nullable();
            $table->decimal('total_fat_g', 6, 2)->nullable();
            $table->boolean('is_completed')->default(false);
            $table->enum('source', ['rule_engine', 'ai', 'coach', 'manual'])->default('ai');
            $table->timestamps();
        });

        Schema::create('meal_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_plan_id')->constrained('meal_plans')->cascadeOnDelete();
            $table->foreignId('kb_food_id')->nullable()->constrained('kb_foods')->nullOnDelete();
            $table->string('custom_name', 150)->nullable();
            $table->decimal('portion', 6, 2)->default(1);
            $table->decimal('calories', 8, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('workout_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_program_id')->constrained('user_programs')->cascadeOnDelete();
            $table->date('plan_date');
            $table->string('workout_type', 50)->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->enum('intensity', ['low', 'moderate', 'high'])->default('low');
            $table->boolean('is_completed')->default(false);
            $table->enum('source', ['rule_engine', 'ai', 'coach', 'manual'])->default('ai');
            $table->timestamps();
        });

        Schema::create('workout_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_plan_id')->constrained('workout_plans')->cascadeOnDelete();
            $table->foreignId('kb_exercise_id')->nullable()->constrained('kb_exercises')->nullOnDelete();
            $table->string('custom_name', 150)->nullable();
            $table->unsignedInteger('sets')->nullable();
            $table->unsignedInteger('reps')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_program_id')->constrained('user_programs')->cascadeOnDelete();
            $table->date('item_date');
            $table->string('label', 200);
            $table->boolean('is_checked')->default(false);
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('title', 150);
            $table->string('message', 255)->nullable();
            $table->time('scheduled_at');
            $table->boolean('is_recurring')->default(true);
            $table->string('recurrence_rule', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
        Schema::dropIfExists('checklist_items');
        Schema::dropIfExists('workout_plan_items');
        Schema::dropIfExists('workout_plans');
        Schema::dropIfExists('meal_plan_items');
        Schema::dropIfExists('meal_plans');
        Schema::dropIfExists('daily_tasks');
        Schema::dropIfExists('weekly_plans');
        Schema::dropIfExists('program_goals');
        Schema::dropIfExists('user_programs');
        Schema::dropIfExists('programs');
    }
};
