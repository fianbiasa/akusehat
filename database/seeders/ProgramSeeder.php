<?php

namespace Database\Seeders;

use App\Models\Program;
use Illuminate\Database\Seeder;

/**
 * Catalog starts with just "Diet & Transformasi 90 Hari" per
 * docs/11-Development-Checklist.md Phase 6 - other categories
 * (bulking/marathon/disease-management/etc.) are future catalog entries,
 * not something to fabricate content for ahead of demand.
 */
class ProgramSeeder extends Seeder
{
    public function run(): void
    {
        Program::updateOrCreate(
            ['slug' => 'diet-90-hari'],
            [
                'name' => 'Diet & Transformasi 90 Hari',
                'category' => 'diet',
                'description' => 'Program transformasi 90 hari yang menggabungkan target kalori, pola makan, olahraga, dan kebiasaan harian yang disesuaikan secara personal.',
                'default_duration_days' => 90,
                'is_active' => true,
            ],
        );
    }
}
