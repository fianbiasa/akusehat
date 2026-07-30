<?php

namespace Database\Seeders;

use App\Models\Achievement;
use Illuminate\Database\Seeder;

/**
 * Baseline achievement catalog (FR-ACH-01) covering both categories the
 * PRD names - "streaks, milestones" - across the 3 criteria types
 * AchievementCriteriaEvaluator supports. `icon` values are lucide-react
 * icon names, rendered client-side the same way app-sidebar.tsx already
 * maps icon names to components.
 */
class AchievementSeeder extends Seeder
{
    public function run(): void
    {
        $achievements = [
            [
                'name' => 'Turun 5kg',
                'description' => 'Berhasil menurunkan berat badan 5kg sejak mulai program.',
                'icon' => 'TrendingDown',
                'criteria' => ['type' => 'weight_loss_kg', 'kg' => 5],
            ],
            [
                'name' => 'Turun 10kg',
                'description' => 'Berhasil menurunkan berat badan 10kg sejak mulai program.',
                'icon' => 'TrendingDown',
                'criteria' => ['type' => 'weight_loss_kg', 'kg' => 10],
            ],
            [
                'name' => 'Konsisten 7 Hari',
                'description' => 'Menyelesaikan semua checklist harian selama 7 hari berturut-turut.',
                'icon' => 'Flame',
                'criteria' => ['type' => 'checklist_streak_days', 'days' => 7],
            ],
            [
                'name' => 'Konsisten 30 Hari',
                'description' => 'Menyelesaikan semua checklist harian selama 30 hari berturut-turut.',
                'icon' => 'Flame',
                'criteria' => ['type' => 'checklist_streak_days', 'days' => 30],
            ],
            [
                'name' => '1 Bulan Perjalanan',
                'description' => 'Sudah menjalani program selama 30 hari.',
                'icon' => 'Calendar',
                'criteria' => ['type' => 'program_milestone_days', 'days' => 30],
            ],
            [
                'name' => '90 Hari Transformasi',
                'description' => 'Sudah menjalani program penuh selama 90 hari.',
                'icon' => 'Trophy',
                'criteria' => ['type' => 'program_milestone_days', 'days' => 90],
            ],
        ];

        foreach ($achievements as $achievement) {
            Achievement::updateOrCreate(['name' => $achievement['name']], $achievement);
        }
    }
}
