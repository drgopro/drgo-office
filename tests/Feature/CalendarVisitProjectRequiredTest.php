<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 방문의뢰 새 일정 — 의뢰자 + 프로젝트 연결 필수 ('프로젝트는 나중에 연결' 사유를 적으면 예외).
 * 다른 유형과 기존 일정 수정에는 적용하지 않는다.
 */
class CalendarVisitProjectRequiredTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'master']);
    }

    /** @return array<string, mixed> */
    private function payload(string $color, ?array $requestData = null): array
    {
        return array_filter([
            'title' => '테스트 일정', 'start_date' => '2026-07-10', 'end_date' => '2026-07-10',
            'is_all_day' => true, 'color' => $color, 'request_data' => $requestData,
        ], fn ($v) => $v !== null);
    }

    public function test_new_visit_schedule_without_project_is_rejected(): void
    {
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);

        // 아무 연결도 없음 / 의뢰자만 연결 / 사유가 공백뿐
        foreach ([null, ['client_id' => $client->id], ['project_defer_reason' => '   ']] as $data) {
            $this->actingAs($this->user)->postJson('/api/events', $this->payload('gold', $data))
                ->assertStatus(422)
                ->assertJsonValidationErrors('project_id');
        }
        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_new_visit_schedule_with_project_or_defer_reason_is_saved(): void
    {
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $project = Project::create(['client_id' => $client->id, 'name' => '세팅', 'stage' => 'consulting']);

        $this->actingAs($this->user)->postJson('/api/events', $this->payload('gold', ['client_id' => $client->id, 'project_id' => $project->id]))
            ->assertCreated();

        $id = $this->actingAs($this->user)->postJson('/api/events', $this->payload('gold', ['project_defer_reason' => '급한 일정이라 먼저 잡음']))
            ->assertCreated()->json('id');
        $this->assertSame('급한 일정이라 먼저 잡음', Schedule::find($id)->request_data['project_defer_reason']);
    }

    public function test_other_categories_are_not_affected(): void
    {
        foreach (['teal', 'blue', 'red', 'green', 'purple'] as $color) {
            $this->actingAs($this->user)->postJson('/api/events', $this->payload($color))->assertCreated();
        }
    }

    public function test_editing_existing_visit_schedule_without_project_is_allowed(): void
    {
        $schedule = Schedule::create(['title' => '예전 방문', 'start_date' => '2026-07-10', 'end_date' => '2026-07-10', 'color' => 'gold', 'is_all_day' => true]);

        $this->actingAs($this->user)->postJson("/api/events/{$schedule->id}", [
            'title' => '예전 방문 (제목 수정)', 'start_date' => '2026-07-10', 'end_date' => '2026-07-10', 'is_all_day' => true, 'color' => 'gold',
        ])->assertOk();
        $this->assertSame('예전 방문 (제목 수정)', $schedule->fresh()->title);
    }

    public function test_calendar_page_has_defer_ui_and_client_side_check(): void
    {
        $this->actingAs($this->user)->get('/calendar')
            ->assertOk()
            ->assertSee('id="projectDeferChk"', false)
            ->assertSee('id="projectDeferReason"', false)
            ->assertSee('function projectLinkBlockMessage()', false)
            ->assertSee("if(currentColor!=='gold' || editingId) return null;", false)
            ->assertSee('project_defer_reason:(document.getElementById', false);
    }
}
