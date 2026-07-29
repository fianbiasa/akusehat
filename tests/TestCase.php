<?php

namespace Tests;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Baseline roles/permissions every RefreshDatabase test needs, since
     * users.role_id is a required FK — see RolePermissionSeeder.
     */
    protected bool $seed = true;

    protected string $seeder = RolePermissionSeeder::class;
}
