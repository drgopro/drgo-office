<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Consultation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/** 상담 인입 시간 — 30분 단위 24시간 입력 + 통계 엑셀 상담 이력 노출 */
class ConsultationInboundTimeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $this->project = Project::create(['client_id' => $client->id, 'name' => '캠 세팅', 'project_type' => 'visit', 'stage' => 'consulting']);
    }

    public function test_store_saves_inbound_time_and_rejects_invalid(): void
    {
        $payload = fn (?string $time) => [
            'consulted_at' => now()->format('Y-m-d'), 'consult_type' => 'phone',
            'result' => 'in_progress', 'content' => '상담', 'inbound_time' => $time,
        ];

        // 30분 단위 24시간 표기 — 저장
        $this->actingAs($this->admin)->post("/projects/{$this->project->id}/consultations", $payload('14:30'))
            ->assertRedirect(route('projects.show', $this->project));
        $this->assertSame('14:30', Consultation::latest('id')->first()->inbound_time);

        // 미입력 허용
        $this->actingAs($this->admin)->post("/projects/{$this->project->id}/consultations", $payload(null))
            ->assertRedirect(route('projects.show', $this->project));
        $this->assertNull(Consultation::latest('id')->first()->inbound_time);

        // 30분 단위가 아니거나 24시간 밖이면 거부
        foreach (['14:15', '24:00', '9:00', '14시'] as $bad) {
            $this->actingAs($this->admin)
                ->postJson("/projects/{$this->project->id}/consultations", $payload($bad))
                ->assertStatus(422);
        }
    }

    public function test_update_changes_inbound_time_and_page_renders_form(): void
    {
        $consultation = Consultation::create([
            'project_id' => $this->project->id, 'client_id' => $this->project->client_id,
            'consulted_at' => now(), 'inbound_time' => '09:00',
            'consult_type' => 'phone', 'result' => 'in_progress', 'consultant_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->patch("/consultations/{$consultation->id}", [
            'consulted_at' => now()->format('Y-m-d'), 'consult_type' => 'kakao',
            'result' => 'done', 'inbound_time' => '21:30',
        ])->assertRedirect(route('projects.show', $this->project));
        $this->assertSame('21:30', $consultation->fresh()->inbound_time);

        // 등록/수정 모달 셀렉트(30분 단위 옵션) + 목록에 시간 표시
        $this->actingAs($this->admin)->get("/projects/{$this->project->id}")->assertOk()
            ->assertSee('인입 시간')
            ->assertSee('name="inbound_time"', false)
            ->assertSee('id="editInboundTime"', false)
            ->assertSee('<option value="23:30">23:30</option>', false)
            ->assertSee('21:30'); // 상담 목록 날짜 옆 표시
    }

    public function test_excel_consultation_sheet_includes_inbound_time(): void
    {
        Consultation::create([
            'project_id' => $this->project->id, 'client_id' => $this->project->client_id,
            'consulted_at' => now(), 'inbound_time' => '10:30',
            'consult_type' => 'phone', 'result' => 'done', 'consultant_id' => $this->admin->id,
        ]);

        $from = now()->startOfMonth()->format('Y-m-d');
        $to = now()->format('Y-m-d');
        $res = $this->actingAs($this->admin)->get("/api/dashboard-export/excel?from={$from}&to={$to}")->assertOk();

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($tmp, $res->streamedContent());
        $sheet = IOFactory::load($tmp)->getSheetByName('상담 이력');
        unlink($tmp);

        $this->assertSame('인입 시간', $sheet->getCell('N1')->getValue());
        $this->assertSame('10:30', $sheet->getCell('N2')->getValue());
    }
}
