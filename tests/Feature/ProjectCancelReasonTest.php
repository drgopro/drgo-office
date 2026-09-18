<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 프로젝트 취소 사유 — 관리자 설정 기반 선택지 */
class ProjectCancelReasonTest extends TestCase
{
    use RefreshDatabase;

    private function makeProject(): Project
    {
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);

        return Project::create(['client_id' => $client->id, 'name' => '테스트', 'project_type' => 'visit', 'stage' => 'consulting']);
    }

    public function test_cancel_modal_shows_default_reasons(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $project = $this->makeProject();

        $this->actingAs($admin)->get("/projects/{$project->id}")
            ->assertOk()
            ->assertSee('의뢰자 연락 두절')
            ->assertSee('일정이 맞지 않음')
            ->assertSee('value="기타"', false);
    }

    public function test_cancel_modal_uses_admin_defined_reasons(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Setting::set('project_cancel_reasons', "가격 협의 실패\n타업체 계약\n기타"); // '기타' 줄은 중복 표시 방지

        $project = $this->makeProject();
        $res = $this->actingAs($admin)->get("/projects/{$project->id}");

        $res->assertOk()
            ->assertSee('가격 협의 실패')
            ->assertSee('타업체 계약')
            ->assertDontSee('의뢰자 연락 두절');
        // 취소 사유의 '기타' 라디오는 한 번만 (설정에 기타를 넣어도 중복 표시 안 됨)
        $this->assertSame(1, substr_count($res->getContent(), 'name="cancel_reason" value="기타"'));
    }

    public function test_cancel_saves_custom_reason(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $project = $this->makeProject();

        $this->actingAs($admin)->patchJson("/projects/{$project->id}/stage", [
            'stage' => 'cancelled',
            'cancel_reason' => '가격 협의 실패',
            'cancel_detail' => null,
        ])->assertOk();

        $fresh = $project->fresh();
        $this->assertSame('cancelled', $fresh->stage);
        $this->assertSame('가격 협의 실패', $fresh->cancel_reason);
    }

    public function test_cancel_saves_multiple_reasons_and_stage_snapshot(): void
    {
        // 복수 사유(', ' 결합) 저장 + 취소 직전 진행 단계 기록
        $admin = User::factory()->create(['role' => 'admin']);
        $project = $this->makeProject();
        $project->update(['stage' => 'payment']);

        $this->actingAs($admin)->patchJson("/projects/{$project->id}/stage", [
            'stage' => 'cancelled',
            'cancel_reason' => '의뢰자 연락 두절, 일정이 맞지 않음',
            'cancel_detail' => null,
        ])->assertOk();

        $fresh = $project->fresh();
        $this->assertSame('의뢰자 연락 두절, 일정이 맞지 않음', $fresh->cancel_reason);
        $this->assertSame('payment', $fresh->cancelled_from_stage);

        // 상세 화면에 취소 시점 단계 표시 + 사유 선택지가 체크박스(복수 선택)
        $this->actingAs($admin)->get("/projects/{$project->id}")->assertOk()
            ->assertSee('취소 시점: 결제/예약 단계', false)
            ->assertSee('type="checkbox" name="cancel_reason"', false);
    }

    public function test_cancelled_project_greys_stage_bar_with_cancel_point_and_reopen(): void
    {
        // 취소된 프로젝트 — 프로세스 바 전체 회색·선택 불가, 취소 시점 단계 ✕ 표기 + 취소 해제 버튼
        $admin = User::factory()->create(['role' => 'admin']);
        $project = $this->makeProject();
        $project->update(['stage' => 'payment']);
        $this->actingAs($admin)->patchJson("/projects/{$project->id}/stage", [
            'stage' => 'cancelled', 'cancel_reason' => '의뢰자 연락 두절',
        ])->assertOk();

        $res = $this->actingAs($admin)->get("/projects/{$project->id}")->assertOk();
        $res->assertSee('취소된 프로젝트 (변경하려면 취소 해제)')
            ->assertSee('step-dot cancelled', false)          // 회색·비활성 단계 점
            ->assertSee('cancel-point', false)                 // 취소 시점 강조
            ->assertSee('결제/예약 단계에서 취소됨', false)     // 시점 툴팁
            ->assertSee('· 취소', false)                       // 라벨 표기
            ->assertSee('↺ 취소 해제', false);
        // 마지막 단계까지 전부 비활성(disabled) — 활성 단계 폼 없음
        $this->assertStringNotContainsString('name="stage" value="done"', $res->getContent());

        // 취소 해제 — 취소 시점 단계로 복구
        $this->actingAs($admin)->patchJson("/projects/{$project->id}/stage", ['stage' => 'payment'])->assertOk();
        $fresh = $project->fresh();
        $this->assertSame('payment', $fresh->stage);
        $this->actingAs($admin)->get("/projects/{$project->id}")->assertOk()
            ->assertDontSee('취소된 프로젝트 (변경하려면 취소 해제)');
    }

    public function test_admin_can_save_reasons_setting(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->postJson('/api/settings', [
            'project_cancel_reasons' => "사유A\n사유B",
        ])->assertOk();

        $this->assertSame("사유A\n사유B", Setting::get('project_cancel_reasons'));
    }
}
