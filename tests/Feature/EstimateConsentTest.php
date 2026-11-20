<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Estimate;
use App\Models\Project;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 의뢰자 동의 — 위약 안내(필수)·마케팅 활용(선택) 전문 열람과 기록.
 * 위약 동의 전 결제 버튼 잠금, 마케팅 동의는 의뢰자에도 복사(사례 업로드 대상 필터용).
 */
class EstimateConsentTest extends TestCase
{
    use RefreshDatabase;

    private Estimate $estimate;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create(['role' => 'master']);
        $this->client = Client::create(['nickname' => '이콘타', 'phone' => '010-7777-1212', 'grade' => 'normal']);
        $this->estimate = Estimate::create([
            'status' => 'issued', 'client_id' => $this->client->id, 'client_nickname' => '이콘타',
            'product_items' => [['name' => '할인 기능 테스트', 'qty' => 1, 'sale_price' => 200000, 'subtotal' => 200000]],
            'service_items' => [], 'product_total' => 200000, 'service_total' => 0, 'total_amount' => 200000,
            'validity_days' => 3, 'created_by' => $admin->id,
            'share_token' => Str::random(48), 'payapp_payurl' => 'https://payapp.example/pay/1', 'payapp_state' => 1,
        ]);
    }

    public function test_public_terms_pages_render_with_defaults(): void
    {
        $this->get('/terms/penalty')->assertOk()->assertSee('위약 안내')->assertSee('일정 변경');
        $this->get('/terms/marketing')->assertOk()->assertSee('선택사항');
        $this->get('/terms/nope')->assertNotFound();
    }

    public function test_estimate_terms_page_shows_agree_button(): void
    {
        $this->get("/estimate-view/{$this->estimate->share_token}/terms/penalty")
            ->assertOk()->assertSee('동의합니다');
    }

    public function test_public_estimate_locks_payment_until_penalty_agreed(): void
    {
        $res = $this->get("/estimate-view/{$this->estimate->share_token}")->assertOk();
        $res->assertSee('위약 안내');
        $res->assertSee('pay-btn locked', false);

        $this->postJson("/estimate-view/{$this->estimate->share_token}/agree", ['type' => 'penalty'])->assertOk();

        // 위약 동의만으로는 아직 잠김 — 마케팅 동의 여부 선택이 남았다
        $res = $this->get("/estimate-view/{$this->estimate->share_token}")->assertOk();
        $res->assertSee('pay-btn locked', false);
        $res->assertSee('동의 완료');
        $res->assertSee('동의 여부 선택');

        $this->postJson("/estimate-view/{$this->estimate->share_token}/agree", ['type' => 'marketing', 'agree' => false])->assertOk();

        $res = $this->get("/estimate-view/{$this->estimate->share_token}")->assertOk();
        $res->assertDontSee('pay-btn locked', false);
        $res->assertSee('동의하지 않음');

        $state = $this->estimate->fresh()->agreements;
        $this->assertNotEmpty($state['penalty']['at']);
        $this->assertNotEmpty($state['penalty']['ver']);
    }

    public function test_marketing_agree_copies_to_client(): void
    {
        $this->postJson("/estimate-view/{$this->estimate->share_token}/agree", ['type' => 'marketing'])->assertOk();

        $client = $this->client->fresh();
        $this->assertNotNull($client->marketing_agreed_at);
        $this->assertSame($this->estimate->id, (int) $client->marketing_agree_estimate_id);
        $this->assertNotEmpty($this->estimate->fresh()->agreements['marketing']['at']);
    }

    public function test_marketing_agree_keeps_earlier_client_record(): void
    {
        $this->client->forceFill(['marketing_agreed_at' => now()->subDay(), 'marketing_agree_estimate_id' => 999])->save();
        $before = $this->client->fresh()->marketing_agreed_at;

        $this->postJson("/estimate-view/{$this->estimate->share_token}/agree", ['type' => 'marketing'])->assertOk();
        $this->assertSame((string) $before, (string) $this->client->fresh()->marketing_agreed_at);
    }

    public function test_duplicate_agree_keeps_first_timestamp(): void
    {
        $this->postJson("/estimate-view/{$this->estimate->share_token}/agree", ['type' => 'penalty'])->assertOk();
        $first = $this->estimate->fresh()->agreements['penalty']['at'];

        $this->travel(5)->minutes();
        $this->postJson("/estimate-view/{$this->estimate->share_token}/agree", ['type' => 'penalty'])->assertOk();
        $this->assertSame($first, $this->estimate->fresh()->agreements['penalty']['at']);
    }

    public function test_bank_transfer_option_shown_only_with_account_setting(): void
    {
        $this->get("/estimate-view/{$this->estimate->share_token}")->assertOk()->assertDontSee('계좌이체로 결제');

        Setting::set('payment_bank_account', '국민은행 000000-00-000000 (주)닥터고블린컴퍼니');
        $res = $this->get("/estimate-view/{$this->estimate->share_token}")->assertOk();
        $res->assertSee('계좌이체로 결제');
        $res->assertSee('국민은행 000000-00-000000');
        $res->assertSee('이콘타'); // 입금자명 안내
    }

    public function test_consent_cards_can_be_hidden_by_settings(): void
    {
        // 위약 카드 숨김 → 결제 잠금도 해제 (보이지 않는 동의를 요구하지 않음)
        Setting::set('terms_penalty_enabled', '0');
        $res = $this->get("/estimate-view/{$this->estimate->share_token}")->assertOk();
        $res->assertDontSee('위약 안내 <span', false);
        $res->assertSee('pay-btn locked', false); // 마케팅 동의 여부는 아직 선택 전
        $res->assertSee('포트폴리오 사용 동의'); // 마케팅 카드는 그대로

        // 마케팅도 숨김 → 동의 영역 전체 미출력
        Setting::set('terms_marketing_enabled', '0');
        $res = $this->get("/estimate-view/{$this->estimate->share_token}")->assertOk();
        $res->assertDontSee('class="consent-row', false);
        $res->assertDontSee('pay-btn locked', false); // 보이지 않는 선택은 요구하지 않음
    }

    public function test_marketing_decline_is_recorded_and_withdraws_client_consent_from_this_estimate(): void
    {
        $token = $this->estimate->share_token;
        $this->postJson("/estimate-view/{$token}/agree", ['type' => 'marketing'])->assertOk();
        $this->assertNotNull($this->client->fresh()->marketing_agreed_at);

        $this->postJson("/estimate-view/{$token}/agree", ['type' => 'marketing', 'agree' => false])
            ->assertOk()->assertJson(['agreed' => false]);

        $record = $this->estimate->fresh()->agreements['marketing'];
        $this->assertFalse($record['agreed']);
        $this->assertNotEmpty($record['at']);
        $this->assertNull($this->client->fresh()->marketing_agreed_at);
    }

    public function test_marketing_decline_keeps_consent_given_on_other_estimate(): void
    {
        $this->client->forceFill(['marketing_agreed_at' => now()->subDay(), 'marketing_agree_estimate_id' => 999])->save();

        $this->postJson("/estimate-view/{$this->estimate->share_token}/agree", ['type' => 'marketing', 'agree' => false])->assertOk();

        $this->assertNotNull($this->client->fresh()->marketing_agreed_at);
    }

    public function test_marketing_choice_updates_linked_project(): void
    {
        $project = Project::create(['name' => '동의 연동 프로젝트', 'client_id' => $this->client->id]);
        $this->estimate->forceFill(['project_id' => $project->id])->save();

        $this->postJson("/estimate-view/{$this->estimate->share_token}/agree", ['type' => 'marketing', 'agree' => false])->assertOk();
        $this->assertFalse($project->fresh()->marketing_consent);

        $this->postJson("/estimate-view/{$this->estimate->share_token}/agree", ['type' => 'marketing', 'agree' => true])->assertOk();
        $this->assertTrue($project->fresh()->marketing_consent);
        $this->assertNotNull($project->fresh()->marketing_consent_updated_at);
    }

    public function test_marketing_terms_page_offers_agree_and_decline(): void
    {
        $this->get("/estimate-view/{$this->estimate->share_token}/terms/marketing")->assertOk()
            ->assertSee('동의하지 않습니다')
            ->assertSee('포트폴리오 사용에 동의합니다')
            ->assertSee('동의 여부를 선택해 주세요')
            ->assertSee('포트폴리오 사용 동의');
    }

    public function test_legacy_marketing_record_without_choice_flag_counts_as_agreed(): void
    {
        $this->estimate->forceFill(['agreements' => ['marketing' => ['at' => '2026-11-10 10:00:00', 'ver' => 'v1']]])->save();

        $this->get("/estimate-view/{$this->estimate->share_token}")->assertOk()->assertSee('동의함 · 2026-11-10 10:00:00');
    }

    public function test_penalty_terms_assemble_sections_per_estimate(): void
    {
        // 섹션 설정: 공통 + 렌탈 + 방문 — 견적서에는 렌탈만 선택
        Setting::set('terms_penalty_sections', json_encode([
            ['key' => 'common', 'name' => '공통', 'version' => 'c1', 'active' => true, 'auto_types' => [], 'html' => '<p>공통 조항</p>'],
            ['key' => 'rental', 'name' => '렌탈', 'version' => 'r2', 'active' => true, 'auto_types' => ['rental'], 'html' => '<p>렌탈 위약 조항</p>'],
            ['key' => 'visit_setup', 'name' => '방문 세팅', 'version' => 'v1', 'active' => true, 'auto_types' => ['visit'], 'html' => '<p>방문 위약 조항</p>'],
        ], JSON_UNESCAPED_UNICODE));
        $this->estimate->forceFill(['terms_sections' => ['rental']])->save();

        // 공개 전문(/terms/penalty)은 활성 섹션 전체
        $this->get('/terms/penalty')->assertOk()
            ->assertSee('공통 조항')->assertSee('렌탈 위약 조항')->assertSee('방문 위약 조항');

        // 견적서 전문은 공통 + 선택 섹션만
        $this->get("/estimate-view/{$this->estimate->share_token}/terms/penalty")->assertOk()
            ->assertSee('공통 조항')->assertSee('렌탈 위약 조항')->assertDontSee('방문 위약 조항');

        // 동의 기록에 섹션별 버전이 남는다
        $this->postJson("/estimate-view/{$this->estimate->share_token}/agree", ['type' => 'penalty'])->assertOk();
        $rec = $this->estimate->fresh()->agreements['penalty'];
        $this->assertSame(['common' => 'c1', 'rental' => 'r2'], $rec['sections']);
        $this->assertStringContainsString('렌탈 r2', $rec['ver']);
    }
}
