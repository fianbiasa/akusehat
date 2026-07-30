<?php

namespace Tests\Feature;

use App\Jobs\ExportUserDataJob;
use App\Models\DataExport;
use App\Models\User;
use App\Notifications\DataExportReady;
use App\Services\UserDataExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class DataExportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_requesting_an_export_creates_a_pending_record_and_dispatches_the_job()
    {
        Bus::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->post('/settings/data-export')->assertSessionHasNoErrors();

        $this->assertDatabaseHas('data_exports', ['user_id' => $user->id, 'status' => 'pending']);
        Bus::assertDispatched(ExportUserDataJob::class, fn ($job) => $job->export->user_id === $user->id);
    }

    public function test_the_job_writes_a_json_file_and_notifies_the_user()
    {
        Storage::fake('local');
        Notification::fake();

        $user = User::factory()->create();
        $user->healthProfile()->create(['gender' => 'male', 'height_cm' => 170]);
        // At least one row in every manually-column-selected relation - an
        // empty relation's SELECT never actually validates its column list
        // against SQLite's schema (SQLite silently returns an empty result
        // rather than erroring on an unknown column when there are zero
        // rows to fetch, unlike MySQL), so a typo'd column name in
        // UserDataExportService would otherwise pass this test on SQLite
        // and only fail against the real MySQL database. See the
        // ai_memories 'content' vs 'summary'/'data' bug this caught.
        $user->aiMemories()->create(['memory_type' => 'trend', 'summary' => 'Weight trending down', 'data' => ['delta_kg' => -1.2]]);
        $export = $user->dataExports()->create(['status' => 'pending', 'created_at' => now()]);

        app(ExportUserDataJob::class, ['export' => $export])->handle(app(UserDataExportService::class));

        $export->refresh();
        $this->assertSame('ready', $export->status);
        $this->assertNotNull($export->file_path);
        Storage::disk('local')->assertExists($export->file_path);

        $contents = json_decode(Storage::disk('local')->get($export->file_path), true);
        $this->assertSame($user->email, $contents['profile']['email']);
        $this->assertSame('male', $contents['health_profile']['gender']);
        $this->assertSame('Weight trending down', $contents['ai_memories'][0]['summary']);

        Notification::assertSentTo($user, DataExportReady::class);
    }

    public function test_a_ready_export_can_be_downloaded_via_a_valid_signed_url_by_its_owner()
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $export = $user->dataExports()->create(['status' => 'ready', 'file_path' => "data-exports/{$user->id}/1.json", 'created_at' => now()]);
        Storage::disk('local')->put($export->file_path, json_encode(['ok' => true]));

        $url = URL::temporarySignedRoute('data-export.download', now()->addHour(), ['export' => $export->id]);

        $this->actingAs($user)->get($url)->assertOk();
    }

    public function test_an_unsigned_download_url_is_rejected()
    {
        $user = User::factory()->create();
        $export = $user->dataExports()->create(['status' => 'ready', 'file_path' => 'x.json', 'created_at' => now()]);

        $this->actingAs($user)->get("/data-export/{$export->id}/download")->assertForbidden();
    }

    public function test_a_signed_url_cannot_be_used_by_a_different_user()
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $export = $owner->dataExports()->create(['status' => 'ready', 'file_path' => "data-exports/{$owner->id}/1.json", 'created_at' => now()]);
        Storage::disk('local')->put($export->file_path, json_encode(['ok' => true]));

        $url = URL::temporarySignedRoute('data-export.download', now()->addHour(), ['export' => $export->id]);

        $this->actingAs($intruder)->get($url)->assertForbidden();
    }

    public function test_a_not_yet_ready_export_cannot_be_downloaded()
    {
        $user = User::factory()->create();
        $export = $user->dataExports()->create(['status' => 'pending', 'created_at' => now()]);

        $url = URL::temporarySignedRoute('data-export.download', now()->addHour(), ['export' => $export->id]);

        $this->actingAs($user)->get($url)->assertNotFound();
    }
}
