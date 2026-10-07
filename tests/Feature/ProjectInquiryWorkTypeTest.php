<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 프로젝트 작업 유형 — 문의 유형은 '단순'이 기본값이고, 익명(의뢰자명 확인
 * 불가) 등록에서도 작업 유형을 선택해 어떤 문의였는지 구분할 수 있다.
 */
class ProjectInquiryWorkTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonymous_project_saves_work_type_without_scale(): void
    {
        $user = User::factory()->create(['role' => 'master']);

        $this->actingAs($user)->postJson('/api/projects', [
            'name' => '익명 문의 구분',
            'project_type' => 'inquiry',
            'work_type' => 'simple',
            'client_scale' => null,
            'manual_client_name' => null,
        ])->assertSuccessful();

        $p = Project::where('name', '익명 문의 구분')->firstOrFail();
        $this->assertNull($p->client_id);
        $this->assertSame('inquiry', $p->project_type);
        $this->assertSame('simple', $p->work_type);
        $this->assertNull($p->client_scale);
    }

    public function test_project_list_page_renders_inquiry_default_and_anonymous_work_type_ui(): void
    {
        $user = User::factory()->create(['role' => 'master']);

        $this->actingAs($user)->get('/projects')->assertOk()
            // 문의 유형 기본값 '단순' 선택 로직
            ->assertSee("v === 'simple' || l === '단순'", false)
            // 익명: 규모 칸만 숨기고 작업 유형은 남김
            ->assertSee('id="npScaleCol"', false)
            ->assertSee("set('npScaleCol', 'block', noClient)", false);
    }

    public function test_client_page_project_form_has_inquiry_default(): void
    {
        $user = User::factory()->create(['role' => 'master']);

        $this->actingAs($user)->get('/clients')->assertOk()
            ->assertSee("v === 'simple' || l === '단순'", false);
    }
}
