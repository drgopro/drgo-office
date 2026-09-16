<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Consultation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 상담 등록/수정/삭제 후 리다이렉트 — back()이 첨부 미디어 GET(세션 이전 URL 오염)에 이끌려
 * 등록 직후 사진/영상 파일 URL로 이동하던 버그. 항상 프로젝트 상세로 명시 리다이렉트.
 */
class ConsultationRedirectTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $this->project = Project::create(['client_id' => $client->id, 'name' => '캠 세팅', 'project_type' => 'visit', 'stage' => 'consulting']);
    }

    public function test_store_redirects_to_project_even_with_polluted_previous_url(): void
    {
        // 첨부 영상 조회가 세션 이전 URL을 미디어 파일로 바꿔놓은 상황 재현
        $this->actingAs($this->admin)
            ->withSession(['_previous' => ['url' => url('/project-documents/99/view')]])
            ->post("/projects/{$this->project->id}/consultations", [
                'consulted_at' => now()->format('Y-m-d'),
                'consult_type' => 'phone',
                'result' => 'in_progress',
                'content' => '전화 상담',
            ])
            ->assertRedirect(route('projects.show', $this->project));

        $this->assertSame(1, Consultation::where('project_id', $this->project->id)->count());
    }

    public function test_update_and_destroy_redirect_to_project(): void
    {
        $consultation = Consultation::create([
            'project_id' => $this->project->id, 'client_id' => $this->project->client_id,
            'consulted_at' => now(), 'consult_type' => 'phone', 'result' => 'in_progress',
            'consultant_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->patch("/consultations/{$consultation->id}", [
            'consulted_at' => now()->format('Y-m-d'),
            'consult_type' => 'kakao',
            'result' => 'done',
        ])->assertRedirect(route('projects.show', $this->project));

        $this->actingAs($this->admin)
            ->withSession(['_previous' => ['url' => url('/project-documents/99/view')]])
            ->delete("/consultations/{$consultation->id}")
            ->assertRedirect(route('projects.show', $this->project));
        $this->assertSame(0, Consultation::count());
    }
}
