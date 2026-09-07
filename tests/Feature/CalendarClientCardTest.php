<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 캘린더 요약 뷰 의뢰자 카드 — 방문의뢰 외 카테고리(스튜디오/촬영 등)에서도 연동 의뢰자 정보 표시 */
class CalendarClientCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_renders_client_card_loader_for_non_gold_categories(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/calendar')->assertOk()
            ->assertSee('lsClientCard', false)       // 요약 뷰 의뢰자 카드 컨테이너
            ->assertSee('lsLoadClientCard', false)   // 비동기 로더 함수
            ->assertSee('lsClientDetailCache', false); // 재렌더 시 중복 요청 방지 캐시
    }

    public function test_client_detail_returns_fields_used_by_summary_card(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create([
            'name' => '홍길동', 'nickname' => '고블린', 'grade' => 'normal',
            'phone' => '010-1234-5678', 'platforms' => ['SOOP'], 'career' => '1년 이상',
        ]);

        $res = $this->actingAs($admin)->getJson("/api/clients/{$client->id}/detail")->assertOk()->json();

        $this->assertSame('고블린', $res['nickname']);
        $this->assertSame('홍길동', $res['name']);
        $this->assertSame('010-1234-5678', $res['phone']);
        $this->assertContains('SOOP', $res['platforms']);
        $this->assertSame('1년 이상', $res['career']);
    }
}
