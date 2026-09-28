<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\Setting;
use App\Models\User;
use App\Services\PaymentCompleteAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'status' => 'paid', 'client_nickname' => '고블린',
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

        PaymentCompleteAlert::estimatePaid($this->makePaidEstimate(), ['pay_type' => 'card']);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/groups/@'.rawurlencode('결제완료').'/messages')) {
                return false;
            }
            $text = $request['blocks'][0]['value'] ?? '';

            return str_contains($text, '[결제완료] 견적서 #')
                && str_contains($text, '고블린')
                && str_contains($text, '1,234,000원')
                && str_contains($text, '(card)')
                && str_contains($text, '<link type="manager" value="mgr-1">'); // 담당자 개인 알림 멘션
        });
    }

    public function test_alert_skipped_when_group_not_configured(): void
    {
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
        // 톡방 미설정 → 422
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
