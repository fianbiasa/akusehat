<?php

namespace Database\Seeders;

use App\Models\KbExercise;
use Illuminate\Database\Seeder;

class KbExerciseSeeder extends Seeder
{
    /**
     * Starter/demo set (see KbFoodSeeder docblock for the same caveat).
     * Columns: name, category, met_value, difficulty, contraindications
     * (kb_diseases.slug values, per docs/08-Knowledge-Base.md §2.3).
     */
    public function run(): void
    {
        $exercises = [
            // Cardio — includes the two §2.3 examples
            ['Jalan Kaki (Brisk Walk)', 'cardio', 3.8, 'beginner', []],
            ['Jogging', 'cardio', 7.0, 'intermediate', []],
            ['Lari Cepat (Sprint)', 'cardio', 9.0, 'advanced', ['asam-urat']],
            ['Bersepeda Santai', 'cardio', 4.0, 'beginner', []],
            ['Bersepeda Cepat', 'cardio', 8.0, 'advanced', []],
            ['Lompat Tali (Jump Rope)', 'cardio', 11.0, 'intermediate', ['asam-urat']],
            ['Renang', 'cardio', 6.0, 'intermediate', []],
            ['Naik Turun Tangga', 'cardio', 8.0, 'intermediate', ['asam-urat']],
            ['Zumba', 'cardio', 6.5, 'intermediate', []],
            ['HIIT (High Intensity Interval Training)', 'cardio', 8.5, 'advanced', ['hipertensi', 'asam-urat']],

            // Strength
            ['Push Up', 'strength', 3.8, 'beginner', []],
            ['Pull Up', 'strength', 8.0, 'advanced', []],
            ['Squat Bodyweight', 'strength', 5.0, 'beginner', []],
            ['Angkat Beban Berat (Heavy Deadlift)', 'strength', 6.0, 'advanced', ['hipertensi']],
            ['Plank', 'strength', 3.0, 'beginner', []],
            ['Lunges', 'strength', 4.0, 'beginner', []],
            ['Bench Press', 'strength', 6.0, 'intermediate', ['hipertensi']],
            ['Dumbbell Row', 'strength', 4.5, 'intermediate', []],
            ['Sit Up', 'strength', 3.8, 'beginner', []],
            ['Burpees', 'strength', 8.0, 'advanced', ['hipertensi', 'asam-urat']],

            // Flexibility
            ['Yoga Ringan', 'flexibility', 2.5, 'beginner', []],
            ['Stretching Statis', 'flexibility', 2.3, 'beginner', []],
            ['Pilates', 'flexibility', 3.0, 'intermediate', []],
            ['Tai Chi', 'flexibility', 3.0, 'beginner', []],

            // Sport
            ['Badminton', 'sport', 5.5, 'intermediate', []],
            ['Sepak Bola (santai)', 'sport', 7.0, 'intermediate', ['asam-urat']],
            ['Basket', 'sport', 6.5, 'intermediate', ['asam-urat']],
            ['Tenis Meja', 'sport', 4.0, 'beginner', []],
        ];

        foreach ($exercises as [$name, $category, $met, $difficulty, $contraindications]) {
            KbExercise::updateOrCreate(
                ['name' => $name],
                [
                    'category' => $category,
                    'met_value' => $met,
                    'difficulty' => $difficulty,
                    'contraindications' => $contraindications,
                ]
            );
        }
    }
}
