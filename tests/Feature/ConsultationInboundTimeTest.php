<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Consultation;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

        // 등록/수정 모달의 시/분 분리 셀렉트(24시간 + 00/30분) + 목록에 시간 표시
        $this->actingAs($this->admin)->get("/projects/{$this->project->id}")->assertOk()
            ->assertSee('인입 시간')
            ->assertSee('id="ciHour"', false)
            ->assertSee('id="eiHour"', false)
            ->assertSee('syncInboundTime', false)
            ->assertSee('<option value="23">23</option>', false)
            ->assertSee('<option value="30">30</option>', false)
            ->assertSee('21:30'); // 상담 목록 날짜 옆 표시
    }

    public function test_stats_page_shows_inbound_hour_distribution_with_peak(): void
    {
        // 통계 페이지 — 시간대별 분포 + 피크 시간 강조 (14시 2건 > 10시 1건)
        $mk = fn (string $time) => Consultation::create([
            'project_id' => $this->project->id, 'client_id' => $this->project->client_id,
            'consulted_at' => now(), 'inbound_time' => $time,
            'consult_type' => 'phone', 'result' => 'done', 'consultant_id' => $this->admin->id,
        ]);
        $mk('10:00');
        $mk('14:00');
        $mk('14:30'); // 14시대 2건 — 피크

        $this->actingAs($this->admin)->get('/marketing-report')->assertOk()
            ->assertSee('상담 인입 시간대 분포')
            ->assertSee('피크 14:00~14:59')
            ->assertSee('chartInboundHours', false) // 00~23시 세로 막대 차트
            ->assertSee('[0,0,0,0,0,0,0,0,0,0,1,0,0,0,2,0,0,0,0,0,0,0,0,0]', false); // 10시 1건, 14시 2건
    }

    public function test_stats_page_shows_guide_without_inbound_data(): void
    {
        $this->actingAs($this->admin)->get('/marketing-report')->assertOk()
            ->assertSee('기록된 인입 시간이 없습니다');
    }

    private function configureChannelTalk(): void
    {
        config([
            'services.channeltalk.access_key' => 'test-key',
            'services.channeltalk.access_secret' => 'test-secret',
            'services.channeltalk.group' => '테스트그룹',
        ]);
    }

    public function test_inbound_suggest_returns_earliest_chat_time_floored_to_half_hour(): void
    {
        $this->configureChannelTalk();
        $this->project->client->update(['channeltalk_user_id' => 'ct-user-1']);

        // 2026-09-18(Asia/Seoul) 챗 2건(09:47, 14:10) + 다른 날짜 1건 → 가장 이른 09:47을 09:30으로 내림
        $ms = fn (string $dt) => Carbon::parse($dt, config('app.timezone'))->getTimestampMs();
        Http::fake([
            'api.channel.io/open/v5/users/ct-user-1/user-chats*' => Http::response(['userChats' => [
                ['id' => 'c1', 'createdAt' => $ms('2026-09-18 14:10'), 'state' => 'closed'],
                ['id' => 'c2', 'createdAt' => $ms('2026-09-18 09:47'), 'state' => 'closed'],
                ['id' => 'c3', 'createdAt' => $ms('2026-09-17 11:00'), 'state' => 'closed'],
            ]]),
        ]);

        $this->actingAs($this->admin)
            ->getJson("/api/projects/{$this->project->id}/consult-inbound-suggest?date=2026-09-18")
            ->assertOk()
            ->assertJson(['found' => true, 'time' => '09:30', 'chats' => 2]);
    }

    public function test_inbound_suggest_without_linked_client_skips_channeltalk(): void
    {
        $this->configureChannelTalk();
        Http::fake();

        $this->actingAs($this->admin)
            ->getJson("/api/projects/{$this->project->id}/consult-inbound-suggest?date=2026-09-18")
            ->assertOk()
            ->assertJson(['found' => false]);
        Http::assertNothingSent();
    }

    public function test_inbound_suggest_returns_not_found_when_no_chat_on_that_date(): void
    {
        $this->configureChannelTalk();
        $this->project->client->update(['channeltalk_user_id' => 'ct-user-2']);

        Http::fake([
            'api.channel.io/open/v5/users/ct-user-2/user-chats*' => Http::response(['userChats' => [
                ['id' => 'c1', 'createdAt' => Carbon::parse('2026-09-10 11:00', config('app.timezone'))->getTimestampMs(), 'state' => 'closed'],
            ]]),
        ]);

        $this->actingAs($this->admin)
            ->getJson("/api/projects/{$this->project->id}/consult-inbound-suggest?date=2026-09-18")
            ->assertOk()
            ->assertJson(['found' => false]);
    }

    public function test_project_page_renders_inbound_suggest_script(): void
    {
        // 등록 모달 — 상담일 변경/모달 오픈 시 자동 기입 스크립트 + 힌트 영역
        $this->actingAs($this->admin)->get("/projects/{$this->project->id}")->assertOk()
            ->assertSee('suggestInboundTime', false)
            ->assertSee('id="ciAutoHint"', false)
            ->assertSee('consult-inbound-suggest', false)
            ->assertSee('ciAutoFilled', false);
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
