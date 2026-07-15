<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 사용 가이드 — 허브 페이지 + 정적 문서 서빙 */
class GuideTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'member']);
    }

    public function test_hub_lists_all_guides(): void
    {
        $this->actingAs($this->user)->get('/guide')
            ->assertOk()
            ->assertSee('사용 가이드')
            ->assertSee('캘린더 · 일정')
            ->assertSee('프로젝트 관리')
            ->assertSee('의뢰자 관리')
            ->assertSee('렌탈 · 방송룸')
            ->assertSee('/guide/calendar')
            ->assertSee('/guide/rental-broadcast');
    }

    public function test_each_guide_document_is_served(): void
    {
        foreach (['calendar', 'projects', 'clients', 'rental-broadcast'] as $slug) {
            $res = $this->actingAs($this->user)->get("/guide/{$slug}");
            $res->assertOk();
            // BinaryFileResponse — 본문 대신 파일 자체를 검증
            $this->assertStringContainsString('닥터고블린 오피스', file_get_contents($res->baseResponse->getFile()->getPathname()));
        }
    }

    public function test_unknown_guide_returns_404(): void
    {
        $this->actingAs($this->user)->get('/guide/secret-file')->assertNotFound();
        $this->actingAs($this->user)->get('/guide/..%2F..%2F.env')->assertNotFound();
    }

    public function test_guest_requires_login(): void
    {
        $this->get('/guide')->assertRedirect();
        $this->get('/guide/calendar')->assertRedirect();
    }

    public function test_header_has_guide_button(): void
    {
        $this->actingAs($this->user)->get('/')
            ->assertOk()
            ->assertSee('guide-link', false)
            ->assertSee('가이드');
    }
}
