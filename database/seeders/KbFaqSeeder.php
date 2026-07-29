<?php

namespace Database\Seeders;

use App\Models\KbFaq;
use Illuminate\Database\Seeder;

class KbFaqSeeder extends Seeder
{
    public function run(): void
    {
        $faqs = [
            ['Bagaimana program disusun untuk saya?', 'Program kamu dihitung dari data onboarding lewat Rule Engine (baseline medis) dan disempurnakan oleh AI, lalu ditinjau ulang setiap minggu berdasarkan progresmu.', 'program', 1],
            ['Apakah saya bisa mengganti Coach?', 'Bisa, hubungi tim support untuk permintaan penggantian Coach.', 'coach', 2],
            ['Apakah data kesehatan saya aman?', 'Data kesehatanmu dienkripsi dan hanya bisa diakses oleh kamu dan Coach yang ditugaskan untukmu.', 'privacy', 3],
        ];

        foreach ($faqs as [$question, $answer, $category, $order]) {
            KbFaq::updateOrCreate(
                ['question' => $question],
                ['answer' => $answer, 'category' => $category, 'order' => $order, 'is_published' => true],
            );
        }
    }
}
