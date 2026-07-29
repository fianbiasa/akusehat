<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('step');
            $table->string('category', 50); // identity, body, lifestyle, medical, preferences, goal
            $table->string('question_text');
            $table->enum('input_type', ['text', 'number', 'date', 'single_choice', 'multi_choice', 'time', 'scale']);
            $table->json('options')->nullable();
            $table->json('validation_rules')->nullable();
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('order');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('onboarding_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['in_progress', 'completed', 'abandoned'])->default('in_progress');
            $table->unsignedInteger('current_step')->default(1);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('onboarding_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onboarding_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('onboarding_questions');
            $table->json('answer_value');
            $table->timestamp('answered_at')->nullable();
            $table->unique(['onboarding_session_id', 'question_id'], 'uniq_session_question');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_answers');
        Schema::dropIfExists('onboarding_sessions');
        Schema::dropIfExists('onboarding_questions');
    }
};
