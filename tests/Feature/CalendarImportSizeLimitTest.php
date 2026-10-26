<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * 캘린더 JSON/iCal 가져오기 — 파일 전체를 메모리에 읽으므로 20MB 상한을 둔다.
 */
class CalendarImportSizeLimitTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_oversized_json_import_is_rejected(): void
    {
        $this->actingAs($this->admin)->postJson('/api/events/import/json', [
            'file' => UploadedFile::fake()->create('backup.json', 20481, 'application/json'),
        ])->assertStatus(422)->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_oversized_ical_import_is_rejected(): void
    {
        $this->actingAs($this->admin)->postJson('/api/events/import/ical', [
            'file' => UploadedFile::fake()->create('calendar.ics', 20481, 'text/calendar'),
        ])->assertStatus(422)->assertJsonValidationErrors('file');
    }

    public function test_json_import_within_limit_still_works(): void
    {
        $json = json_encode(['events' => [[
            'title' => '백업 일정', 'start_date' => '2026-10-27', 'end_date' => '2026-10-27',
        ]]]);

        $this->actingAs($this->admin)->postJson('/api/events/import/json', [
            'file' => UploadedFile::fake()->createWithContent('backup.json', $json),
        ])->assertOk();

        $this->assertDatabaseHas('schedules', ['title' => '백업 일정']);
    }
}
