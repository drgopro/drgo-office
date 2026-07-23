<?php

namespace Tests\Feature;

use App\Models\Client;
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
            ->assertSee('인적 정보');
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
