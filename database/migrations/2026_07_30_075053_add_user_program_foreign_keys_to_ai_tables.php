<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5's ai_memories/ai_recommendations left user_program_id as a
 * plain nullable column with a comment promising the FK once
 * user_programs exists (Phase 6, now). Adding it here rather than
 * editing the Phase 5 migration in place, since that migration has
 * already run in every environment that built on top of Phase 5.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_memories', function (Blueprint $table) {
            $table->foreign('user_program_id')->references('id')->on('user_programs')->nullOnDelete();
        });

        Schema::table('ai_recommendations', function (Blueprint $table) {
            $table->foreign('user_program_id')->references('id')->on('user_programs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_memories', function (Blueprint $table) {
            $table->dropForeign(['user_program_id']);
        });

        Schema::table('ai_recommendations', function (Blueprint $table) {
            $table->dropForeign(['user_program_id']);
        });
    }
};
