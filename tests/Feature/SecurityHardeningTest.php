<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** 보안 점검 조치 — 임포트 권한, 할 일 IDOR, 첨부 위험 확장자, XSS 이스케이프, 비색인/보안 헤더, 로그인 잠금 */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    // ── 엑셀 임포트 — 관리자 전용 ──

    public function test_member_cannot_access_excel_import(): void
    {
        $member = User::factory()->create(['role' => 'member']);

        $this->actingAs($member)->get('/api/import/template/clients')->assertForbidden();
        $this->actingAs($member)->postJson('/api/import/clients')->assertForbidden();
    }

    public function test_admin_can_access_excel_import_template(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/api/import/template/clients')->assertOk();
    }

    // ── 할 일 IDOR — 타인 할 일 조작 차단 ──

    public function test_member_cannot_modify_others_todo(): void
    {
        $attacker = User::factory()->create(['role' => 'member']);
        $victim = User::factory()->create(['role' => 'member']);
        $todo = Todo::factory()->create(['assignee_id' => $victim->id, 'created_by' => $victim->id]);

        $this->actingAs($attacker)->patchJson("/api/todos/{$todo->id}", [
            'title' => '탈취', 'priority' => $todo->priority, 'assignee_id' => $attacker->id,
        ])->assertForbidden();
        $this->actingAs($attacker)->patchJson("/api/todos/{$todo->id}/complete")->assertForbidden();
        $this->actingAs($attacker)->deleteJson("/api/todos/{$todo->id}")->assertForbidden();
        $this->actingAs($attacker)->postJson("/api/todos/{$todo->id}/attachments", [
            'files' => [UploadedFile::fake()->image('a.png')],
        ])->assertForbidden();

        $this->assertDatabaseHas('todos', ['id' => $todo->id, 'assignee_id' => $victim->id]);
    }

    public function test_assignee_and_admin_can_still_manage_todo(): void
    {
        $owner = User::factory()->create(['role' => 'member']);
        $admin = User::factory()->create(['role' => 'admin']);
        $todo = Todo::factory()->create(['assignee_id' => $owner->id, 'created_by' => $owner->id]);

        $this->actingAs($owner)->patchJson("/api/todos/{$todo->id}/complete")->assertOk();
        $this->actingAs($admin)->patchJson("/api/todos/{$todo->id}/complete")->assertOk();
        $this->actingAs($admin)->deleteJson("/api/todos/{$todo->id}")->assertOk();
    }

    // ── 첨부 위험 확장자 차단 ──

    public function test_dangerous_attachment_extensions_are_rejected(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $todo = Todo::factory()->create(['assignee_id' => $admin->id, 'created_by' => $admin->id]);

        foreach (['evil.html', 'evil.svg', 'evil.php', 'evil.exe'] as $name) {
            $this->actingAs($admin)->postJson("/api/todos/{$todo->id}/attachments", [
                'files' => [UploadedFile::fake()->create($name, 10)],
            ])->assertUnprocessable();
        }

        $this->assertSame(0, $todo->attachments()->count());
    }

    public function test_normal_attachments_are_still_accepted(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $todo = Todo::factory()->create(['assignee_id' => $admin->id, 'created_by' => $admin->id]);

        $this->actingAs($admin)->postJson("/api/todos/{$todo->id}/attachments", [
            'files' => [
                UploadedFile::fake()->image('photo.png'),
                UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
                UploadedFile::fake()->create('archive.zip', 100, 'application/zip'),
            ],
        ])->assertCreated();

        $this->assertSame(3, $todo->attachments()->count());
    }

    // ── XSS 이스케이프 헬퍼 적용 ──

    public function test_client_and_inventory_pages_include_escape_helper(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/clients')
            ->assertOk()
            ->assertSee('function _esc', false)
            ->assertSee('${_esc(displayName)}', false);

        $this->actingAs($admin)->get('/inventory')
            ->assertOk()
            ->assertSee('function _esc', false)
            ->assertSee('${_esc(p.name)}', false);
    }

    // ── 검색엔진 비색인 + 보안 헤더 ──

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

    // ── 로그인 브루트포스 제한 ──

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
