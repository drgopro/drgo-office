<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** 내부 오피스 보안 — 검색엔진 비색인 + 보안 헤더 + 로그인 브루트포스 제한 */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_robots_txt_disallows_all_crawling(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));
        $this->assertStringContainsString('Disallow: /', $robots);
    }

    public function test_security_headers_on_all_web_responses(): void
    {
        // 로그인 페이지 (비인증)
        $res = $this->get('/login');
        $res->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet');
        $res->assertHeader('X-Content-Type-Options', 'nosniff');
        $res->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $res->assertHeader('Referrer-Policy', 'no-referrer');

        // 의뢰자 공개 견적서 — 토큰 URL도 비색인 + Referer 유출 방지
        $admin = User::factory()->create(['role' => 'master']);
        $estimate = Estimate::create([
            'status' => 'created', 'product_items' => [], 'service_items' => [],
            'product_total' => 0, 'service_total' => 0, 'total_amount' => 0,
            'validity_days' => 3, 'created_by' => $admin->id,
        ]);
        $public = $this->get($estimate->publicUrl());
        $public->assertOk();
        $public->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet');
        $public->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_login_is_rate_limited_after_five_failures(): void
    {
        User::factory()->create(['username' => 'staff1', 'password' => Hash::make('correct-pw'), 'is_active' => true]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['username' => 'staff1', 'password' => 'wrong'])
                ->assertSessionHasErrors('username');
        }

        // 6번째 — 올바른 비밀번호여도 잠금
        $res = $this->post('/login', ['username' => 'staff1', 'password' => 'correct-pw']);
        $res->assertSessionHasErrors('username');
        $this->assertStringContainsString('너무 많습니다', session('errors')->first('username'));
        $this->assertGuest();
    }

    public function test_successful_login_clears_rate_limit(): void
    {
        User::factory()->create(['username' => 'staff2', 'password' => Hash::make('correct-pw'), 'is_active' => true]);

        // 4회 실패 후 성공 → 제한 해제
        for ($i = 0; $i < 4; $i++) {
            $this->post('/login', ['username' => 'staff2', 'password' => 'wrong']);
        }
        $this->post('/login', ['username' => 'staff2', 'password' => 'correct-pw'])->assertRedirect();
        $this->assertAuthenticated();
    }
}
