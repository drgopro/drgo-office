<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 원격/방송룸(teal) 일정 — 장소 영역 숨김 (폼·요약 뷰) */
class CalendarRemoteNoLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_hides_address_block_for_remote_category(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->get('/calendar')
            ->assertOk()
            ->assertSee("addr.style.display=currentColor==='teal'?'none':''", false);
    }

    public function test_lock_summary_omits_location_line_for_remote_category(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->get('/calendar')
            ->assertOk()
            ->assertSee("const hideLoc=color==='teal';", false)
            ->assertSee("\${hideLoc?'':addrActions}", false)
            ->assertSee("lsCard(hideLoc?'일시':'일시 · 장소'", false);
    }

    public function test_switching_to_remote_category_clears_hidden_location_on_save(): void
    {
        // 다른 카테고리에서 teal로 바꾸면 숨겨진 주소가 몰래 저장되지 않아야 함 (원래 teal이던 일정의 기존 주소는 보존)
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->get('/calendar')
            ->assertOk()
            ->assertSee("return currentColor==='teal' && !(editingId && editingOrigDT && editingOrigDT.color==='teal');", false)
            ->assertSee("location:dropLoc?'':[", false)
            ->assertSee('entrance_info:dropLoc?null:(', false);
    }

    public function test_client_address_hint_toast_is_skipped_for_remote_category(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->get('/calendar')
            ->assertOk()
            ->assertSee("if(currentColor!=='teal'&&d.address&&", false);
    }

    public function test_rental_history_toggle_is_hidden_for_remote_mode(): void
    {
        // 대여 이력 등록은 '방송룸 이용' 유형에서만 — 원격 유형에서는 숨기고, 유형 전환 시 표시 갱신
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->get('/calendar')
            ->assertOk()
            ->assertSee("if(currentColor==='teal'&&(getRadio('teal_mode_group')||'remote')!=='studio') return null;", false)
            ->assertSee("style.display=v==='studio'?'':'none';if(typeof updateBrRentalUI==='function') updateBrRentalUI();}", false);
    }
}
