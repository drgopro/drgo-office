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

    public function test_board_page_uses_location_wording_instead_of_target(): void
    {
        $this->actingAs($this->admin)->get('/rental-equipment')->assertOk()
            ->assertSee('＋ 위치')
            ->assertSee('이동할 위치')
            ->assertDontSee('＋ 대상')
            ->assertDontSee('사용 대상');
    }

    public function test_in_use_count_excludes_items_still_at_home_location(): void
    {
        // 원래 위치 그대로 — 대여 아님
        RentalItem::create(['name' => '집에 있는 장비', 'current_target_id' => $this->studio->id, 'home_target_id' => $this->studio->id]);
        // 원래 위치에서 이동 — 대여중
        RentalItem::create(['name' => '나간 장비', 'current_target_id' => $this->client->id, 'home_target_id' => $this->studio->id]);
        // 원래 위치 미지정 + 어딘가에 있음 — 대여중
        RentalItem::create(['name' => '홈 미지정 장비', 'current_target_id' => $this->client->id, 'home_target_id' => null]);
        // 위치 미지정 — 대여 아님
        RentalItem::create(['name' => '위치 없는 장비']);

        $this->actingAs($this->admin)->getJson('/api/rental/board')
            ->assertOk()
            ->assertJsonPath('in_use_count', 2);
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

    public function test_camera_permission_policy_allows_self_for_qr_scan(): void
    {
        // camera=()로 전면 차단하면 안드로이드에서 권한 프롬프트 자체가 뜨지 않음 —
        // QR 스캔을 위해 동일 출처(self)만 허용, 마이크/위치는 계속 차단
        $res = $this->actingAs($this->admin)->get('/rental-equipment')->assertOk();
        $policy = $res->headers->get('Permissions-Policy');
        $this->assertStringContainsString('camera=(self)', $policy);
        $this->assertStringContainsString('microphone=()', $policy);
        $this->assertStringContainsString('geolocation=()', $policy);
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
            ->assertSee('마지막 위치')
            // QR 스캐너 — 자체 호스팅 라이브러리 + 카메라 폴백/전환
            ->assertSee('/vendor/html5-qrcode.min.js', false)
            ->assertSee('switchQrCamera', false)
            ->assertSee('getCameras', false);

        $this->assertFileExists(public_path('vendor/html5-qrcode.min.js'));
    }
}
