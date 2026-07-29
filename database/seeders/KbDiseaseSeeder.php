<?php

namespace Database\Seeders;

use App\Models\KbDisease;
use Illuminate\Database\Seeder;

class KbDiseaseSeeder extends Seeder
{
    /**
     * Baseline 5 per docs/08-Knowledge-Base.md §2.1. `name` matches the
     * option strings in OnboardingQuestionSeeder's disease_checklist
     * question exactly - the onboarding-to-profile mapper looks diseases
     * up by name.
     */
    public function run(): void
    {
        $diseases = [
            [
                'name' => 'Diabetes Melitus Tipe 2',
                'slug' => 'diabetes-tipe-2',
                'category' => 'metabolic',
                'dietary_restrictions' => ['low_sugar', 'low_glycemic_index', 'controlled_carb'],
                'contraindicated_exercise' => [],
            ],
            [
                'name' => 'Hipertensi',
                'slug' => 'hipertensi',
                'category' => 'cardiovascular',
                'dietary_restrictions' => ['low_sodium'],
                'contraindicated_exercise' => ['heavy_lifting_valsalva'],
            ],
            [
                'name' => 'Kolesterol Tinggi',
                'slug' => 'kolesterol-tinggi',
                'category' => 'cardiovascular',
                'dietary_restrictions' => ['low_saturated_fat', 'high_fiber'],
                'contraindicated_exercise' => [],
            ],
            [
                'name' => 'Asam Urat',
                'slug' => 'asam-urat',
                'category' => 'metabolic',
                'dietary_restrictions' => ['low_purine'],
                'contraindicated_exercise' => ['high_impact_joint_stress'],
            ],
            [
                'name' => 'Tukak Lambung/GERD',
                'slug' => 'tukak-lambung-gerd',
                'category' => 'digestive',
                'dietary_restrictions' => ['low_acid', 'small_frequent_meals', 'avoid_spicy'],
                'contraindicated_exercise' => ['inversion_exercises'],
            ],
        ];

        foreach ($diseases as $disease) {
            KbDisease::updateOrCreate(['slug' => $disease['slug']], $disease);
        }
    }
}
