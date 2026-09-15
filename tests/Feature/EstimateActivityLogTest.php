<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Estimate;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 견적서 수정 로그 — 빌더의 로그 버튼/모달 + 변경 이력 기록·조회 */
class EstimateActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_builder_renders_log_button_and_modal(): void
    {
        $user = User::factory()->create(['role' => 'master']);
        $estimate = Estimate::create(['status' => 'created', 'product_items' => [], 'service_items' => [], 'total_amount' => 0, 'created_by' => $user->id]);

        // 독립 레이아웃인 빌더에도 로그 버튼 + 공용 모달(openActivityLog)이 함께 렌더되어야 함
        $this->actingAs($user)->get("/estimates/{$estimate->id}/edit")->assertOk()
            ->assertSee("openActivityLog('Estimate'", false)
            ->assertSee('id="activityLogOverlay"', false)
            ->assertSee('async function openActivityLog', false);
    }

    public function test_estimate_update_is_logged_and_queryable(): void
    {
        $user = User::factory()->create(['role' => 'master']);
        $estimate = Estimate::create(['status' => 'created', 'client_nickname' => '고블린', 'product_items' => [], 'service_items' => [], 'total_amount' => 0, 'created_by' => $user->id]);

        $this->actingAs($user)->patchJson("/api/estimates/{$estimate->id}", [
            'status' => 'completed',
            'product_items' => [['name' => '카메라', 'sale_price' => 100000, 'qty' => 1, 'subtotal' => 100000]],
            'service_items' => [],
        ])->assertOk();

        $logs = $this->actingAs($user)->getJson("/api/activity-logs?type=Estimate&id={$estimate->id}")->assertOk()->json();
        $this->assertNotEmpty($logs);

        $update = collect($logs)->firstWhere('action', 'update');
        $this->assertNotNull($update, '견적서 수정 로그가 없습니다');
        $changes = $update['changes'];
        $this->assertSame(['old' => '작성중', 'new' => '완료'], $changes['상태']);   // 상태 코드 → 한글
        $this->assertSame('1개 항목', $changes['제품항목']['new']);                 // 항목 배열은 개수 요약
        $this->assertSame(100000, (int) $changes['총액']['new']);
    }

    public function test_layout_pages_still_have_shared_log_modal(): void
    {
        // 파셜 추출 회귀 — app/tab-content 레이아웃 페이지에서도 모달 정상 포함
        $user = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['name' => '테스트', 'grade' => 'normal']);
        $project = Project::create(['client_id' => $client->id, 'name' => '집 세팅']);

        $this->actingAs($user)->get("/projects/{$project->id}")->assertOk()
            ->assertSee('id="activityLogOverlay"', false)
            ->assertSee('async function openActivityLog', false);
    }
}
