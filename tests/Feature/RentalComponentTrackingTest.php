<?php

namespace Tests\Feature;

use App\Models\RentalItem;
use App\Models\RentalLog;
use App\Models\RentalTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 장비 구성품 추적 — 항목 단위 구성품 + 이동 시 체크, 누락 구성품 마지막 위치 기록 */
class RentalComponentTrackingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private RentalTarget $studio;

    private RentalTarget $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->studio = RentalTarget::create(['name' => '스튜디오']);
        $this->client = RentalTarget::create(['name' => '의뢰자A']);
    }

    public function test_item_saves_structured_components(): void
    {
        $res = $this->actingAs($this->admin)->postJson('/api/rental/items', [
            'name' => '송출컴', 'home_target_id' => $this->studio->id,
            'component_items' => [
                ['name' => '전원 케이블', 'left_target_id' => null],
                ['name' => '리모컨', 'left_target_id' => null],
            ],
        ])->assertCreated();

        $item = RentalItem::find($res->json('id'));
        $this->assertSame(['전원 케이블', '리모컨'], array_column($item->component_items, 'name'));
    }

    public function test_move_records_unchecked_component_last_location(): void
    {
        $item = RentalItem::create([
            'name' => '송출컴', 'current_target_id' => $this->studio->id, 'home_target_id' => $this->studio->id,
            'component_items' => [
                ['name' => '전원 케이블', 'left_target_id' => null],
                ['name' => '리모컨', 'left_target_id' => null],
            ],
        ]);

        // 리모컨(인덱스 1)을 빼고 이동 → 리모컨은 스튜디오(이전 위치)에 남음
        $this->actingAs($this->admin)->postJson('/api/rental/assign', [
            'item_id' => $item->id, 'target_id' => $this->client->id, 'components_moved' => [0],
        ])->assertOk();

        $comps = $item->fresh()->component_items;
        $this->assertNull($comps[0]['left_target_id']);
        $this->assertSame($this->studio->id, $comps[1]['left_target_id']);
        $this->assertDatabaseHas('rental_logs', ['item_id' => $item->id]);
        $this->assertStringContainsString('구성품 남김(스튜디오): 리모컨', RentalLog::latest('id')->first()->detail);

        // 남겨진 구성품을 다시 체크해 반납 → 회수(left_target_id=null)
        $this->actingAs($this->admin)->postJson('/api/rental/assign', [
            'item_id' => $item->id, 'return' => true, 'components_moved' => [0, 1],
        ])->assertOk();
        $this->assertNull($item->fresh()->component_items[1]['left_target_id']);
    }

    public function test_component_left_elsewhere_stays_when_unchecked_again(): void
    {
        $other = RentalTarget::create(['name' => '창고']);
        $item = RentalItem::create([
            'name' => '캠 세트', 'current_target_id' => $this->studio->id, 'home_target_id' => $this->studio->id,
            'component_items' => [['name' => '삼각대', 'left_target_id' => $other->id]], // 이미 창고에 남아 있음
        ]);

        // 삼각대 미체크로 이동 — 마지막 위치(창고) 유지, 이전 위치로 덮어쓰지 않음
        $this->actingAs($this->admin)->postJson('/api/rental/assign', [
            'item_id' => $item->id, 'target_id' => $this->client->id, 'components_moved' => [],
        ])->assertOk();

        $this->assertSame($other->id, $item->fresh()->component_items[0]['left_target_id']);
    }

    public function test_board_returns_components_and_page_renders_ui(): void
    {
        RentalItem::create(['name' => '송출컴', 'component_items' => [['name' => '케이블', 'left_target_id' => null]]]);

        $this->actingAs($this->admin)->getJson('/api/rental/board')->assertOk()
            ->assertJsonPath('items.0.component_items.0.name', '케이블');

        $this->actingAs($this->admin)->get('/rental-equipment')->assertOk()
            ->assertSee('구성품 추가')
            ->assertSee('전체선택')
            ->assertSee('id="compMoveModal"', false)
            ->assertSee('openCompMoveModal', false)
            ->assertSee('마지막 위치');
    }
}
