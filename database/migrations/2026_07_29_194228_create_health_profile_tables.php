<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->decimal('height_cm', 5, 2)->nullable();
            $table->decimal('initial_weight_kg', 5, 2)->nullable();
            $table->string('blood_type', 5)->nullable();
            $table->decimal('bmi', 5, 2)->nullable();
            $table->decimal('bmr', 8, 2)->nullable();
            $table->decimal('tdee', 8, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('lifestyle_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->enum('activity_level', ['sedentary', 'light', 'moderate', 'heavy'])->default('sedentary');
            $table->time('sleep_time')->nullable();
            $table->time('wake_time')->nullable();
            $table->decimal('avg_sleep_hours', 3, 1)->nullable();
            $table->decimal('work_hours_per_day', 3, 1)->nullable();
            $table->string('diet_pattern', 50)->nullable();
            $table->enum('sugary_drinks_frequency', ['never', 'rarely', 'often', 'daily'])->nullable();
            $table->enum('smoking_status', ['never', 'former', 'current'])->nullable();
            $table->enum('alcohol_frequency', ['never', 'rarely', 'often', 'daily'])->nullable();
            $table->enum('exercise_frequency', ['never', '1_2_week', '3_4_week', '5plus_week'])->nullable();
            $table->timestamps();
        });

        Schema::create('user_diseases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kb_disease_id')->constrained();
            $table->date('diagnosed_at')->nullable();
            $table->enum('status', ['active', 'managed', 'resolved'])->default('active');
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('user_allergies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('allergen', 150);
            $table->enum('severity', ['mild', 'moderate', 'severe'])->default('mild');
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('user_medications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('dosage', 100)->nullable();
            $table->string('frequency', 100)->nullable();
            $table->date('started_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('body_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('measured_at');
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->decimal('waist_cm', 5, 2)->nullable();
            $table->decimal('chest_cm', 5, 2)->nullable();
            $table->decimal('hip_cm', 5, 2)->nullable();
            $table->decimal('arm_cm', 5, 2)->nullable();
            $table->decimal('thigh_cm', 5, 2)->nullable();
            $table->decimal('body_fat_pct', 4, 2)->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'measured_at'], 'uniq_user_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('body_measurements');
        Schema::dropIfExists('user_medications');
        Schema::dropIfExists('user_allergies');
        Schema::dropIfExists('user_diseases');
        Schema::dropIfExists('lifestyle_profiles');
        Schema::dropIfExists('health_profiles');
    }
};
