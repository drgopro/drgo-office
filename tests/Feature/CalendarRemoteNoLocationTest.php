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

    public function test_lock_summary_omits_location_line_for_remote_category_without_address(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->get('/calendar')
            ->assertOk()
            ->assertSee("const hideLoc=color==='teal'&&!locDisplay;", false)
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
}
