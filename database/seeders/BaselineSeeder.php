<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Reference data every environment needs, dev or test — no dev-only
 * fixtures like the "Test User" account (that stays in DatabaseSeeder).
 */
class BaselineSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);
        $this->call(KbDiseaseSeeder::class);
        $this->call(KbFoodSeeder::class);
        $this->call(KbExerciseSeeder::class);
        $this->call(KbNutritionArticleSeeder::class);
        $this->call(KbFaqSeeder::class);
        $this->call(RuleEngineRuleSeeder::class);
        $this->call(AiProviderSeeder::class);
        $this->call(AiPromptTemplateSeeder::class);
        $this->call(OnboardingQuestionSeeder::class);
        $this->call(ProgramSeeder::class);
        $this->call(AchievementSeeder::class);
    }
}
