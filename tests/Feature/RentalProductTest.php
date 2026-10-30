<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\RentalContract;
use App\Models\RentalProduct;
use App\Models\Schedule;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 렌탈 상품(항목) 관리 + 계약 등록 시 상품 선택 → 금액 자동/수동, 준비중 상태 */
class RentalProductTest extends TestCase
{
    use RefreshDatabase;

    private User $master;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->master = User::factory()->create(['role' => 'master']);
        $this->client = Client::create(['nickname' => '윤민아', 'grade' => 'normal', 'status' => 'active']);
    }

    /** @return array<string, mixed> */
    private function payload(array $extra = []): array
    {
        return array_merge([
            'client_id' => $this->client->id,
            'start_date' => '2026-07-18',
            'end_date' => null,
            'status' => 'active',
        ], $extra);
    }

    public function test_products_crud_by_kind(): void
    {
        $res = $this->actingAs($this->master)->postJson('/api/rental-products', [
            'kind' => 'server', 'name' => 'OBS 스트리밍 서버 (표준)', 'default_fee' => 158000, 'memo' => 'Ryzen 9 · 64GB',
        ])->assertCreated()->assertJsonPath('kind', 'server')->assertJsonPath('default_fee', 158000);
        $id = $res->json('id');

        $this->actingAs($this->master)->postJson('/api/rental-products', ['name' => '방송 풀세트', 'default_fee' => 258000])
            ->assertCreated()->assertJsonPath('kind', 'equipment');

        // 종류별 목록
        $this->actingAs($this->master)->getJson('/api/rental-products?kind=server')->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'OBS 스트리밍 서버 (표준)');
        $this->actingAs($this->master)->getJson('/api/rental-products?kind=equipment')->assertOk()->assertJsonCount(1);

        // 편집 + 숨김(비활성)은 기본 목록에서 빠지고 include_inactive 로만 보임
        $this->actingAs($this->master)->patchJson("/api/rental-products/{$id}", ['default_fee' => 168000, 'is_active' => false])->assertOk()->assertJsonPath('default_fee', 168000);
        $this->actingAs($this->master)->getJson('/api/rental-products?kind=server')->assertOk()->assertJsonCount(0);
        $this->actingAs($this->master)->getJson('/api/rental-products?kind=server&include_inactive=1')->assertOk()->assertJsonCount(1);

        // 삭제(소프트)
        $this->actingAs($this->master)->deleteJson("/api/rental-products/{$id}")->assertOk();
        $this->assertSoftDeleted('rental_products', ['id' => $id]);
    }

    public function test_product_write_requires_rental_edit_but_read_only_needs_rental_view(): void
    {
        $viewTeam = Team::create(['name' => '조회팀', 'slug' => 'view-'.uniqid(), 'permissions' => ['rental.view']]);
        $viewer = User::factory()->create(['role' => 'member', 'team_id' => $viewTeam->id]);

        $this->actingAs($viewer)->getJson('/api/rental-products')->assertOk();
        $this->actingAs($viewer)->postJson('/api/rental-products', ['name' => 'x', 'default_fee' => 1])->assertForbidden();

        $editTeam = Team::create(['name' => '렌탈팀', 'slug' => 'edit-'.uniqid(), 'permissions' => ['rental.view', 'rental.edit']]);
        $editor = User::factory()->create(['role' => 'member', 'team_id' => $editTeam->id]);
        $this->actingAs($editor)->postJson('/api/rental-products', ['name' => '세트', 'default_fee' => 1000])->assertCreated();
    }

    public function test_choosing_product_fills_fee_and_name_snapshot(): void
    {
        $product = RentalProduct::create(['kind' => 'equipment', 'name' => '방송 풀세트', 'default_fee' => 258000]);

        // 금액을 보내지 않아도 상품 기본 금액으로 저장
        $this->actingAs($this->master)->postJson('/api/rental-contracts', $this->payload(['product_id' => $product->id]))
            ->assertCreated()->assertJsonPath('monthly_fee', 258000)->assertJsonPath('product_name', '방송 풀세트')->assertJsonPath('fee_manual', false);

        // 자동이면 보낸 금액이 있어도 상품 금액이 우선
        $this->actingAs($this->master)->postJson('/api/rental-contracts', $this->payload(['product_id' => $product->id, 'monthly_fee' => 1000]))
            ->assertCreated()->assertJsonPath('monthly_fee', 258000);

        // 캘린더 제목에 상품명 포함
        $contract = RentalContract::first();
        $this->assertSame('렌탈 시작 · 윤민아 · 방송 풀세트', Schedule::find($contract->calendar_meta['start_id'])->title);

        // 상품 금액이 바뀌거나 삭제되어도 기존 계약은 그대로 (스냅샷)
        $product->update(['default_fee' => 300000, 'name' => '방송 풀세트 v2']);
        $this->actingAs($this->master)->deleteJson("/api/rental-products/{$product->id}")->assertOk();
        $this->actingAs($this->master)->getJson('/api/rental-contracts?kind=equipment')->assertOk()
            ->assertJsonPath('0.product_name', '방송 풀세트')->assertJsonPath('0.monthly_fee', 258000)
            ->assertJsonPath('0.product_id', null)->assertJsonPath('0.fee_manual', true);

        // 삭제된 상품을 쓰던 계약도 그대로 편집 가능
        $this->actingAs($this->master)->patchJson("/api/rental-contracts/{$contract->id}", ['memo' => '편집'])->assertOk();
    }

    public function test_product_kind_must_match_contract_kind_and_cannot_change_later(): void
    {
        $server = RentalProduct::create(['kind' => 'server', 'name' => 'OBS 서버', 'default_fee' => 158000]);

        $this->actingAs($this->master)->postJson('/api/rental-contracts', $this->payload(['kind' => 'equipment', 'product_id' => $server->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('product_id');
        $this->actingAs($this->master)->postJson('/api/rental-contracts', $this->payload(['kind' => 'server', 'product_id' => $server->id]))
            ->assertCreated()->assertJsonPath('monthly_fee', 158000);

        // 상품 종류는 생성 후 바꿀 수 없다 (기존 계약과 어긋남 방지)
        $this->actingAs($this->master)->patchJson("/api/rental-products/{$server->id}", ['kind' => 'equipment', 'name' => 'OBS 서버 2'])
            ->assertOk()->assertJsonPath('kind', 'server')->assertJsonPath('name', 'OBS 서버 2');
    }

    public function test_manual_fee_overrides_product_fee_and_custom_product_name_is_allowed(): void
    {
        $product = RentalProduct::create(['kind' => 'equipment', 'name' => '방송 풀세트', 'default_fee' => 258000]);

        // 세트 일부 제외 — 직접 입력
        $this->actingAs($this->master)->postJson('/api/rental-contracts', $this->payload([
            'product_id' => $product->id, 'monthly_fee' => 228000, 'fee_manual' => true, 'memo' => '마이크 제외',
        ]))->assertCreated()->assertJsonPath('monthly_fee', 228000)->assertJsonPath('fee_manual', true)->assertJsonPath('product_name', '방송 풀세트');

        // 상품 없이 상품명·금액 직접 입력
        $this->actingAs($this->master)->postJson('/api/rental-contracts', $this->payload([
            'product_id' => null, 'product_name' => '단품 캡처보드', 'monthly_fee' => 30000,
        ]))->assertCreated()->assertJsonPath('product_name', '단품 캡처보드')->assertJsonPath('monthly_fee', 30000);

        // 상품도 금액도 없으면 거부
        $this->actingAs($this->master)->postJson('/api/rental-contracts', $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors('monthly_fee');

        // 수동 → 자동으로 되돌리면 상품 금액으로 복귀
        $manual = RentalContract::where('fee_manual', true)->first();
        $this->actingAs($this->master)->patchJson("/api/rental-contracts/{$manual->id}", ['fee_manual' => false])
            ->assertOk()->assertJsonPath('monthly_fee', 258000)->assertJsonPath('fee_manual', false);
    }

    public function test_pending_status_syncs_start_event_without_payment_repeats(): void
    {
        $this->actingAs($this->master)->postJson('/api/rental-contracts', $this->payload([
            'monthly_fee' => 158000, 'status' => 'pending',
        ]))->assertCreated()->assertJsonPath('status', 'pending');

        $contract = RentalContract::first();
        $this->assertTrue($contract->isPending());
        $this->assertNotNull($contract->calendar_meta['start_id']);
        $this->assertNull($contract->calendar_meta['repeat_group'], '준비중은 결제 반복 없음');

        // 진행중으로 바꾸면 결제 반복 생성
        $this->actingAs($this->master)->patchJson("/api/rental-contracts/{$contract->id}", ['status' => 'active'])->assertOk();
        $meta = $contract->fresh()->calendar_meta;
        $this->assertNotNull($meta['repeat_group']);
        $this->assertSame(13, Schedule::where('repeat_group', $meta['repeat_group'])->count());

        $this->actingAs($this->master)->postJson('/api/rental-contracts', $this->payload(['monthly_fee' => 1, 'status' => 'paused']))
            ->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_page_renders_cards_product_picker_and_status_options(): void
    {
        $html = $this->actingAs($this->master)->get('/rental-contracts')->assertOk()->getContent();

        $this->assertStringContainsString('id="contractCards"', $html);
        $this->assertStringContainsString('id="productSelect"', $html);
        $this->assertStringContainsString('id="feeManual"', $html);
        $this->assertStringContainsString('data-status="pending"', $html);
        $this->assertStringContainsString('<option value="updated">최근 업데이트순</option>', $html);
        $this->assertStringContainsString('id="productModal"', $html);
        $this->assertStringContainsString('class="card-updated"', $html, '리스트형 카드 — 정렬 기준일(업데이트) 표시');
        $this->assertStringNotContainsString('<table>', $html, '표 대신 모든 폭에서 카드');
    }
}
