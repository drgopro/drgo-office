<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Estimate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 견적서 복제 — 항목·의뢰자·배송지 복사, 번호/링크/결제/거래 이력은 새로 시작 */
class EstimateDuplicateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function makePaidEstimate(): Estimate
    {
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal', 'phone' => '010-1234-5678']);

        return Estimate::create([
            'estimate_no' => 301,
            'status' => 'paid',
            'title' => '스튜디오 세팅 견적',
            'client_id' => $client->id,
            'client_name' => '홍길동',
            'client_nickname' => '고블린',
            'client_phone' => '010-1234-5678',
            'ship_address' => '서울시 강남구',
            'ship_name' => '홍길동',
            'product_items' => [
                ['name' => '카메라 X100', 'sale_price' => 500000, 'qty' => 1, 'subtotal' => 500000, 'category' => '카메라',
                    'ordered' => true, 'ordered_at' => '2026-07-01', 'purchase_source' => '쿠팡', 'purchase_amount' => 450000,
                    'refunded' => true, 'refund_qty' => 1, 'refund_amount' => 500000, 'refunded_at' => '2026-07-10',
                    'bundle_items' => [['name' => '렌즈', 'qty' => 1, 'price' => 100000, 'refund_qty' => 1, 'refund_amount' => 100000, 'ordered' => true, 'ordered_at' => '2026-07-02', 'source' => '사무실 발송', 'memo' => '구성품 메모']]],
                ['name' => '구형 마이크', 'sale_price' => 100000, 'qty' => 1, 'subtotal' => 100000, 'replaced' => true, 'replaced_note' => '신형으로 대체'],
                ['name' => '신형 마이크', 'sale_price' => 120000, 'qty' => 1, 'subtotal' => 120000, 'deal_type' => 'discount', 'original_price' => 150000],
            ],
            'service_items' => [['name' => '세팅비', 'sale_price' => 200000, 'qty' => 1, 'subtotal' => 200000, 'is_service' => true]],
            'product_total' => 620000,
            'service_total' => 200000,
            'total_amount' => 820000,
            'memo' => '의뢰자 메모',
            'internal_memo' => '내부 메모',
            'issued_at' => now(),
            'paid_at' => now(),
            'share_token' => 'abc123',
            'payapp_mul_no' => 'M1',
            'payapp_state' => 'paid',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_duplicate_copies_items_and_client_but_resets_document_state(): void
    {
        $source = $this->makePaidEstimate();

        $res = $this->actingAs($this->admin)->postJson("/api/estimates/{$source->id}/duplicate");
        $res->assertCreated();
        $copy = Estimate::find($res->json('id'));

        $this->assertNotSame($source->id, $copy->id);
        $this->assertSame('created', $copy->status);
        $this->assertNull($copy->estimate_no);
        $this->assertNull($copy->parent_estimate_id);
        $this->assertSame('스튜디오 세팅 견적 (복제)', $copy->title);
        $this->assertSame($source->client_id, $copy->client_id);
        $this->assertSame('고블린', $copy->client_nickname);
        $this->assertSame('서울시 강남구', $copy->ship_address);
        $this->assertSame('의뢰자 메모', $copy->memo);
        $this->assertSame('내부 메모', $copy->internal_memo);
        $this->assertSame(820000, (int) $copy->total_amount);
        $this->assertSame($this->admin->id, $copy->created_by);

        // 문서 상태 초기화
        $this->assertNull($copy->share_token);
        $this->assertNull($copy->payapp_mul_no);
        $this->assertNull($copy->payapp_state);
        $this->assertNull($copy->issued_at);
        $this->assertNull($copy->paid_at);

        // 항목: 대체 항목 제외, 거래 이력 키 제거, 가격/할인 스냅샷 유지
        $names = array_column($copy->product_items, 'name');
        $this->assertSame(['카메라 X100', '신형 마이크'], $names);
        $camera = $copy->product_items[0];
        foreach (['ordered', 'ordered_at', 'purchase_source', 'purchase_amount', 'refunded', 'refund_qty', 'refund_amount', 'refunded_at'] as $key) {
            $this->assertArrayNotHasKey($key, $camera, "{$key} 는 복제본에서 제거돼야 함");
        }
        $this->assertSame(['name' => '렌즈', 'qty' => 1, 'price' => 100000], $camera['bundle_items'][0]);
        $this->assertSame('discount', $copy->product_items[1]['deal_type']);
        $this->assertSame(150000, $copy->product_items[1]['original_price']);
        $this->assertSame('세팅비', $copy->service_items[0]['name']);

        // 원본은 그대로
        $source->refresh();
        $this->assertSame('paid', $source->status);
        $this->assertCount(3, $source->product_items);
        $this->assertTrue($source->product_items[0]['ordered']);

        // 원본 로그에 복제 흔적
        $log = ActivityLog::where('loggable_type', Estimate::class)->where('loggable_id', $source->id)->latest('id')->first();
        $this->assertSame('견적서 #'.$copy->id.' 로 복제', $log->changes['복제']['new']);
    }

    public function test_long_title_is_truncated_so_suffix_fits_column(): void
    {
        $source = $this->makePaidEstimate();
        $source->forceFill(['title' => str_repeat('가', 200)])->save();

        $res = $this->actingAs($this->admin)->postJson("/api/estimates/{$source->id}/duplicate")->assertCreated();
        $copy = Estimate::find($res->json('id'));
        $this->assertSame(200, mb_strlen($copy->title));
        $this->assertStringEndsWith(' (복제)', $copy->title);

        // 복제본을 다시 복제해도 길이 유지
        $res2 = $this->actingAs($this->admin)->postJson("/api/estimates/{$copy->id}/duplicate")->assertCreated();
        $this->assertSame(200, mb_strlen(Estimate::find($res2->json('id'))->title));
    }

    public function test_duplicating_a_round_creates_standalone_estimate(): void
    {
        $parent = $this->makePaidEstimate();
        $round = Estimate::create([
            'parent_estimate_id' => $parent->id, 'round' => 2, 'status' => 'paid', 'title' => '추가 견적',
            'client_id' => $parent->client_id, 'client_nickname' => '고블린',
            'product_items' => [['name' => '조명', 'sale_price' => 50000, 'qty' => 2, 'subtotal' => 100000]],
            'service_items' => [], 'product_total' => 100000, 'service_total' => 0, 'total_amount' => 100000,
            'created_by' => $this->admin->id,
        ]);

        $res = $this->actingAs($this->admin)->postJson("/api/estimates/{$round->id}/duplicate")->assertCreated();
        $copy = Estimate::find($res->json('id'));
        $this->assertNull($copy->parent_estimate_id);
        $this->assertSame(1, (int) $copy->round); // 본 견적서 기본값 — 차수 번호(2)를 물려받지 않음
        $this->assertSame('추가 견적 (복제)', $copy->title);
        $this->assertSame('조명', $copy->product_items[0]['name']);
    }

    public function test_estimate_list_and_builder_expose_duplicate_action(): void
    {
        $source = $this->makePaidEstimate();
        $this->actingAs($this->admin)->get('/estimates')->assertOk()->assertSee('duplicateEstimate(', false);
        $this->actingAs($this->admin)->get("/estimates/{$source->id}/edit")->assertOk()->assertSee('onclick="duplicateEstimate()"', false);
    }
}
