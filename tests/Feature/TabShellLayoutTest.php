<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 탭 셸 레이아웃 — iframe 높이의 데스크탑 zoom 배율 보정 */
class TabShellLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_tab_iframe_height_compensates_ui_zoom(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        // dvh 지원 브라우저용 규칙도 zoom 보정을 포함해야 함 (미보정 시 하단 7% 잘림)
        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertSee('calc(100dvh / var(--ui-zoom, 1) - var(--chrome-h, 86px))', false)
            ->assertDontSee('calc(100dvh - var(--chrome-h, 86px))', false);
    }

    public function test_favicon_and_pwa_icons_use_new_logo_version(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertSee('/favicon.ico?v=3', false)
            ->assertSee('/favicon.svg?v=3', false)
            ->assertSee('/apple-touch-icon.png?v=3', false)
            ->assertSee('/icon-192.png?v=3', false);

        foreach (['favicon.ico', 'favicon.svg', 'icon-192.png', 'icon-512.png', 'apple-touch-icon.png', 'favicon-96x96.png'] as $f) {
            $this->assertFileExists(public_path($f));
        }
    }
}
