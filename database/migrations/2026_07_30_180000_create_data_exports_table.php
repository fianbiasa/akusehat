<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive table, not in mysql.sql - backs the PRD §13 "right-to-export"
 * compliance flow (a data-export feature exists nowhere in the original
 * schema). A persisted record (not just a fire-and-forget file write)
 * gives an audit trail of when a user's data was exported, which
 * matters for the same compliance reasons as the export itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('file_path')->nullable();
            $table->enum('status', ['pending', 'ready', 'failed'])->default('pending');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_exports');
    }
};
