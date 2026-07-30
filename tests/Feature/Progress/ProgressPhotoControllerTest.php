<?php

namespace Tests\Feature\Progress;

use App\Models\Program;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ProgressPhotoControllerTest extends TestCase
{
    use RefreshDatabase;

    private function onboardedMember(): User
    {
        return User::factory()->create(['onboarding_completed_at' => now()]);
    }

    /**
     * Regression test: index() rebuilds a plain array per photo instead
     * of returning the model directly, which bypasses the 'date:Y-m-d'
     * cast unless logged_at is explicitly formatted - this is the exact
     * bug a live smoke test caught (full ISO8601 timestamp instead of a
     * plain date string).
     */
    public function test_the_photo_listing_returns_a_plain_date_string_not_a_full_timestamp()
    {
        Storage::fake('local');
        $user = $this->onboardedMember();
        $user->progressPhotos()->create(['logged_at' => today(), 'angle' => 'front', 'photo_path' => 'fake.jpg', 'is_private' => true, 'created_at' => now()]);

        $response = $this->actingAs($user)->getJson('/progress/photos');

        $response->assertOk()->assertJsonPath('0.logged_at', today()->toDateString());
    }

    public function test_uploading_a_photo_defaults_to_private()
    {
        Storage::fake('local');
        $user = $this->onboardedMember();

        $this->actingAs($user)->post('/progress/photos', [
            'angle' => 'front',
            'photo' => UploadedFile::fake()->image('progress.jpg'),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('progress_photos', ['user_id' => $user->id, 'is_private' => true]);
    }

    public function test_a_member_can_share_a_photo_to_their_coach()
    {
        Storage::fake('local');
        $user = $this->onboardedMember();
        $photo = $user->progressPhotos()->create([
            'logged_at' => today(), 'angle' => 'front', 'photo_path' => 'fake.jpg', 'is_private' => true, 'created_at' => now(),
        ]);

        $this->actingAs($user)->patch("/progress/photos/{$photo->id}", ['is_private' => false])->assertSessionHasNoErrors();

        $this->assertFalse($photo->fresh()->is_private);
    }

    public function test_a_coach_only_sees_photos_shared_with_them_not_private_ones()
    {
        $owner = $this->onboardedMember();
        $coach = User::factory()->create(['role_id' => Role::where('name', 'coach')->value('id'), 'onboarding_completed_at' => now()]);
        $program = Program::where('slug', 'diet-90-hari')->firstOrFail();
        $owner->programs()->create([
            'program_id' => $program->id, 'coach_id' => $coach->id, 'status' => 'active',
            'start_date' => today(), 'end_date' => today()->addDays(89), 'created_by' => 'ai',
        ]);

        $owner->progressPhotos()->create(['logged_at' => today(), 'angle' => 'front', 'photo_path' => 'private.jpg', 'is_private' => true, 'created_at' => now()]);
        $owner->progressPhotos()->create(['logged_at' => today(), 'angle' => 'side', 'photo_path' => 'shared.jpg', 'is_private' => false, 'created_at' => now()]);

        $response = $this->actingAs($coach)->getJson("/progress/photos?user_id={$owner->id}");

        $response->assertOk()->assertJsonCount(1);
    }

    public function test_a_member_cannot_update_another_members_photo()
    {
        $owner = $this->onboardedMember();
        $intruder = $this->onboardedMember();
        $photo = $owner->progressPhotos()->create(['logged_at' => today(), 'angle' => 'front', 'photo_path' => 'fake.jpg', 'is_private' => true, 'created_at' => now()]);

        $this->actingAs($intruder)->patch("/progress/photos/{$photo->id}", ['is_private' => false])->assertForbidden();
    }

    public function test_a_member_can_delete_their_own_photo()
    {
        Storage::fake('local');
        $user = $this->onboardedMember();
        $photo = $user->progressPhotos()->create(['logged_at' => today(), 'angle' => 'front', 'photo_path' => 'fake.jpg', 'is_private' => true, 'created_at' => now()]);

        $this->actingAs($user)->delete("/progress/photos/{$photo->id}")->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('progress_photos', ['id' => $photo->id]);
    }

    public function test_a_valid_signed_url_serves_the_photo_file()
    {
        Storage::fake('local');
        Storage::disk('local')->put('progress-photos/1/fake.jpg', 'fake-image-content');
        $user = $this->onboardedMember();
        $photo = $user->progressPhotos()->create(['logged_at' => today(), 'angle' => 'front', 'photo_path' => 'progress-photos/1/fake.jpg', 'is_private' => true, 'created_at' => now()]);

        $url = URL::temporarySignedRoute('progress.photos.show', now()->addMinutes(30), ['photo' => $photo->id]);

        $this->actingAs($user)->get($url)->assertOk();
    }

    public function test_an_unsigned_url_is_rejected()
    {
        Storage::fake('local');
        $user = $this->onboardedMember();
        $photo = $user->progressPhotos()->create(['logged_at' => today(), 'angle' => 'front', 'photo_path' => 'fake.jpg', 'is_private' => true, 'created_at' => now()]);

        $this->actingAs($user)->get("/progress/photos/{$photo->id}/file")->assertForbidden();
    }
}
