<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 작업 유형 — 프로젝트 유형 종속 구조 (type_key) */
class WorkTypeDependencyTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, list<string>> 기획안: 프로젝트 유형 → 작업 유형 키 */
    private const EXPECTED_MAP = [
        'inquiry' => ['simple'],
        'remote' => ['troubleshoot', 'as'],
        'visit' => ['general', 'revisit', 'rush', 'survey'],
        'store' => ['outdoor_setup', 'edu_consulting'],
        'filming' => ['relay', 'outdoor'],
        'design' => ['design', 'video', 'audio'],
        'rental' => ['rental', 'equip_single'],
        'broadcast' => ['hourly', 'daily', 'monthly'],
        'product_inquiry' => ['shop'],
    ];

    public function test_seed_maps_work_types_to_project_types(): void
    {
        foreach (self::EXPECTED_MAP as $typeKey => $workKeys) {
            $actual = WorkType::where('type_key', $typeKey)->where('is_active', true)
                ->orderBy('sort_order')->pluck('key')->all();
            $this->assertSame($workKeys, $actual, "{$typeKey} 종속 작업 유형");
        }

        // 기획안에 없는 레거시 키는 비활성화
        foreach (['setup', 'remote', 'filming', 'dispatch'] as $legacy) {
            $row = WorkType::where('key', $legacy)->first();
            if ($row) {
                $this->assertFalse((bool) $row->is_active, "레거시 {$legacy} 비활성");
            }
        }
    }

    public function test_active_endpoint_returns_type_key(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $res = $this->actingAs($user)->getJson('/api/work-types/active');
        $res->assertOk();

        $rows = collect($res->json());
        $this->assertSame('remote', $rows->firstWhere('key', 'troubleshoot')['type_key']);
        $this->assertSame('visit', $rows->firstWhere('key', 'general')['type_key']);
        $this->assertNull($rows->firstWhere('key', 'setup'), '비활성 항목 미노출');
    }

    public function test_admin_can_store_work_type_with_type_key(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $res = $this->actingAs($admin)->postJson('/api/admin/work-types', [
            'label' => '라이브 송출',
            'type_key' => 'filming',
        ]);
        $res->assertCreated();
        $this->assertDatabaseHas('work_types', ['label' => '라이브 송출', 'type_key' => 'filming']);
    }

    public function test_store_rejects_unknown_type_key(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->postJson('/api/admin/work-types', [
            'label' => '잘못된 유형',
            'type_key' => 'nonexistent_type',
        ])->assertUnprocessable()->assertJsonValidationErrors(['type_key']);
    }

    public function test_admin_can_update_type_key_and_clear_to_common(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $wt = WorkType::where('key', 'survey')->firstOrFail();

        $this->actingAs($admin)->patchJson("/api/admin/work-types/{$wt->id}", [
            'type_key' => 'filming',
        ])->assertOk();
        $this->assertSame('filming', $wt->fresh()->type_key);

        // null로 비우면 공통(모든 유형) 노출
        $this->actingAs($admin)->patchJson("/api/admin/work-types/{$wt->id}", [
            'type_key' => null,
        ])->assertOk();
        $this->assertNull($wt->fresh()->type_key);
    }
}
