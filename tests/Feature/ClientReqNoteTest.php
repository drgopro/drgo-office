<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 의뢰자 요구사항 수기 메모 — 프로젝트 의뢰 내용 카드 하단, 캘린더 일정에 연동 표시 */
class ClientReqNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_note_saves_via_custom_data_and_exposed_to_calendar(): void
    {
        $admin = User::factory()->create(['role' => 'master']);
        $client = Client::create(['name' => '테스트 의뢰자', 'grade' => 'normal']);
        $project = Project::create(['client_id' => $client->id, 'name' => '캠 세팅']);

        // 자동 저장 경로 — custom_data 병합 PATCH
        $this->actingAs($admin)->patchJson("/api/projects/{$project->id}", [
            'custom_data' => ['__client_req_note' => '웹캠 화질 개선 요청'],
        ])->assertOk();

        // 캘린더가 쓰는 request-items API에 함께 노출
        $this->actingAs($admin)->getJson("/api/projects/{$project->id}/request-items")
            ->assertOk()
            ->assertJsonPath('client_req_note', '웹캠 화질 개선 요청');

        // 프로젝트 페이지 — 입력란 렌더 + 저장값 프리필
        $this->actingAs($admin)->get("/projects/{$project->id}")->assertOk()
            ->assertSee('clientReqNote', false)
            ->assertSee('의뢰자 요구사항')
            ->assertSee('웹캠 화질 개선 요청');

        // 캘린더 — 표시 블록 렌더 (reqNoteHtml)
        $this->actingAs($admin)->get('/calendar')->assertOk()
            ->assertSee('reqNoteHtml', false)
            ->assertSee('의뢰자 요구사항', false);
    }
}
