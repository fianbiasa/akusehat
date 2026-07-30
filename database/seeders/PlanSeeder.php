<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Baseline plan tiers (docs/09-UI-UX-Wireframe.md's "Langganan" tab and
 * 05-API-Specification.md §14 assume a real catalog exists, but neither
 * doc names actual tiers/pricing - this is a designed scaffold, not
 * business-decided pricing, matching the PRD's own open question in
 * §12 about the AI-cost/subscription-pricing model). `gratis` is the
 * default every new user lands on (SubscriptionService), so it must
 * always exist and stay `is_active`.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Gratis',
                'slug' => 'gratis',
                'price' => 0,
                'billing_cycle' => 'monthly',
                'features' => ['Program Diet 90 Hari', 'Rule Engine adaptif', 'Progress tracking & Health Score'],
                'max_programs' => 1,
                'has_coach_access' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Premium Bulanan',
                'slug' => 'premium-bulanan',
                'price' => 99000,
                'billing_cycle' => 'monthly',
                'features' => ['Semua fitur Gratis', 'Akses Coach pribadi', 'Hingga 3 program berjalan', 'AI review mingguan prioritas'],
                'max_programs' => 3,
                'has_coach_access' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Premium Tahunan',
                'slug' => 'premium-tahunan',
                'price' => 990000,
                'billing_cycle' => 'yearly',
                'features' => ['Semua fitur Premium Bulanan', 'Hemat 2 bulan dibanding bulanan'],
                'max_programs' => 3,
                'has_coach_access' => true,
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
