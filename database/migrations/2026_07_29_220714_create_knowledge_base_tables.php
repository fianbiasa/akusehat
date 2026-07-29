<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kb_foods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('name_local', 150)->nullable();
            $table->string('category', 50)->nullable(); // staple, protein, vegetable, snack, drink
            $table->string('serving_unit', 30)->default('gram');
            $table->decimal('serving_size', 8, 2)->default(100);
            $table->decimal('calories', 8, 2);
            $table->decimal('protein_g', 6, 2)->default(0);
            $table->decimal('carbs_g', 6, 2)->default(0);
            $table->decimal('fat_g', 6, 2)->default(0);
            $table->decimal('fiber_g', 6, 2)->nullable();
            $table->decimal('sodium_mg', 8, 2)->nullable();
            $table->unsignedInteger('glycemic_index')->nullable();
            $table->json('tags')->nullable();
            $table->string('source', 150)->nullable();
            $table->timestamps();
        });

        Schema::create('kb_exercises', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('category', 50)->nullable(); // cardio, strength, flexibility, sport
            $table->string('target_muscle', 100)->nullable();
            $table->decimal('met_value', 5, 2)->nullable();
            $table->enum('difficulty', ['beginner', 'intermediate', 'advanced'])->default('beginner');
            $table->string('equipment', 150)->nullable();
            $table->text('instructions')->nullable();
            $table->string('video_url')->nullable();
            $table->json('contraindications')->nullable(); // kb_diseases.slug values
            $table->timestamps();
        });

        Schema::create('kb_nutrition_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('slug', 200)->unique();
            $table->string('category', 50)->nullable();
            $table->longText('content');
            $table->json('tags')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('kb_faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question');
            $table->text('answer');
            $table->string('category', 50)->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_faqs');
        Schema::dropIfExists('kb_nutrition_articles');
        Schema::dropIfExists('kb_exercises');
        Schema::dropIfExists('kb_foods');
    }
};
