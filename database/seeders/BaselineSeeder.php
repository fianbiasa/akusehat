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
        $this->call(OnboardingQuestionSeeder::class);
    }
}
