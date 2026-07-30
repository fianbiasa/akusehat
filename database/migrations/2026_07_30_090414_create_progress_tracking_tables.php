<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weight_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('logged_at');
            $table->decimal('weight_kg', 5, 2);
            $table->string('note', 255)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['user_id', 'logged_at']);
        });

        Schema::create('waist_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('logged_at');
            $table->decimal('waist_cm', 5, 2);
            $table->timestamp('created_at')->nullable();
            $table->unique(['user_id', 'logged_at']);
        });

        Schema::create('body_fat_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('logged_at');
            $table->decimal('body_fat_pct', 4, 2);
            $table->timestamp('created_at')->nullable();
            $table->unique(['user_id', 'logged_at']);
        });

        Schema::create('progress_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('logged_at');
            $table->enum('angle', ['front', 'side', 'back']);
            $table->string('photo_path', 255);
            $table->boolean('is_private')->default(true);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('water_intake_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('logged_at');
            $table->unsignedInteger('amount_ml');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sleep_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('logged_at');
            $table->decimal('sleep_hours', 3, 1);
            $table->enum('quality', ['poor', 'fair', 'good', 'excellent'])->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['user_id', 'logged_at']);
        });

        Schema::create('health_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('scored_at');
            $table->decimal('score', 5, 2);
            $table->json('breakdown');
            $table->text('explanation')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['user_id', 'scored_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_scores');
        Schema::dropIfExists('sleep_logs');
        Schema::dropIfExists('water_intake_logs');
        Schema::dropIfExists('progress_photos');
        Schema::dropIfExists('body_fat_logs');
        Schema::dropIfExists('waist_logs');
        Schema::dropIfExists('weight_logs');
    }
};
