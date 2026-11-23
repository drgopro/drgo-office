<?php

namespace Tests\Feature;

use App\Models\ConsultationType;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** 렌탈 유형 진행 단계 — 결제/계약 뒤에 '렌탈 이용'·'완료'가 있어 회수 후 완료 처리할 수 있다 (피드백 #290) */
class RentalFlowStagesTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_11_23_150910_add_use_and_done_stages_to_rental_flow.php';

    private const OLD_FLOW = [
        ['code' => 'consulting', 'label' => '상담', 'kind' => 'normal'],
        ['code' => 'proposal', 'label' => '일정제안', 'kind' => 'proposal'],
        ['code' => 'estimate', 'label' => '견적', 'kind' => 'estimate'],
        ['code' => 'payment', 'label' => '결제/계약', 'kind' => 'payment'],
    ];

    public function test_rental_flow_ends_with_use_and_done(): void
    {
        $this->assertSame(
            ['consulting', 'proposal', 'estimate', 'payment', 'visit', 'done'],
            array_column(ConsultationType::stagesFor('rental'), 'code'),
        );
        $this->assertSame('렌탈 이용', ConsultationType::stagesFor('rental')[4]['label']);
    }

    public function test_migration_appends_missing_stages_to_a_stored_flow_once(): void
    {
        DB::table('consultation_types')->where('key', 'rental')
            ->update(['stages' => json_encode(self::OLD_FLOW, JSON_UNESCAPED_UNICODE)]);

        $migration = require base_path(self::MIGRATION);
        $migration->up();
        $migration->up(); // 다시 돌려도 중복으로 붙지 않는다

        $this->assertSame(
            ['consulting', 'proposal', 'estimate', 'payment', 'visit', 'done'],
            array_column(ConsultationType::stagesFor('rental'), 'code'),
        );
    }

    public function test_rental_project_can_move_to_use_stage_and_complete(): void
    {
        $user = User::factory()->create(['role' => 'master']);
        $project = Project::create(['name' => '렌탈 테스트', 'project_type' => 'rental', 'stage' => 'payment']);

        $this->actingAs($user)->patchJson("/projects/{$project->id}/stage", ['stage' => 'visit'])->assertOk();
        $this->assertSame('렌탈 이용', $project->fresh()->stageLabel());

        $this->actingAs($user)->patchJson("/projects/{$project->id}/stage", ['stage' => 'done'])->assertOk();
        $this->assertNotNull($project->fresh()->completed_at);
    }
}
