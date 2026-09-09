<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 의뢰자 중복 등록 방지 — 동일 전화번호면 기존 의뢰자 정보와 함께 확인 팝업('추가등록'/'등록 취소') */
class ClientDuplicatePhoneTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        Client::create(['nickname' => '고블린', 'name' => '홍길동', 'phone' => '010-1234-5678', 'grade' => 'normal']);
    }

    public function test_check_phone_matches_regardless_of_formatting(): void
    {
        // 하이픈 없는 입력도 같은 번호로 판정
        $res = $this->actingAs($this->admin)->getJson('/api/clients/check-phone?phone=01012345678')->assertOk()->json();
        $this->assertTrue($res['duplicate']);
        $this->assertSame('고블린', $res['existing']['nickname']);
        $this->assertSame('010-1234-5678', $res['existing']['phone']);

        // 다른 번호는 중복 아님
        $this->actingAs($this->admin)->getJson('/api/clients/check-phone?phone=010-9999-0000')
            ->assertOk()->assertJsonPath('duplicate', false);

        // 짧은 번호(7자리 미만)는 오탐 방지 위해 검사 제외
        $this->actingAs($this->admin)->getJson('/api/clients/check-phone?phone=5678')
            ->assertOk()->assertJsonPath('duplicate', false);
    }

    public function test_store_json_returns_409_with_existing_client_info(): void
    {
        $this->actingAs($this->admin)->postJson('/api/clients', [
            'nickname' => '새의뢰자', 'phone' => '01012345678', 'grade' => 'normal',
        ])->assertStatus(409)
            ->assertJsonPath('duplicate', true)
            ->assertJsonPath('existing.nickname', '고블린')
            ->assertJsonPath('existing.phone', '010-1234-5678');

        $this->assertSame(1, Client::count()); // 등록 안 됨
    }

    public function test_store_json_force_duplicate_creates_anyway(): void
    {
        // 팝업에서 '추가등록' 선택 — 강제 등록
        $this->actingAs($this->admin)->postJson('/api/clients', [
            'nickname' => '새의뢰자', 'phone' => '010-1234-5678', 'grade' => 'normal',
            'force_duplicate' => true,
        ])->assertCreated();

        $this->assertSame(2, Client::count());
    }

    public function test_classic_form_store_blocks_duplicate_and_allows_force(): void
    {
        // 등록 페이지(POST /clients) — 서버 폴백: 중복이면 입력 유지 + phone 오류로 되돌림
        $this->actingAs($this->admin)->post('/clients', [
            'nickname' => '새의뢰자', 'phone' => '010 1234 5678', 'grade' => 'normal',
        ])->assertSessionHasErrors('phone');
        $this->assertSame(1, Client::count());

        // '추가등록' (force_duplicate=1) — 정상 등록
        $this->actingAs($this->admin)->post('/clients', [
            'nickname' => '새의뢰자', 'phone' => '010-1234-5678', 'grade' => 'normal',
            'force_duplicate' => 1,
        ])->assertRedirect();
        $this->assertSame(2, Client::count());
    }

    public function test_client_without_phone_registers_without_check(): void
    {
        $this->actingAs($this->admin)->postJson('/api/clients', [
            'nickname' => '전화없는 의뢰자', 'grade' => 'normal',
        ])->assertCreated();

        $this->assertSame(2, Client::count());
    }
}
