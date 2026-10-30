<?php

namespace Tests\Feature;

use App\Models\CalendarCategory;
use App\Models\Client;
use App\Models\RentalContract;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 렌탈 메뉴 탭 — 장비 렌탈(기존) / 서버 렌탈(서버 대여 현황) 구분 */
class RentalServerTabTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'master']);
        $this->client = Client::create(['nickname' => '윤민아', 'grade' => 'normal', 'status' => 'active']);
    }

    /** @return array<string, mixed> */
    private function payload(array $extra = []): array
    {
        return array_merge([
            'client_id' => $this->client->id,
            'start_date' => '2026-07-18',
            'end_date' => null,
            'monthly_fee' => 158000,
            'status' => 'active',
        ], $extra);
    }

    public function test_page_renders_tabs_and_restores_kind_from_query(): void
    {
        $html = $this->actingAs($this->user)->get('/rental-contracts')->assertOk()->getContent();
        $this->assertStringContainsString('data-kind="equipment"', $html);
        $this->assertStringContainsString('data-kind="server"', $html);
        $this->assertStringContainsString('let KIND = "equipment"', $html);
        $this->assertStringContainsString('id="serverAccess"', $html);

        $html = $this->actingAs($this->user)->get('/rental-contracts?kind=server')->assertOk()->getContent();
        $this->assertStringContainsString('let KIND = "server"', $html);

        // 알 수 없는 종류는 장비 렌탈로
        $html = $this->actingAs($this->user)->get('/rental-contracts?kind=foo')->assertOk()->getContent();
        $this->assertStringContainsString('let KIND = "equipment"', $html);
    }

    public function test_store_server_contract_saves_server_fields_and_syncs_own_calendar_category(): void
    {
        $this->actingAs($this->user)->postJson('/api/rental-contracts', $this->payload([
            'kind' => 'server',
            'server_name' => '스트리밍 서버 A',
            'server_spec' => 'Ryzen 9 · 64GB · RTX 4090',
            'server_access' => '10.0.0.5:3389',
            'memo' => '야간 재부팅 금지',
        ]))->assertCreated()->assertJsonPath('kind', 'server')->assertJsonPath('server_name', '스트리밍 서버 A');

        $contract = RentalContract::first();
        $this->assertTrue($contract->isServer());
        $this->assertSame('서버 렌탈', $contract->kindLabel());

        $start = Schedule::find($contract->calendar_meta['start_id']);
        $this->assertSame('서버 렌탈 시작 · 윤민아', $start->title);
        $this->assertStringContainsString('· 서버: 스트리밍 서버 A', $start->description);
        $this->assertStringContainsString('· 사양: Ryzen 9', $start->description);
        $this->assertStringNotContainsString('10.0.0.5', $start->description, '접속 정보는 전 직원 공개 캘린더에 복제하지 않는다');
        $this->assertStringContainsString('야간 재부팅 금지', $start->description);

        $cat = CalendarCategory::where('label', '서버 렌탈')->first();
        $this->assertNotNull($cat);
        $this->assertSame('server_rental', $cat->key);
        $this->assertSame($cat->key, $start->color);

        $pay = Schedule::where('repeat_group', $contract->calendar_meta['repeat_group'])->orderBy('start_date')->first();
        $this->assertSame('서버 렌탈 결제 · 윤민아 (158,000원)', $pay->title);
    }

    public function test_kind_defaults_to_equipment_and_keeps_existing_titles(): void
    {
        $this->actingAs($this->user)->postJson('/api/rental-contracts', $this->payload())->assertCreated();

        $contract = RentalContract::first();
        $this->assertSame('equipment', $contract->kind);
        $this->assertSame('렌탈 시작 · 윤민아', Schedule::find($contract->calendar_meta['start_id'])->title);
        $this->assertNotNull(CalendarCategory::where('label', '렌탈')->first());
        $this->assertNull(CalendarCategory::where('label', '서버 렌탈')->first());
    }

    public function test_invalid_kind_is_rejected(): void
    {
        $this->actingAs($this->user)->postJson('/api/rental-contracts', $this->payload(['kind' => 'cloud']))
            ->assertUnprocessable()->assertJsonValidationErrors('kind');
    }

    public function test_list_filters_by_kind_and_includes_server_fields(): void
    {
        RentalContract::create($this->payload());
        RentalContract::create($this->payload(['kind' => 'server', 'server_name' => '서버 B', 'server_access' => '10.0.0.6']));

        $this->actingAs($this->user)->getJson('/api/rental-contracts')->assertOk()->assertJsonCount(2);

        $server = $this->actingAs($this->user)->getJson('/api/rental-contracts?kind=server')->assertOk()->assertJsonCount(1);
        $server->assertJsonPath('0.kind', 'server')->assertJsonPath('0.server_name', '서버 B')->assertJsonPath('0.server_access', '10.0.0.6');

        $this->actingAs($this->user)->getJson('/api/rental-contracts?kind=equipment')->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.kind', 'equipment')->assertJsonPath('0.server_name', null);
    }

    public function test_equipment_contract_never_keeps_server_fields(): void
    {
        // 장비 렌탈로 등록하면서 서버 필드를 보내도 저장되지 않는다
        $this->actingAs($this->user)->postJson('/api/rental-contracts', $this->payload([
            'server_name' => '몰래', 'server_access' => '10.0.0.9',
        ]))->assertCreated()->assertJsonPath('server_name', null)->assertJsonPath('server_access', null);

        // 서버 → 장비로 바꾸면 기존 서버 필드도 함께 비워진다
        $contract = RentalContract::create($this->payload(['kind' => 'server', 'server_name' => '서버 D', 'server_access' => '10.0.0.7']));
        $this->actingAs($this->user)->patchJson("/api/rental-contracts/{$contract->id}", ['kind' => 'equipment'])->assertOk();
        $contract->refresh();
        $this->assertSame('equipment', $contract->kind);
        $this->assertNull($contract->server_name);
        $this->assertNull($contract->server_access);
    }

    public function test_update_can_change_kind_to_server_and_resyncs_titles(): void
    {
        $this->actingAs($this->user)->postJson('/api/rental-contracts', $this->payload())->assertCreated();
        $contract = RentalContract::first();

        $this->actingAs($this->user)->patchJson("/api/rental-contracts/{$contract->id}", [
            'kind' => 'server', 'server_name' => '서버 C',
        ])->assertOk();

        $contract->refresh();
        $this->assertTrue($contract->isServer());
        $this->assertSame('서버 렌탈 시작 · 윤민아', Schedule::find($contract->calendar_meta['start_id'])->title);
    }
}
