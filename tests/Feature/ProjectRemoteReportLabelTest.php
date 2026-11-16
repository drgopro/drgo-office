<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 원격 프로젝트 — 보고서 라벨만 '원격 보고서'로 (로직은 방문 보고서와 동일) */
class ProjectRemoteReportLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_remote_project_shows_remote_report_label(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $project = Project::create(['client_id' => $client->id, 'name' => '원격 건', 'project_type' => 'remote', 'stage' => 'done']);

        $html = $this->actingAs($admin)->get("/projects/{$project->id}")->assertOk()->getContent();

        $this->assertStringContainsString('📋 원격 보고서', $html);
        $this->assertStringNotContainsString('📋 방문 보고서', $html);
    }

    public function test_visit_project_keeps_visit_report_label(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $project = Project::create(['client_id' => $client->id, 'name' => '방문 건', 'project_type' => 'visit', 'stage' => 'done']);

        $html = $this->actingAs($admin)->get("/projects/{$project->id}")->assertOk()->getContent();

        $this->assertStringContainsString('📋 방문 보고서', $html);
        $this->assertStringNotContainsString('📋 원격 보고서', $html);
    }

    public function test_inquiry_project_skips_report_prompt_and_hides_empty_report_card(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $project = Project::create(['client_id' => $client->id, 'name' => '문의 건', 'project_type' => 'inquiry', 'stage' => 'done']);

        $html = $this->actingAs($admin)->get("/projects/{$project->id}")->assertOk()->getContent();

        // 완료 클릭 시 보고서를 묻지 않고 바로 완료 처리
        $this->assertMatchesRegularExpression('/confirmDoneStage = function\(\) \{\s+\/\/[^\n]*\n\s+if \(true\) \{\s+return advanceToDone\(false\);/', $html);
        // 완료 상태여도 빈 보고서 칸은 숨김
        $this->assertMatchesRegularExpression('/id="visitReportCard" style="display:none;"/', $html);
    }

    public function test_inquiry_project_still_shows_existing_report(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $project = Project::create(['client_id' => $client->id, 'name' => '문의 건', 'project_type' => 'inquiry', 'stage' => 'done', 'visit_report' => '<p>예전에 적은 보고서</p>']);

        $html = $this->actingAs($admin)->get("/projects/{$project->id}")->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/id="visitReportCard" style="display:block;"/', $html);
    }

    public function test_visit_project_asks_for_report_with_button_matching_wording(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $project = Project::create(['client_id' => $client->id, 'name' => '방문 건', 'project_type' => 'visit', 'stage' => 'done']);

        $html = $this->actingAs($admin)->get("/projects/{$project->id}")->assertOk()->getContent();

        $this->assertStringContainsString('if (false) {', $html);
        $this->assertStringContainsString('확인: 완료 처리 후 보고서 작성\\n취소: 보고서 없이 완료만 처리', $html);
        $this->assertStringNotContainsString('예: 즉시 작성 / 아니오: 완료만 처리', $html);
        $this->assertMatchesRegularExpression('/id="visitReportCard" style="display:block;"/', $html);
    }

    public function test_confirming_report_reloads_so_stage_bar_updates_then_reopens_report_editor(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $project = Project::create(['client_id' => $client->id, 'name' => '방문 건', 'project_type' => 'visit', 'stage' => 'consulting']);

        $html = $this->actingAs($admin)->get("/projects/{$project->id}")->assertOk()->getContent();

        // '확인'을 눌러도 새로고침해 진행 단계가 완료로 보이게 하고, 새로고침 뒤 보고서 칸을 열 표시를 남긴다
        $this->assertMatchesRegularExpression('/if \(openEditor\) \{\s+try \{ sessionStorage\.setItem\(VR_OPEN_AFTER_DONE_KEY, \'1\'\); \} catch \(e\) \{\}\s+\}\s+location\.reload\(\);/', $html);
        $this->assertStringContainsString("const VR_OPEN_AFTER_DONE_KEY = 'vrOpenAfterDone:{$project->id}';", $html);
        $this->assertStringContainsString("window.vrSetMode('edit')", $html);
        // 새로고침 없이 카드만 띄우던 예전 분기는 없어야 한다
        $this->assertStringNotContainsString("// '아니오' 선택 시 단계 UI 갱신 위해 새로고침", $html);
    }
}
