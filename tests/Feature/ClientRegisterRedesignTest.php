<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectFieldDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 의뢰자 등록 폼 리디자인 — 히어로+번호 카드+작성 현황 (디자인 1a), 장비는 자동 연동 안내(2b) */
class ClientRegisterRedesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_modal_renders_redesigned_layout(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->get('/clients')
            ->assertOk()
            // 히어로 닉네임 + 번호 카드 + 작성 현황 사이드바
            ->assertSee('ncm-hero', false)
            ->assertSee('작성 현황')
            ->assertSee('남은 필수 항목')
            ->assertSee('data-sec="플랫폼 / 방송"', false)
            // 장비 정보는 직접 입력 없이 자동 연동 안내 (읽기 전용)
            ->assertSee('자동 연동')
            ->assertSee('프로젝트에서 자동으로 불러옵니다')
            // 신규 기본 정보 필드
            ->assertSee('id="ncGender"', false)
            ->assertSee('id="ncAddress"', false)
            ->assertSee('id="ncMemo"', false);
    }

    public function test_detail_view_renders_readonly_layout_with_edit_toggle(): void
    {
        // 조회 페이지 리디자인 (디자인 3a) — 읽기 전용 섹션 뷰 + 수정 버튼 토글, 장비는 프로젝트 연동
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->get('/clients')
            ->assertOk()
            ->assertSee('function renderClientView', false)
            ->assertSee('function clientEditMode', false)
            ->assertSee('cv-sec', false)
            ->assertSee('프로젝트 연동')
            ->assertSee('인적 정보')
            // 인적 정보에 연락처 표시 (d.phone)
            ->assertSee("cvField('연락처', d.phone)", false);
    }

    public function test_equipment_falls_back_to_last_project_with_data(): void
    {
        // 최신 프로젝트에 장비 정보가 없으면, 장비 정보가 적힌 마지막 프로젝트에서 가져오고 출처 표기
        $user = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '스트리머B', 'grade' => 'normal']);
        ProjectFieldDefinition::create([
            'key' => 'main_pc', 'label' => '송출용 컴퓨터', 'type' => 'text',
            'section' => 'equipment', 'subsection' => 'PC', 'is_active' => true, 'sort_order' => 1,
        ]);

        $old = Project::create([
            'client_id' => $client->id, 'name' => '장비 있는 과거 프로젝트', 'project_type' => 'visit',
            'stage' => 'done', 'custom_data' => ['main_pc' => 'RTX 4080 송출용'],
        ]);
        $old->forceFill(['created_at' => now()->subMonths(2)])->save();
        Project::create([
            'client_id' => $client->id, 'name' => '장비 없는 최신 프로젝트', 'project_type' => 'remote', 'stage' => 'consulting',
        ]);

        $eq = $this->actingAs($user)->getJson("/api/clients/{$client->id}/detail")
            ->assertOk()->json('last_project_equipment');

        $this->assertSame($old->id, $eq['project_id'], '장비 정보가 있는 마지막 프로젝트로 폴백');
        $this->assertFalse($eq['is_latest']);
        $this->assertSame('RTX 4080 송출용', $eq['fields'][0]['value']);

        // 최신 프로젝트에 장비를 입력하면 그쪽이 우선
        Project::where('name', '장비 없는 최신 프로젝트')->first()
            ->update(['custom_data' => ['main_pc' => '새 PC']]);
        $eq2 = $this->actingAs($user)->getJson("/api/clients/{$client->id}/detail")->json('last_project_equipment');
        $this->assertSame('장비 없는 최신 프로젝트', $eq2['project_name']);
        $this->assertTrue($eq2['is_latest']);
    }

    public function test_store_accepts_new_basic_info_fields(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->postJson('/api/clients', [
            'nickname' => '스트리머A', 'grade' => 'normal',
            'gender' => 'female', 'affiliation' => '소속사X',
            'address' => '서울 송파구 송파대로 1', 'address_detail' => '401호',
            'important_memo' => '오후 연락 선호', 'memo' => '내부 메모',
        ])->assertCreated();

        $client = Client::first();
        $this->assertSame('female', $client->gender);
        $this->assertSame('서울 송파구 송파대로 1', $client->address);
        $this->assertSame('401호', $client->address_detail);
        $this->assertSame('오후 연락 선호', $client->important_memo);
        $this->assertSame('내부 메모', $client->memo);
    }
}
