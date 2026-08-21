<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Consultation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 익명(의뢰자 미연동) 프로젝트의 상담 이력 등록 — client_id 없이도 저장돼야 함 */
class AnonymousProjectConsultationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return [
            'consulted_at' => '2026-08-20',
            'consult_type' => 'phone',
            'result' => 'in_progress',
            'content' => '전화 상담 내용',
        ];
    }

    public function test_consultation_saves_on_anonymous_project(): void
    {
        $user = User::factory()->create(['role' => 'master']);
        $project = Project::create([
            'client_id' => null, 'manual_client_name' => '익명 문의자',
            'name' => '익명 프로젝트', 'project_type' => 'visit', 'stage' => 'consulting',
        ]);

        $this->actingAs($user)
            ->post("/projects/{$project->id}/consultations", $this->payload())
            ->assertRedirect();

        $c = Consultation::where('project_id', $project->id)->first();
        $this->assertNotNull($c);
        $this->assertNull($c->client_id);
        $this->assertSame('전화 상담 내용', $c->content);
    }

    public function test_anonymous_project_page_uses_simplified_view(): void
    {
        $user = User::factory()->create(['role' => 'master']);
        $anon = Project::create([
            'client_id' => null, 'manual_client_name' => '익명 문의자',
            'name' => '익명 프로젝트', 'project_type' => 'visit', 'stage' => 'consulting',
        ]);
        $client = Client::create(['nickname' => '연동 의뢰자', 'grade' => 'normal']);
        $linked = Project::create([
            'client_id' => $client->id, 'name' => '연동 프로젝트', 'project_type' => 'visit', 'stage' => 'consulting',
        ]);

        // 익명 — 간소화 뷰 클래스 적용, 연동 — 미적용 (CSS 선택자 텍스트와 구분되게 class 속성으로 검사)
        $this->actingAs($user)->get("/projects/{$anon->id}")->assertOk()->assertSee('page-wrap anon-proj', false);
        $this->actingAs($user)->get("/projects/{$linked->id}")->assertOk()->assertDontSee('page-wrap anon-proj', false);
    }

    public function test_consultation_still_updates_last_contact_for_linked_client(): void
    {
        $user = User::factory()->create(['role' => 'master']);
        $client = Client::create(['nickname' => '연동 의뢰자', 'grade' => 'normal', 'last_contact_at' => null]);
        $project = Project::create([
            'client_id' => $client->id, 'name' => '연동 프로젝트',
            'project_type' => 'visit', 'stage' => 'consulting',
        ]);

        $this->actingAs($user)
            ->post("/projects/{$project->id}/consultations", $this->payload())
            ->assertRedirect();

        $this->assertSame($client->id, Consultation::where('project_id', $project->id)->value('client_id'));
        $this->assertNotNull($client->fresh()->last_contact_at);
    }
}
