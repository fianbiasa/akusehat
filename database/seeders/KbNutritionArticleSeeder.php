<?php

namespace Database\Seeders;

use App\Models\KbNutritionArticle;
use Illuminate\Database\Seeder;

class KbNutritionArticleSeeder extends Seeder
{
    public function run(): void
    {
        $articles = [
            [
                'title' => 'Memahami BMI, BMR, dan TDEE',
                'slug' => 'memahami-bmi-bmr-tdee',
                'category' => 'basics',
                'content' => 'BMI (Body Mass Index) menunjukkan proporsi berat terhadap tinggi badan. BMR (Basal Metabolic Rate) adalah kalori yang tubuhmu bakar saat istirahat total. TDEE (Total Daily Energy Expenditure) adalah BMR dikalikan tingkat aktivitas hariamu — inilah yang jadi acuan target kalori program kamu.',
            ],
            [
                'title' => 'Dasar-dasar Makronutrien',
                'slug' => 'dasar-dasar-makronutrien',
                'category' => 'basics',
                'content' => 'Protein membangun dan memperbaiki otot, karbohidrat adalah sumber energi utama, dan lemak sehat mendukung hormon serta penyerapan vitamin. Rasio ideal berbeda-beda tergantung tujuan dan kondisi kesehatanmu.',
            ],
            [
                'title' => 'Makan Sehat dengan Diabetes Tipe 2',
                'slug' => 'makan-sehat-diabetes-tipe-2',
                'category' => 'disease_management',
                'content' => 'Fokus pada makanan berindeks glikemik rendah, porsi karbohidrat yang terkontrol, dan makan dengan jadwal teratur membantu menjaga kadar gula darah tetap stabil.',
            ],
        ];

        foreach ($articles as $article) {
            KbNutritionArticle::updateOrCreate(['slug' => $article['slug']], [...$article, 'is_published' => true]);
        }
    }
}
