<?php

namespace Tests\Feature\Progress;

use App\Models\Program;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * weight/waist/body-fat/sleep are structurally identical (unique-per-day
 * metric logs) - covered together rather than duplicating the same test
 * shape 4 times. water is covered separately since it allows multiple
 * entries per day.
 */
class ProgressLogsTest extends TestCase
{
    use RefreshDatabase;

    private function onboardedMember(): User
    {
        return User::factory()->create(['onboarding_completed_at' => now()]);
    }

    public static function metricProvider(): array
    {
        return [
            'weight' => ['weight', 'weight_kg', 75],
            'waist' => ['waist', 'waist_cm', 90],
            'body-fat' => ['body-fat', 'body_fat_pct', 20],
            'sleep' => ['sleep', 'sleep_hours', 7.5],
        ];
    }

    #[DataProvider('metricProvider')]
    public function test_a_member_can_log_and_list_their_own_metric(string $endpoint, string $field, float $value)
    {
        $user = $this->onboardedMember();

        $this->actingAs($user)->post("/progress/{$endpoint}", [$field => $value])->assertSessionHasNoErrors();

        $response = $this->actingAs($user)->getJson("/progress/{$endpoint}");
        $response->assertOk()->assertJsonCount(1);
    }

    #[DataProvider('metricProvider')]
    public function test_logging_the_same_day_twice_updates_rather_than_duplicates(string $endpoint, string $field, float $value)
    {
        $user = $this->onboardedMember();

        $this->actingAs($user)->post("/progress/{$endpoint}", [$field => $value]);
        $this->actingAs($user)->post("/progress/{$endpoint}", [$field => $value + 1]);

        $response = $this->actingAs($user)->getJson("/progress/{$endpoint}");
        $response->assertOk()->assertJsonCount(1);
        $this->assertEquals($value + 1, $response->json('0.'.$field));
    }

    #[DataProvider('metricProvider')]
    public function test_a_member_cannot_view_another_members_metric_without_a_coach_relationship(string $endpoint, string $field, float $value)
    {
        $owner = $this->onboardedMember();
        $intruder = $this->onboardedMember();
        $owner->weightLogs()->create(['logged_at' => today(), 'weight_kg' => 70, 'created_at' => now()]);

        $this->actingAs($intruder)->getJson("/progress/{$endpoint}?user_id={$owner->id}")->assertForbidden();
    }

    #[DataProvider('metricProvider')]
    public function test_an_assigned_coach_can_view_a_members_metric(string $endpoint, string $field, float $value)
    {
        $owner = $this->onboardedMember();
        $coach = User::factory()->create(['role_id' => Role::where('name', 'coach')->value('id'), 'onboarding_completed_at' => now()]);
        $program = Program::where('slug', 'diet-90-hari')->firstOrFail();
        $owner->programs()->create([
            'program_id' => $program->id, 'coach_id' => $coach->id, 'status' => 'active',
            'start_date' => today(), 'end_date' => today()->addDays(89), 'created_by' => 'ai',
        ]);

        $this->actingAs($coach)->getJson("/progress/{$endpoint}?user_id={$owner->id}")->assertOk();
    }

    public function test_weight_rejects_an_unrealistic_value()
    {
        $user = $this->onboardedMember();

        $this->actingAs($user)->post('/progress/weight', ['weight_kg' => 5])->assertSessionHasErrors('weight_kg');
    }

    public function test_water_allows_multiple_entries_per_day_and_sums_them()
    {
        $user = $this->onboardedMember();

        $this->actingAs($user)->post('/progress/water', ['amount_ml' => 300]);
        $this->actingAs($user)->post('/progress/water', ['amount_ml' => 500]);

        $response = $this->actingAs($user)->getJson('/progress/water');
        $response->assertOk();
        $this->assertCount(2, $response->json('entries'));
        $this->assertSame(800, $response->json('daily_totals.0.total_ml'));
    }
}
