<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Estimate;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 견적서 데이터 유효성 — 연동 데이터(의뢰자/프로젝트/품목 스냅샷)가
 * SQL 오류나 출력물 렌더 500으로 이어지지 않도록 입력 단계에서 차단.
 */
class EstimateValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Estimate $estimate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'master']);
        $this->estimate = Estimate::create([
            'status' => 'created', 'product_items' => [], 'service_items' => [],
            'product_total' => 0, 'service_total' => 0, 'total_amount' => 0,
            'validity_days' => 3, 'created_by' => $this->admin->id,
        ]);
    }

    public function test_rejects_items_missing_required_snapshot_fields(): void
    {
        // name 누락 — 저장되면 출력물 렌더에서 500이 나므로 422로 차단
        $this->actingAs($this->admin)->patchJson("/api/estimates/{$this->estimate->id}", [
            'product_items' => [['sale_price' => 10000, 'qty' => 1, 'subtotal' => 10000]],
        ])->assertStatus(422)->assertJsonValidationErrors(['product_items.0.name']);

        // sale_price·qty·subtotal 누락
        $this->actingAs($this->admin)->patchJson("/api/estimates/{$this->estimate->id}", [
            'product_items' => [['name' => '카메라']],
        ])->assertStatus(422)->assertJsonValidationErrors([
            'product_items.0.sale_price', 'product_items.0.qty', 'product_items.0.subtotal',
        ]);

        // 서비스 항목도 동일 (구버전 호환 필드)
        $this->actingAs($this->admin)->patchJson("/api/estimates/{$this->estimate->id}", [
            'service_items' => [['amount' => 50000]],
        ])->assertStatus(422)->assertJsonValidationErrors(['service_items.0.name']);

        // 음수·0수량 거부
        $this->actingAs($this->admin)->patchJson("/api/estimates/{$this->estimate->id}", [
            'product_items' => [['name' => 'X', 'sale_price' => -1, 'qty' => 0, 'subtotal' => -1]],
        ])->assertStatus(422)->assertJsonValidationErrors([
            'product_items.0.sale_price', 'product_items.0.qty', 'product_items.0.subtotal',
        ]);
    }

    public function test_snapshot_fields_survive_validation_filtering(): void
    {
        // 규칙에 포함된 전체 스냅샷 필드가 저장 후 그대로 보존돼야 한다
        // (validated()가 규칙 밖 중첩 키를 걸러내므로 회귀 방지)
        $item = [
            'product_id' => 7, 'sku' => 'CAM-001', 'category' => 'R 시리즈', 'category_root' => '비디오',
            'name' => 'EOS R50 V', 'purchase_price' => 900000, 'sale_price' => 1000000,
            'qty' => 2, 'time_required' => '1시간', 'subtotal' => 2000000, 'manual' => false,
        ];
        $this->actingAs($this->admin)->patchJson("/api/estimates/{$this->estimate->id}", [
            'product_items' => [$item],
        ])->assertOk();

        $saved = $this->estimate->fresh()->product_items[0];
        foreach ($item as $key => $value) {
            $this->assertEquals($value, $saved[$key], "스냅샷 필드 유실: {$key}");
        }
        $this->assertSame(2000000, (int) $this->estimate->fresh()->total_amount);

        // 저장된 연동 데이터로 출력물 렌더가 오류 없이 동작
        $this->actingAs($this->admin)->get("/estimates/{$this->estimate->id}/print")->assertOk();
    }

    public function test_rejects_linking_project_of_another_client(): void
    {
        $mine = Client::create(['nickname' => '내 의뢰자', 'grade' => 'normal']);
        $other = Client::create(['nickname' => '남의 의뢰자', 'grade' => 'normal']);
        $otherProject = Project::create(['client_id' => $other->id, 'name' => '남의 프로젝트', 'project_type' => 'visit', 'stage' => 'consulting']);

        // 다른 의뢰자의 프로젝트 연동 → 422 (조용한 데이터 꼬임 방지)
        $this->actingAs($this->admin)->patchJson("/api/estimates/{$this->estimate->id}", [
            'client_id' => $mine->id, 'project_id' => $otherProject->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['project_id']);
        $this->assertNull($this->estimate->fresh()->project_id);

        // 의뢰자 없이 프로젝트만 연동하는 것도 거부
        $this->actingAs($this->admin)->patchJson("/api/estimates/{$this->estimate->id}", [
            'client_id' => null, 'project_id' => $otherProject->id,
        ])->assertStatus(422);

        // 소유가 맞으면 정상 연동
        $myProject = Project::create(['client_id' => $mine->id, 'name' => '내 프로젝트', 'project_type' => 'visit', 'stage' => 'consulting']);
        $this->actingAs($this->admin)->patchJson("/api/estimates/{$this->estimate->id}", [
            'client_id' => $mine->id, 'project_id' => $myProject->id,
        ])->assertOk();
        $this->assertSame($myProject->id, $this->estimate->fresh()->project_id);
    }

    public function test_project_summary_exposes_linked_estimates_for_calendar(): void
    {
        // 캘린더 '연결 프로젝트 요약' 카드가 쓰는 summary API에 연동 견적서 노출
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $project = Project::create(['client_id' => $client->id, 'name' => '스튜디오 세팅', 'project_type' => 'visit', 'stage' => 'consulting']);

        $this->actingAs($this->admin)->patchJson("/api/estimates/{$this->estimate->id}", [
            'client_id' => $client->id, 'project_id' => $project->id,
            'product_items' => [['name' => '카메라', 'sale_price' => 100000, 'qty' => 1, 'subtotal' => 100000]],
        ])->assertOk();

        $summary = $this->actingAs($this->admin)->getJson("/api/projects/{$project->id}/summary")->assertOk()->json();
        $this->assertCount(1, $summary['estimates']);
        $this->assertSame($this->estimate->id, $summary['estimates'][0]['id']);
        $this->assertSame(100000, $summary['estimates'][0]['total_amount']);

        // 미연동 프로젝트는 빈 배열
        $other = Project::create(['client_id' => $client->id, 'name' => '다른 건', 'project_type' => 'visit', 'stage' => 'consulting']);
        $summary2 = $this->actingAs($this->admin)->getJson("/api/projects/{$other->id}/summary")->assertOk()->json();
        $this->assertSame([], $summary2['estimates']);
    }

    public function test_rejects_nonexistent_linked_ids_without_sql_error(): void
    {
        // 존재하지 않는 FK들 — SQL 제약 오류(500) 대신 422
        $this->actingAs($this->admin)->patchJson("/api/estimates/{$this->estimate->id}", [
            'client_id' => 999999,
        ])->assertStatus(422)->assertJsonValidationErrors(['client_id']);

        $this->actingAs($this->admin)->patchJson("/api/estimates/{$this->estimate->id}", [
            'project_id' => 999999,
        ])->assertStatus(422)->assertJsonValidationErrors(['project_id']);
    }
}
