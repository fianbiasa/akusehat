<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pulled forward from Phase 4 (Knowledge Base) because user_diseases
     * (Phase 3) has a required FK to it. Only this one KB table + a 5-row
     * seed - kb_foods/kb_exercises/kb_nutrition_articles/kb_faqs and the
     * Admin CRUD for all of them stay in Phase 4.
     */
    public function up(): void
    {
        Schema::create('kb_diseases', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->string('category', 50)->nullable(); // metabolic, cardiovascular, digestive
            $table->text('description')->nullable();
            $table->json('dietary_restrictions')->nullable();
            $table->json('recommended_exercise')->nullable();
            $table->json('contraindicated_exercise')->nullable();
            $table->string('reference_source')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_diseases');
    }
};
