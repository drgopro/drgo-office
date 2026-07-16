<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ConsultationType;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 프로젝트 유형 — 기본 유형 매핑 정합 + 라벨 중복 정리/방지 */
class ConsultationTypeDedupeTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_types_match_defaults_without_duplicates(): void
    {
        $types = ConsultationType::orderBy('sort_order')->get();

        // 라벨/키 중복 없음
        $this->assertSame($types->pluck('label')->unique()->count(), $types->count(), '라벨 중복 없음');
        $this->assertSame($types->pluck('key')->unique()->count(), $types->count(), '키 중복 없음');

        // 기본 11개 유형이 모두 존재하고 라벨 매핑이 DEFAULTS와 일치
        foreach (ConsultationType::DEFAULTS as $key => $label) {
            $row = $types->firstWhere('key', $key);
            $this->assertNotNull($row, "기본 유형 {$key} 존재");
            $this->assertSame($label, $row->label, "{$key} 라벨 매핑");
        }
    }

    public function test_dedupe_migration_remaps_projects_and_removes_duplicate(): void
    {
        // 시드 전에 수동 추가된 중복 '방송룸' 유형 시뮬레이션
        $dup = ConsultationType::create(['key' => 'broadcast_room_manual', 'label' => '방송룸', 'sort_order' => 99, 'is_active' => true]);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $project = Project::create([
            'client_id' => $client->id, 'name' => '방송룸 프로젝트', 'project_type' => 'broadcast_room_manual', 'stage' => 'consulting',
        ]);

        $migration = require database_path('migrations/2026_07_16_153301_dedupe_consultation_types_by_label.php');
        $migration->up();

        // 중복 행 제거 + 대표 키(broadcast)로 리매핑
        $this->assertNull(ConsultationType::find($dup->id));
        $this->assertSame(1, ConsultationType::where('label', '방송룸')->count());
        $this->assertSame('broadcast', $project->fresh()->project_type);
    }

    public function test_duplicate_label_is_rejected_on_create_and_update(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->postJson('/api/admin/consultation-types', ['label' => '방송룸'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['label']);

        $store = ConsultationType::where('key', 'store')->first();
        $this->actingAs($admin)->patchJson("/api/admin/consultation-types/{$store->id}", ['label' => '방송룸'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['label']);
    }
}
