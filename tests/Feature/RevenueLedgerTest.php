<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Estimate;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\ProjectPayment;
use App\Models\RevenueEntry;
use App\Models\User;
use App\Services\RevenueLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 매출 인식 원장(revenue_entries) — 인식일 규칙, 세팅비/장비 분리, 환불 차감 */
class RevenueLedgerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $this->project = Project::create(['client_id' => $client->id, 'name' => '세팅', 'project_type' => 'visit', 'stage' => 'payment']);
    }

    private function pay(array $attrs): ProjectPayment
    {
        return ProjectPayment::create(array_merge(['project_id' => $this->project->id], $attrs));
    }

    private function makePaidEstimate(array $overrides = []): Estimate
    {
        return Estimate::create(array_merge([
            'status' => 'paid',
            'product_items' => [
                ['name' => '카메라', 'sale_price' => 300000, 'qty' => 1, 'subtotal' => 300000, 'is_service' => false],
                ['name' => '세팅비', 'sale_price' => 100000, 'qty' => 1, 'subtotal' => 100000, 'is_service' => true],
            ],
            'service_items' => [],
            'product_total' => 400000,
            'total_amount' => 400000,
            'created_by' => $this->user->id,
        ], $overrides));
    }

    public function test_paid_estimate_recognized_on_payment_date_with_split(): void
    {
        $estimate = $this->makePaidEstimate();
        $this->pay(['type' => 'charge', 'estimate_id' => $estimate->id, 'amount' => 400000, 'paid_at' => '2026-08-10']);

        $entry = RevenueEntry::where('estimate_id', $estimate->id)->where('kind', 'estimate_paid')->first();
        $this->assertNotNull($entry);
        $this->assertSame('2026-08-10', $entry->recognized_on->toDateString());
        $this->assertSame(300000, $entry->product_amount);
        $this->assertSame(100000, $entry->service_amount);
        $this->assertSame(400000, $entry->amount);
        // 결제 이력이 있는 견적서는 원장 행이 정확히 1건 (재저장에도 멱등)
        $estimate->touch();
        $this->assertSame(1, RevenueEntry::where('estimate_id', $estimate->id)->count());
    }

    public function test_project_completion_moves_recognition_date_and_back(): void
    {
        $project = $this->project;
        $estimate = $this->makePaidEstimate(['project_id' => $project->id, 'client_id' => $project->client_id]);
        $this->pay(['type' => 'charge', 'estimate_id' => $estimate->id, 'amount' => 400000, 'paid_at' => '2026-08-10']);

        // 완료 전환 — 인식일이 프로젝트 완료일로 이동
        $project->update(['stage' => 'done', 'completed_at' => '2026-08-20 14:00:00']);
        $entry = RevenueEntry::where('estimate_id', $estimate->id)->where('kind', 'estimate_paid')->first();
        $this->assertSame('2026-08-20', $entry->recognized_on->toDateString());

        // 완료 해제 — 결제일로 복귀
        $project->update(['stage' => 'payment', 'completed_at' => null]);
        $entry = RevenueEntry::where('estimate_id', $estimate->id)->where('kind', 'estimate_paid')->first();
        $this->assertSame('2026-08-10', $entry->recognized_on->toDateString());
    }

    public function test_item_linked_refund_negative_entry_on_refund_date(): void
    {
        $estimate = $this->makePaidEstimate();
        $this->pay(['type' => 'charge', 'estimate_id' => $estimate->id, 'amount' => 400000, 'paid_at' => '2026-08-10']);
        $this->pay([
            'type' => 'refund', 'estimate_id' => $estimate->id, 'amount' => -300000,
            'refunded_at' => '2026-08-15 11:00:00',
            'items' => [['estimate_item_index' => 0, 'qty' => 1, 'price' => 300000]],
        ]);

        $refund = RevenueEntry::where('estimate_id', $estimate->id)->where('kind', 'estimate_refund')->first();
        $this->assertNotNull($refund);
        $this->assertSame('2026-08-15', $refund->recognized_on->toDateString());
        $this->assertSame(-300000, $refund->product_amount); // 0번 항목은 장비
        $this->assertSame(0, $refund->service_amount);
        $this->assertSame(-300000, $refund->amount);
        // 원장 합계 = 매출 100,000 (결제 400,000 − 환불 300,000)
        $this->assertSame(100000, (int) RevenueEntry::where('estimate_id', $estimate->id)->sum('amount'));
    }

    public function test_unlinked_amount_refund_prorated_by_totals(): void
    {
        $estimate = $this->makePaidEstimate();
        $this->pay(['type' => 'charge', 'estimate_id' => $estimate->id, 'amount' => 400000, 'paid_at' => '2026-08-10']);
        // 항목 미지정 금액 환불 100,000 — 세팅비:장비 = 100,000:300,000 비율 배분
        $this->pay(['type' => 'refund', 'estimate_id' => $estimate->id, 'amount' => -100000, 'refunded_at' => '2026-08-16 09:00:00']);

        $refund = RevenueEntry::where('estimate_id', $estimate->id)->where('kind', 'estimate_refund')->first();
        $this->assertSame(-25000, $refund->service_amount);
        $this->assertSame(-75000, $refund->product_amount);
        $this->assertSame(-100000, $refund->amount);
    }

    public function test_unlinked_estimate_snapshot_refund_deducted(): void
    {
        // 프로젝트/결제 미연동 — 스냅샷 환불 기록만으로 차감
        $estimate = Estimate::create([
            'status' => 'paid',
            'product_items' => [
                ['name' => '카메라', 'sale_price' => 300000, 'qty' => 1, 'subtotal' => 300000, 'is_service' => false,
                    'refunded' => true, 'refund_amount' => 100000, 'refunded_at' => '2026-08-12 10:00:00'],
            ],
            'service_items' => [], 'product_total' => 300000, 'total_amount' => 300000,
            'created_by' => $this->user->id,
        ]);

        $entries = RevenueEntry::where('estimate_id', $estimate->id)->get();
        $this->assertSame(300000, $entries->firstWhere('kind', 'estimate_paid')->amount);
        $refund = $entries->firstWhere('kind', 'estimate_refund');
        $this->assertSame(-100000, $refund->amount);
        $this->assertSame('2026-08-12', $refund->recognized_on->toDateString());
        $this->assertSame(200000, (int) $entries->sum('amount'));
    }

    public function test_payment_only_rows_tracked_one_to_one(): void
    {
        $charge = $this->pay(['type' => 'charge', 'amount' => 150000, 'paid_at' => '2026-08-05']);

        $entry = RevenueEntry::where('kind', 'payment_only')->where('payment_id', $charge->id)->first();
        $this->assertNotNull($entry);
        $this->assertSame(150000, $entry->amount);
        $this->assertSame('2026-08-05', $entry->recognized_on->toDateString());

        $charge->update(['amount' => 120000]);
        $this->assertSame(120000, RevenueEntry::where('payment_id', $charge->id)->first()->amount);

        $charge->delete();
        $this->assertSame(0, RevenueEntry::where('payment_id', $charge->id)->count());
    }

    public function test_classification_falls_back_to_current_product_category(): void
    {
        // 분류 도입 전 스냅샷(is_service 키 없음) — 현재 제품/카테고리 분류로 따라온다
        $root = ProductCategory::create(['name' => '서비스', 'code' => 'SVC', 'depth' => 1, 'sort_order' => 1, 'is_service' => true]);
        $child = ProductCategory::create(['parent_id' => $root->id, 'name' => '세팅', 'code' => 'SET', 'depth' => 2, 'sort_order' => 1]);
        $product = Product::create([
            'sku' => 'SVC-00001', 'name' => '방문 세팅비', 'category' => '세팅', 'category_id' => $child->id,
            'purchase_price' => 0, 'sale_price' => 100000, 'safety_stock' => 0, 'is_active' => true,
        ]);
        $estimate = Estimate::create([
            'status' => 'paid',
            'product_items' => [['product_id' => $product->id, 'name' => '방문 세팅비', 'sale_price' => 100000, 'qty' => 1, 'subtotal' => 100000]],
            'service_items' => [], 'product_total' => 100000, 'total_amount' => 100000,
            'created_by' => $this->user->id,
        ]);
        $this->pay(['type' => 'charge', 'estimate_id' => $estimate->id, 'amount' => 100000, 'paid_at' => '2026-08-10']);

        // 조상 카테고리 is_service 상속 → 세팅비 매출
        $entry = RevenueEntry::where('estimate_id', $estimate->id)->where('kind', 'estimate_paid')->first();
        $this->assertSame(100000, $entry->service_amount);
        $this->assertSame(0, $entry->product_amount);

        // 제품별 재정의(service_kind=product)가 카테고리보다 우선 — 재계산 시 장비 매출로 이동
        $product->update(['service_kind' => 'product']);
        RevenueLedger::rebuild();
        $entry = RevenueEntry::where('estimate_id', $estimate->id)->where('kind', 'estimate_paid')->first();
        $this->assertSame(0, $entry->service_amount);
        $this->assertSame(100000, $entry->product_amount);
    }

    public function test_snapshot_is_service_wins_over_current_product(): void
    {
        // 담을 때 박제된 스냅샷이 있으면 이후 제품 분류를 바꿔도 과거 견적서는 불변
        $product = Product::create([
            'sku' => 'EQ-00001', 'name' => '카메라', 'category' => '장비',
            'purchase_price' => 200000, 'sale_price' => 300000, 'safety_stock' => 0, 'is_active' => true,
        ]);
        $estimate = Estimate::create([
            'status' => 'paid',
            'product_items' => [['product_id' => $product->id, 'name' => '카메라', 'sale_price' => 300000, 'qty' => 1, 'subtotal' => 300000, 'is_service' => false]],
            'service_items' => [], 'product_total' => 300000, 'total_amount' => 300000,
            'created_by' => $this->user->id,
        ]);
        $this->pay(['type' => 'charge', 'estimate_id' => $estimate->id, 'amount' => 300000, 'paid_at' => '2026-08-10']);

        $product->update(['service_kind' => 'service']);
        RevenueLedger::rebuild();

        $entry = RevenueEntry::where('estimate_id', $estimate->id)->where('kind', 'estimate_paid')->first();
        $this->assertSame(300000, $entry->product_amount);
        $this->assertSame(0, $entry->service_amount);
    }

    public function test_rebuild_command_is_idempotent(): void
    {
        $estimate = $this->makePaidEstimate();
        $this->pay(['type' => 'charge', 'estimate_id' => $estimate->id, 'amount' => 400000, 'paid_at' => '2026-08-10']);
        $this->pay(['type' => 'charge', 'amount' => 150000, 'paid_at' => '2026-08-05']); // 단순 결제

        $this->artisan('revenue:rebuild')->assertSuccessful();
        $firstTotal = (int) RevenueEntry::sum('amount');
        $firstCount = RevenueEntry::count();

        $this->artisan('revenue:rebuild')->assertSuccessful();
        $this->assertSame($firstTotal, (int) RevenueEntry::sum('amount'));
        $this->assertSame($firstCount, RevenueEntry::count());
        $this->assertSame(550000, $firstTotal);
    }

    public function test_rebuild_falls_back_to_estimate_created_at_for_manual_paid(): void
    {
        // 결제 일시 단서가 전혀 없는 수동 결제완료 견적(미연동, 페이앱 아님) —
        // rebuild 시 구축일이 아니라 견적서 생성일(기존 통계 기준)로 인식돼야 한다
        $estimate = $this->makePaidEstimate();
        $estimate->forceFill(['created_at' => '2026-06-15 10:00:00'])->save();

        $this->artisan('revenue:rebuild')->assertSuccessful();

        $entry = RevenueEntry::where('estimate_id', $estimate->id)->where('kind', 'estimate_paid')->first();
        $this->assertSame('2026-06-15', $entry->recognized_on->toDateString());
    }

    public function test_live_manual_paid_recognized_today_and_kept_on_later_edits(): void
    {
        // 실시간 결제완료 전환은 '오늘'이 결제 확인일 — 이후 편집 재저장에도 그 날짜가 유지된다
        $estimate = $this->makePaidEstimate(['status' => 'issued']);
        $estimate->forceFill(['created_at' => '2026-06-15 10:00:00'])->save();
        $this->assertSame(0, RevenueEntry::where('estimate_id', $estimate->id)->count());

        $estimate->update(['status' => 'paid']);
        $entry = RevenueEntry::where('estimate_id', $estimate->id)->where('kind', 'estimate_paid')->first();
        $this->assertSame(now()->toDateString(), $entry->recognized_on->toDateString());

        $estimate->update(['memo' => '편집']);
        $entry = RevenueEntry::where('estimate_id', $estimate->id)->where('kind', 'estimate_paid')->first();
        $this->assertSame(now()->toDateString(), $entry->recognized_on->toDateString());
    }

    public function test_adopts_matching_unlinked_manual_charge(): void
    {
        // 프로젝트에 수동 결제 기록(견적 미연동) 후 견적서를 결제완료 표시 — 같은 돈이니 한 번만
        $charge = $this->pay(['type' => 'charge', 'amount' => 400000, 'paid_at' => '2026-08-25', 'method' => '계좌이체']);
        $estimate = $this->makePaidEstimate(['project_id' => $this->project->id]);

        $entries = RevenueEntry::all();
        $this->assertSame(1, $entries->count());
        $entry = $entries->first();
        $this->assertSame('estimate_paid', $entry->kind);
        $this->assertSame($charge->id, $entry->payment_id); // 입양 표시
        $this->assertSame('2026-08-25', $entry->recognized_on->toDateString()); // 수동 결제일로 인식
        $this->assertSame(400000, $entry->amount);
        $this->assertSame(0, RevenueEntry::where('kind', 'payment_only')->count());

        // 반대 순서(견적 먼저, 수동 결제 나중)도 한 번만
        RevenueEntry::query()->delete();
        $charge->delete();
        $estimate->touch(); // 견적 재계산 — 입양할 charge 없음 → 견적 단독 집계
        $again = $this->pay(['type' => 'charge', 'amount' => 400000, 'paid_at' => '2026-08-25', 'method' => '계좌이체']);
        $this->assertSame(1, RevenueEntry::count());
        $this->assertSame($again->id, RevenueEntry::first()->payment_id);
        $this->assertSame(400000, (int) RevenueEntry::sum('amount'));
    }

    public function test_no_adoption_when_amount_differs(): void
    {
        // 금액이 다르면 서로 다른 돈 — 양쪽 다 집계
        $this->pay(['type' => 'charge', 'amount' => 150000, 'paid_at' => '2026-08-25']);
        $this->makePaidEstimate(['project_id' => $this->project->id]);

        $this->assertSame(1, RevenueEntry::where('kind', 'payment_only')->count());
        $this->assertSame(1, RevenueEntry::where('kind', 'estimate_paid')->count());
        $this->assertSame(550000, (int) RevenueEntry::sum('amount'));
    }

    public function test_adopted_charge_deletion_keeps_estimate_revenue_once(): void
    {
        $charge = $this->pay(['type' => 'charge', 'amount' => 400000, 'paid_at' => '2026-08-25']);
        $estimate = $this->makePaidEstimate(['project_id' => $this->project->id]);

        $charge->delete();
        $entries = RevenueEntry::where('estimate_id', $estimate->id)->get();
        $this->assertSame(1, $entries->count()); // 견적 매출은 유지, 이중 집계 없음
        $this->assertNull($entries->first()->payment_id);
        $this->assertSame(400000, (int) RevenueEntry::sum('amount'));
    }

    public function test_revenue_detail_no_duplicate_card_for_adopted_estimate(): void
    {
        // 매출 상세 — 수동 결제 카드 하나만, '견적 결제완료' 카드가 따로 뜨지 않는다
        $this->pay(['type' => 'charge', 'amount' => 400000, 'paid_at' => now()->toDateString(), 'method' => '계좌이체']);
        $this->makePaidEstimate(['project_id' => $this->project->id]);

        $res = $this->actingAs($this->user)->getJson('/api/marketing-report/revenue-projects');
        $res->assertOk()
            ->assertJsonPath('total', 400000)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('projects.0.source', 'payment');
        // 입양된 견적서가 카드에 연결돼 '견적서 보기'로 노출된다
        $estimate = Estimate::where('project_id', $this->project->id)->first();
        $res->assertJsonPath('projects.0.estimates.0.id', $estimate->id)
            ->assertJsonPath('projects.0.estimates.0.paid_on', now()->toDateString());
    }

    public function test_revenue_detail_links_estimate_on_linked_charge_card(): void
    {
        $estimate = $this->makePaidEstimate(['project_id' => $this->project->id]);
        $this->pay(['type' => 'charge', 'estimate_id' => $estimate->id, 'amount' => 400000, 'paid_at' => now()->toDateString()]);

        $this->actingAs($this->user)->getJson('/api/marketing-report/revenue-projects')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('projects.0.estimates.0.id', $estimate->id)
            ->assertJsonPath('projects.0.estimates.0.paid_on', now()->toDateString());
    }

    public function test_revenue_detail_page_has_period_controls(): void
    {
        $this->actingAs($this->user)->get('/marketing-report/revenue')
            ->assertOk()
            ->assertSee('id="rvFrom"', false)
            ->assertSee('id="rvTo"', false)
            ->assertSee('rvApplyPeriod', false)
            ->assertSee('rvOpenEstimate', false)
            ->assertSee('최근 3개월');
    }

    public function test_marketing_report_uses_ledger_split(): void
    {
        $estimate = $this->makePaidEstimate();
        $this->pay(['type' => 'charge', 'estimate_id' => $estimate->id, 'amount' => 400000, 'paid_at' => now()->toDateString()]);

        $res = $this->actingAs($this->user)->get('/marketing-report')->assertOk();
        $this->assertSame(400000, (int) $res->viewData('revenueTotal'));
        $this->assertSame(100000, (int) $res->viewData('revenueService'));
        $this->assertSame(300000, (int) $res->viewData('revenueProduct'));
    }

    public function test_cancelled_estimate_with_charge_nets_to_zero(): void
    {
        // 전액 환불로 결제취소된 견적 — +결제 / −환불 흔적이 남고 합계는 0
        $estimate = $this->makePaidEstimate();
        $this->pay(['type' => 'charge', 'estimate_id' => $estimate->id, 'amount' => 400000, 'paid_at' => '2026-08-10']);
        $this->pay(['type' => 'cancel', 'estimate_id' => $estimate->id, 'amount' => -400000, 'refunded_at' => '2026-08-18 10:00:00']);
        $estimate->update(['status' => 'cancelled']);

        $entries = RevenueEntry::where('estimate_id', $estimate->id)->get();
        $this->assertSame(2, $entries->count());
        $this->assertSame(0, (int) $entries->sum('amount'));
        $this->assertSame('2026-08-18', $entries->firstWhere('kind', 'estimate_refund')->recognized_on->toDateString());
    }
}
