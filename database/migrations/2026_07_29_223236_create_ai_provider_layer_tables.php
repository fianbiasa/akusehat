<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('slug', 50)->unique();
            $table->enum('type', ['cloud', 'local'])->default('cloud');
            $table->string('base_url')->nullable();
            $table->string('driver_class', 150);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ai_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('ai_providers')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('model_key', 100);
            $table->unsignedInteger('context_length')->nullable();
            $table->boolean('supports_json_mode')->default(true);
            $table->decimal('input_cost_per_1k', 10, 6)->nullable();
            $table->decimal('output_cost_per_1k', 10, 6)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('user_ai_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('ai_providers');
            $table->foreignId('model_id')->constrained('ai_models');
            $table->text('api_key_encrypted')->nullable();
            $table->decimal('temperature', 3, 2)->default(0.70);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            // mysql.sql specifies UNIQUE(user_id, is_default), but MySQL
            // has no partial/filtered unique index - a plain 2-column
            // unique on a boolean also caps *non*-default rows at one per
            // user, which breaks FR-AI-06 (a secondary provider for
            // failover requires >=2 non-default rows to be possible).
            // "One default per user" is enforced at the app level instead
            // (every write path that sets is_default=true unsets the
            // others in the same transaction - see UserAiSetting call
            // sites), which is what the docs' own "combined with
            // app-level unset-others logic" phrasing already implies.
        });

        Schema::create('ai_prompt_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('purpose');
            $table->longText('template');
            $table->json('variables');
            $table->json('response_schema');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ai_memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // user_program_id -> user_programs(id) FK deferred to Phase 6
            // (Program Generation), which is what actually creates that
            // table. Nullable column matches the schema now; the
            // constraint gets added when user_programs exists.
            $table->unsignedBigInteger('user_program_id')->nullable();
            $table->enum('memory_type', ['trend', 'pattern', 'milestone', 'concern']);
            $table->string('summary', 500);
            $table->json('data');
            $table->decimal('relevance_score', 4, 2)->default(1.00);
            $table->timestamps();
        });

        Schema::create('ai_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_program_id')->nullable(); // see ai_memories comment above
            $table->enum('type', ['meal_adjustment', 'workout_adjustment', 'habit', 'motivation', 'alert']);
            $table->json('content');
            $table->text('rationale')->nullable();
            $table->enum('status', ['pending', 'applied', 'rejected', 'expired'])->default('pending');
            $table->timestamp('applied_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ai_request_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('provider_id')->constrained('ai_providers');
            $table->foreignId('model_id')->constrained('ai_models');
            $table->string('purpose', 100); // matches ai_prompt_templates.key
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->decimal('estimated_cost', 10, 6)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->enum('status', ['success', 'error', 'timeout', 'invalid_json']);
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_request_logs');
        Schema::dropIfExists('ai_recommendations');
        Schema::dropIfExists('ai_memories');
        Schema::dropIfExists('ai_prompt_templates');
        Schema::dropIfExists('user_ai_settings');
        Schema::dropIfExists('ai_models');
        Schema::dropIfExists('ai_providers');
    }
};
