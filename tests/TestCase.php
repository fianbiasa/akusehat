<?php

namespace Tests;

use Database\Seeders\BaselineSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Baseline data every RefreshDatabase test needs: roles/permissions
     * (users.role_id is a required FK) and onboarding questions.
     */
    protected bool $seed = true;

    protected string $seeder = BaselineSeeder::class;
}
