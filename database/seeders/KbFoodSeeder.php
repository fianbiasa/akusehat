<?php

namespace Database\Seeders;

use App\Models\KbFood;
use Illuminate\Database\Seeder;

class KbFoodSeeder extends Seeder
{
    /**
     * A starter/demo set, not the full ~300-500 row TKPI-sourced catalog
     * docs/08-Knowledge-Base.md §7 calls for - per PRD §6.3, full content
     * curation is an explicit product-owner/SME task, not something to
     * fabricate wholesale here. Enough rows to exercise search/filtering
     * and let the Rule Engine's restriction tags mean something.
     *
     * Columns: name_local, category, calories, protein_g, carbs_g, fat_g, glycemic_index, tags
     */
    public function run(): void
    {
        $foods = [
            // Staple — per docs/08-Knowledge-Base.md §2.2
            ['Nasi Putih', 'staple', 130, 2.7, 28, 0.3, 73, ['halal']],
            ['Nasi Merah', 'staple', 110, 2.6, 23, 0.9, 55, ['halal', 'high_fiber']],
            ['Kentang Rebus', 'staple', 86, 1.9, 20, 0.1, 78, ['halal', 'vegetarian']],
            ['Ubi Jalar Rebus', 'staple', 86, 1.6, 20, 0.1, 44, ['halal', 'vegetarian', 'high_fiber']],
            ['Jagung Rebus', 'staple', 96, 3.4, 21, 1.5, 52, ['halal', 'vegetarian']],
            ['Roti Tawar Putih', 'staple', 265, 9, 49, 3.3, 75, ['halal', 'vegetarian']],
            ['Roti Gandum', 'staple', 247, 13, 41, 4.2, 53, ['halal', 'vegetarian', 'high_fiber']],
            ['Oatmeal', 'staple', 68, 2.4, 12, 1.4, 55, ['halal', 'vegetarian', 'high_fiber']],
            ['Mie Instan Goreng', 'staple', 380, 8, 56, 14, 80, ['halal']],

            // Protein — includes the two disease-relevant §2.2 examples
            ['Dada Ayam Panggang (tanpa kulit)', 'protein', 165, 31, 0, 3.6, 0, ['halal', 'high_protein', 'low_purine']],
            ['Tempe Goreng', 'protein', 195, 15, 12, 11, 15, ['halal', 'vegetarian']],
            ['Jeroan Sapi', 'protein', 224, 20, 0, 15, 0, ['halal']],
            ['Telur Rebus', 'protein', 155, 13, 1.1, 11, 0, ['halal', 'vegetarian', 'low_purine']],
            ['Ikan Lele Goreng', 'protein', 203, 18, 0, 14, 0, ['halal']],
            ['Ikan Kembung', 'protein', 103, 22, 0, 1.7, 0, ['halal', 'high_protein', 'low_purine']],
            ['Tahu Goreng', 'protein', 150, 12, 6, 10, 15, ['halal', 'vegetarian']],
            ['Daging Sapi Panggang', 'protein', 250, 26, 0, 15, 0, ['halal', 'high_protein']],
            ['Udang Rebus', 'protein', 99, 24, 0.2, 0.3, 0, ['halal', 'high_protein']],
            ['Salmon Panggang', 'protein', 206, 22, 0, 13, 0, ['halal', 'high_protein', 'low_purine']],

            // Vegetable — includes the §2.2 example
            ['Bayam Rebus', 'vegetable', 23, 2.9, 3.6, 0.4, 15, ['halal', 'vegetarian', 'high_fiber']],
            ['Brokoli Kukus', 'vegetable', 35, 2.4, 7, 0.4, 15, ['halal', 'vegetarian', 'high_fiber']],
            ['Wortel Rebus', 'vegetable', 35, 0.8, 8, 0.2, 39, ['halal', 'vegetarian', 'high_fiber']],
            ['Kangkung Tumis', 'vegetable', 30, 2.6, 4, 1.5, 15, ['halal', 'vegetarian']],
            ['Buncis Rebus', 'vegetable', 31, 1.8, 7, 0.2, 15, ['halal', 'vegetarian', 'high_fiber']],
            ['Tomat', 'vegetable', 18, 0.9, 3.9, 0.2, 15, ['halal', 'vegetarian']],
            ['Timun', 'vegetable', 15, 0.7, 3.6, 0.1, 15, ['halal', 'vegetarian']],

            // Fruit
            ['Pisang', 'fruit', 89, 1.1, 23, 0.3, 51, ['halal', 'vegetarian']],
            ['Apel', 'fruit', 52, 0.3, 14, 0.2, 36, ['halal', 'vegetarian', 'high_fiber']],
            ['Pepaya', 'fruit', 43, 0.5, 11, 0.3, 60, ['halal', 'vegetarian']],
            ['Semangka', 'fruit', 30, 0.6, 8, 0.2, 72, ['halal', 'vegetarian']],
            ['Jeruk', 'fruit', 47, 0.9, 12, 0.1, 40, ['halal', 'vegetarian', 'high_fiber']],
            ['Alpukat', 'fruit', 160, 2, 8.5, 15, 15, ['halal', 'vegetarian', 'high_fiber']],

            // Snack
            ['Keripik Singkong', 'snack', 450, 1, 60, 22, 75, ['halal', 'vegetarian']],
            ['Kacang Tanah Rebus', 'snack', 262, 12, 21, 17, 14, ['halal', 'vegetarian', 'high_fiber']],
            ['Biskuit Marie', 'snack', 450, 7, 79, 11, 70, ['halal', 'vegetarian']],

            // Drink
            ['Air Putih', 'drink', 0, 0, 0, 0, 0, ['halal', 'vegetarian']],
            ['Teh Tawar', 'drink', 1, 0, 0.3, 0, 0, ['halal', 'vegetarian']],
            ['Kopi Hitam Tanpa Gula', 'drink', 2, 0.3, 0, 0, 0, ['halal', 'vegetarian']],
            ['Susu Full Cream', 'drink', 61, 3.2, 4.8, 3.3, 30, ['halal', 'vegetarian']],
            ['Jus Jeruk Tanpa Gula', 'drink', 45, 0.7, 10, 0.2, 50, ['halal', 'vegetarian']],
        ];

        foreach ($foods as [$name, $category, $calories, $protein, $carbs, $fat, $gi, $tags]) {
            KbFood::updateOrCreate(
                ['name_local' => $name],
                [
                    'name' => $name,
                    'category' => $category,
                    'calories' => $calories,
                    'protein_g' => $protein,
                    'carbs_g' => $carbs,
                    'fat_g' => $fat,
                    'glycemic_index' => $gi,
                    'tags' => $tags,
                    'source' => 'Starter demo set - pending SME/TKPI review before production use (PRD §6.3)',
                ]
            );
        }
    }
}
