<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\Setting;
use App\Models\User;
use App\Services\PaymentCompleteAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** 페이앱 결제완료 → 채널톡 결제완료 톡방 알림 + 결제완료 담당자 멘션 */
class PaymentCompleteAlertTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'display_name' => '이수호', 'email' => 'suho@drgo.pro']);
        config([
            'services.channeltalk.access_key' => 'k',
            'services.channeltalk.access_secret' => 's',
            'services.channeltalk.group' => '팀챗기본',
            'services.channeltalk.bot_name' => '오피스봇',
        ]);
    }

    private function makePaidEstimate(): Estimate
    {
        return Estimate::create([
            'status' => 'paid', 'client_nickname' => '고블린', 'estimate_no' => 200,
            'product_items' => [], 'service_items' => [],
            'total_amount' => 1234000, 'created_by' => $this->admin->id,
        ]);
    }

    public function test_alert_sends_to_configured_group_with_manager_mention(): void
    {
        Setting::set('payment_alert_group', '결제완료');
        Setting::set('payment_alert_managers', json_encode([$this->admin->id]));

        Http::fake([
            'api.channel.io/open/v5/managers*' => Http::response(['managers' => [
                ['id' => 'mgr-1', 'name' => '이수호', 'email' => 'suho@drgo.pro'],
            ]]),
            'api.channel.io/open/v5/groups/*' => Http::response(['ok' => true]),
        ]);

        // pay_type '1'은 신용카드로 표기, '23' 같은 미확인 숫자 코드는 표기 생략
        PaymentCompleteAlert::estimatePaid($this->makePaidEstimate(), ['pay_type' => '1']);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/groups/@'.rawurlencode('결제완료').'/messages')) {
                return false;
            }
            $text = $request['blocks'][0]['value'] ?? '';

            return str_contains($text, '[결제완료] 견적서 #200') // 화면 표시 번호(estimate_no) — DB id 아님
                && str_contains($text, '고블린')
                && str_contains($text, '1,234,000원')
                && str_contains($text, '(신용카드)')
                && str_contains($text, '<link type="manager" value="mgr-1">'); // 담당자 개인 알림 멘션
        });

        Cache::flush(); // 중복 잠금 해제 — 미확인 코드 케이스 재발송
        PaymentCompleteAlert::estimatePaid(Estimate::first(), ['pay_type' => '23']);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/messages')
            && ! str_contains($r['blocks'][0]['value'] ?? '', '(23)'));
    }

    public function test_default_group_seeded_as_estimate_payment_room(): void
    {
        // 시드+교정 마이그레이션 — 채널톡에 실제로 만들어진 '견적서결제알림' 그룹이 기본 톡방
        $this->assertSame('견적서결제알림', Setting::get('payment_alert_group'));
    }

    public function test_alert_skipped_when_group_not_configured(): void
    {
        Setting::set('payment_alert_group', ''); // 기본 시드값(견적서결제)을 비워 기능 끔
        Http::fake();
        PaymentCompleteAlert::estimatePaid($this->makePaidEstimate());
        Http::assertNothingSent();
    }

    public function test_alert_failure_does_not_throw(): void
    {
        Setting::set('payment_alert_group', '결제완료');
        Http::fake(fn () => throw new \Exception('통신 불가'));

        PaymentCompleteAlert::estimatePaid($this->makePaidEstimate()); // 예외 없이 통과해야 함
        $this->assertTrue(true);
    }

    public function test_test_endpoint_sends_and_validates(): void
    {
        // 톡방 미설정(기본 시드값 비움) → 422
        Setting::set('payment_alert_group', '');
        $this->actingAs($this->admin)->postJson('/api/admin/payment-alert-test')->assertStatus(422);

        Setting::set('payment_alert_group', '결제완료');
        Setting::set('payment_alert_managers', json_encode([$this->admin->id]));
        Http::fake([
            'api.channel.io/open/v5/managers*' => Http::response(['managers' => []]),
            'api.channel.io/open/v5/groups/*' => Http::response(['ok' => true]),
        ]);

        $this->actingAs($this->admin)->postJson('/api/admin/payment-alert-test')->assertOk();
        Http::assertSent(fn ($r) => str_contains($r->url(), '/groups/@'.rawurlencode('결제완료').'/messages')
            && str_contains($r['blocks'][0]['value'] ?? '', '[테스트]'));

        // member는 관리 라우트 접근 불가
        $member = User::factory()->create(['role' => 'member']);
        $this->actingAs($member)->postJson('/api/admin/payment-alert-test')->assertForbidden();
    }

    public function test_test_endpoint_failure_lists_visible_groups(): void
    {
        // 422 등 발송 실패 시 — 채널톡에서 보이는 그룹 목록을 안내 (비공개/오타 진단)
        Setting::set('payment_alert_group', '견적서결제');
        Http::fake([
            'api.channel.io/open/v5/managers*' => Http::response(['managers' => []]),
            'api.channel.io/open/v5/groups/@*' => Http::response(['error' => 'unknown group'], 422),
            'api.channel.io/open/v5/groups*' => Http::response(['groups' => [
                ['id' => 'g1', 'name' => '팀챗기본'], ['id' => 'g2', 'name' => '배송알림'],
            ]]),
        ]);

        $res = $this->actingAs($this->admin)->postJson('/api/admin/payment-alert-test')->assertStatus(502);
        $message = $res->json('message');
        $this->assertStringContainsString('보이는 그룹: 팀챗기본, 배송알림', $message);
        $this->assertStringContainsString('비공개 그룹', $message);
    }

    public function test_duplicate_alert_suppressed_within_window(): void
    {
        // 페이앱이 같은 결제완료를 재통보해도 알림은 1회만 (1시간 중복 잠금)
        Setting::set('payment_alert_group', '견적서결제알림');
        Http::fake([
            'api.channel.io/open/v5/managers*' => Http::response(['managers' => []]),
            'api.channel.io/open/v5/groups/*' => Http::response(['ok' => true]),
        ]);

        $estimate = $this->makePaidEstimate();
        PaymentCompleteAlert::estimatePaid($estimate);
        PaymentCompleteAlert::estimatePaid($estimate); // 중복 호출

        Http::assertSentCount(1);
    }

    public function test_settings_save_and_admin_page_renders_tab(): void
    {
        $this->actingAs($this->admin)->postJson('/api/settings', [
            'payment_alert_group' => '결제완료',
            'payment_alert_managers' => json_encode([1, 2]),
        ])->assertOk();
        $this->assertSame('결제완료', Setting::get('payment_alert_group'));
        $this->assertSame([1, 2], json_decode(Setting::get('payment_alert_managers'), true));

        $this->actingAs($this->admin)->get('/admin')->assertOk()
            ->assertSee('결제 알림')
            ->assertSee('id="paGroup"', false)
            ->assertSee('loadPaymentAlertSettings', false)
            ->assertSee('payment-alert-test', false);
    }
}
