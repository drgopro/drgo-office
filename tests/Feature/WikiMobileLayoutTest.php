<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wiki;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 위키 상세 — 모바일 반응형 (헤더 세로 배치·여백 축소) */
class WikiMobileLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_wiki_show_includes_mobile_responsive_styles(): void
    {
        $user = User::factory()->create(['role' => 'member']);
        $wiki = Wiki::create(['title' => '거래처 사이트 모음', 'content' => '<p>본문</p>', 'category' => '물류']);

        $this->actingAs($user)->get("/wiki/{$wiki->id}")
            ->assertOk()
            ->assertSee('@media (max-width: 640px)', false)
            ->assertSee('.wiki-header { flex-direction:column;', false)
            ->assertSee('.wiki-content { padding:16px 14px;', false);
    }
}
