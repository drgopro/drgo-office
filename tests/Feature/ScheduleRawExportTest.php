<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

/** 일정통계 RAW 엑셀 — 마케팅부 추출 사양서 형식 (17컬럼, 1건=1행) */
class ScheduleRawExportTest extends TestCase
{
    use RefreshDatabase;

    private function download(string $from, string $to): Spreadsheet
    {
        $user = User::factory()->create(['role' => 'admin']);
        $res = $this->actingAs($user)->get("/marketing-report/schedules-export-raw?from={$from}&to={$to}");
        $res->assertOk();

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($tmp, $res->streamedContent());

        return IOFactory::load($tmp);
    }

    public function test_raw_export_has_17_columns_and_mapped_values(): void
    {
        $client = Client::create(['nickname' => '옴니버', 'grade' => 'normal', 'platforms' => ['SOOP']]);
        $project = Project::create([
            'name' => '옴니버 스튜디오', 'project_type' => 'visit', 'stage' => 'consulting',
            'client_id' => $client->id, 'client_scale' => 'studio',
        ]);

        // 재방문 판정용 과거 일정 (기간 밖)
        Schedule::create([
            'title' => '옴니버 1차 방문', 'start_date' => '2026-06-10', 'end_date' => '2026-06-10',
            'color' => 'gold', 'is_all_day' => true,
            'request_data' => ['client_id' => $client->id, 'nickname' => '옴니버'],
        ]);

        $visit = Schedule::create([
            'title' => '옴니버 강남 방문세팅 (급행)', 'start_date' => '2026-07-03', 'end_date' => '2026-07-03',
            'color' => 'gold', 'start_time' => '14:00', 'end_time' => '17:00', 'is_all_day' => false,
            'client_name' => '옴니버', 'address' => '서울특별시 강남구 역삼동 123', 'completed_at' => now(),
            'request_data' => [
                'client_id' => $client->id, 'project_id' => $project->id, 'nickname' => '옴니버',
                'platform' => 'soop', 'career' => '신규', 'estimate_amount' => '2,500,000',
            ],
        ]);
        Schedule::create([
            'title' => '사내 회의', 'start_date' => '2026-07-05', 'end_date' => '2026-07-05',
            'color' => 'blue', 'is_all_day' => true,
        ]);

        $sheet = $this->download('2026-07-01', '2026-07-31')->getSheet(0);
        $rows = $sheet->toArray();

        // 헤더 17컬럼 순서 고정
        $this->assertSame(
            ['일정ID', '날짜', '요일', '시작시간', '종료시간', '제목', '유형', '의뢰자 유형', '의뢰자명', '플랫폼', '경력', '결제금액', '속성', '상태', '주담당자', '보조담당자', '지역'],
            array_slice($rows[0], 0, 17)
        );

        // 방문 건 — 매핑·정규화 확인
        $visitRow = collect($rows)->first(fn ($r) => str_contains((string) $r[5], '방문세팅 (급행)'));
        $this->assertSame('CAL-202607-'.str_pad((string) $visit->id, 4, '0', STR_PAD_LEFT), $visitRow[0]);
        $this->assertSame('금', $visitRow[2]);
        $this->assertSame('14:00', $visitRow[3]);
        $this->assertSame('17:00', $visitRow[4]);
        $this->assertSame('방문세팅', $visitRow[6]);
        $this->assertSame('스튜디오', $visitRow[7]); // 프로젝트 client_scale
        $this->assertSame('숲', $visitRow[9]);       // soop → 숲 정규화
        $this->assertSame('처음', $visitRow[10]);    // 신규 → 처음 통합
        $this->assertEquals(2500000, $visitRow[11]);
        $this->assertSame('급행;재방문', $visitRow[12]); // 제목 급행 + 과거 이력 재방문
        $this->assertSame('완료', $visitRow[13]);
        $this->assertSame('서울 강남구', $visitRow[16]);

        // 사내 건 — 해당없음 규칙
        $internalRow = collect($rows)->first(fn ($r) => $r[5] === '사내 회의');
        $this->assertSame('사내업무', $internalRow[6]);
        $this->assertSame('해당없음', $internalRow[7]);
        $this->assertSame('해당없음', $internalRow[8]);
        $this->assertSame('해당없음', $internalRow[9]);
        $this->assertSame('종일', $internalRow[3]);
        $this->assertSame('예정', $internalRow[13]);
    }

    public function test_guest_is_blocked(): void
    {
        $guest = User::factory()->create(['role' => 'guest']);
        $this->actingAs($guest)->get('/marketing-report/schedules-export-raw')->assertForbidden();
    }
}
