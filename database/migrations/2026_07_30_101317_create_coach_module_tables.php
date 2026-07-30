<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coach_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('bio')->nullable();
            $table->string('specialization', 150)->nullable();
            $table->string('certification', 255)->nullable();
            $table->unsignedInteger('max_members')->default(50);
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('coach_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['active', 'paused', 'ended'])->default('active');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            // mysql.sql's own dictionary calls this "Unique-ish" -
            // UNIQUE(coach_id, member_id, status) breaks the very
            // reassignment history it's meant to preserve: a member who
            // leaves the same coach twice (e.g. coach A -> coach B ->
            // back to coach A -> away again) would violate this on the
            // second (coach_id, member_id, 'ended') row. "At most one
            // active assignment per member" is enforced app-side instead
            // (CoachAssignmentService ends the prior active row inside
            // the same transaction before creating the new one).
        });

        Schema::create('coach_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('users')->cascadeOnDelete();
            $table->text('note');
            $table->boolean('is_visible_to_member')->default(false);
            $table->timestamps();
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['coach_member', 'ai_assistant']);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coach_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->enum('sender_type', ['user', 'coach', 'ai', 'system']);
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('content');
            $table->json('meta')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('comment', 500)->nullable();
            $table->timestamps();
            $table->unique(['coach_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('coach_notes');
        Schema::dropIfExists('coach_members');
        Schema::dropIfExists('coach_profiles');
    }
};
