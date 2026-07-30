<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/settings/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/settings/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/settings/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/settings/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertTrue($user->fresh()->trashed());
    }

    public function test_a_deleted_users_data_is_retained_not_hard_deleted()
    {
        $user = User::factory()->create();
        $user->healthProfile()->create(['gender' => 'male', 'height_cm' => 170]);

        $this->actingAs($user)->delete('/settings/profile', ['password' => 'password']);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('health_profiles', ['user_id' => $user->id]);
    }

    public function test_a_deleted_user_can_no_longer_log_in()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->delete('/settings/profile', ['password' => 'password']);

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertGuest();
    }

    public function test_a_deleted_users_api_tokens_are_revoked()
    {
        $user = User::factory()->create();
        $user->createToken('test-token');

        $this->actingAs($user)->delete('/settings/profile', ['password' => 'password']);

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/settings/profile')
            ->delete('/settings/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/settings/profile');

        $this->assertNotNull($user->fresh());
    }
}
