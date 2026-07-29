<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rule_engine_rules', function (Blueprint $table) {
            $table->id();
            $table->string('category', 50); // calorie_target, macro_split, workout_level, water_target, disease_restriction
            $table->string('name', 150);
            $table->json('condition');
            $table->json('action');
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rule_engine_rules');
    }
};
