<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

/** 통계 일정 엑셀 — 날짜별 일정 + 의뢰자 플랫폼·경력·결제, 월별 시트 분리 */
class ScheduleExcelExportTest extends TestCase
{
    use RefreshDatabase;

    private function download(string $from, string $to): Spreadsheet
    {
        $user = User::factory()->create(['role' => 'admin']);
        $res = $this->actingAs($user)->get("/marketing-report/schedules-export?from={$from}&to={$to}");
        $res->assertOk();
        $res->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($tmp, $res->streamedContent());

        return IOFactory::load($tmp);
    }

    public function test_single_month_exports_one_sheet_with_details(): void
    {
        $client = Client::create(['nickname' => '옴니버', 'grade' => 'normal', 'platforms' => ['SOOP', '유튜브']]);

        Schedule::create([
            'title' => '옴니버 방문 세팅', 'start_date' => '2026-07-03', 'end_date' => '2026-07-03',
            'color' => 'gold', 'is_all_day' => true, 'client_name' => '옴니버',
            'request_data' => [
                'client_id' => $client->id, 'nickname' => '옴니버', 'platform' => 'SOOP',
                'career' => '경력', 'paid' => '결제완료', 'estimate_amount' => '1,500,000',
                'balance' => 'X', 'balance_amount' => '',
            ],
        ]);
        Schedule::create([
            'title' => '사내 회의', 'start_date' => '2026-07-10', 'end_date' => '2026-07-10',
            'color' => 'blue', 'is_all_day' => true,
        ]);

        $xlsx = $this->download('2026-07-01', '2026-07-15');

        $this->assertSame(1, $xlsx->getSheetCount());
        $sheet = $xlsx->getSheet(0);
        $this->assertSame('2026-07', $sheet->getTitle());

        $all = collect($sheet->toArray())->flatten()->filter()->implode('|');
        $this->assertStringContainsString('총 일정', $all);
        $this->assertStringContainsString('■ 유형 분포', $all);
        $this->assertStringContainsString('옴니버 방문 세팅', $all);
        $this->assertStringContainsString('SOOP', $all);
        $this->assertStringContainsString('경력', $all);
        $this->assertStringContainsString('유상', $all);
        $this->assertStringContainsString('1500000', $all);
        $this->assertStringContainsString('사내 회의', $all);
    }

    public function test_multi_month_range_splits_sheets_by_month(): void
    {
        Schedule::create(['title' => '7월 일정', 'start_date' => '2026-07-20', 'end_date' => '2026-07-20', 'color' => 'gold', 'is_all_day' => true]);
        Schedule::create(['title' => '8월 일정', 'start_date' => '2026-08-02', 'end_date' => '2026-08-02', 'color' => 'blue', 'is_all_day' => true]);

        $xlsx = $this->download('2026-07-01', '2026-08-31');

        $this->assertSame(2, $xlsx->getSheetCount());
        $this->assertSame('2026-07', $xlsx->getSheet(0)->getTitle());
        $this->assertSame('2026-08', $xlsx->getSheet(1)->getTitle());

        $july = collect($xlsx->getSheet(0)->toArray())->flatten()->filter()->implode('|');
        $aug = collect($xlsx->getSheet(1)->toArray())->flatten()->filter()->implode('|');
        $this->assertStringContainsString('7월 일정', $july);
        $this->assertStringNotContainsString('8월 일정', $july);
        $this->assertStringContainsString('8월 일정', $aug);
    }

    public function test_guest_is_blocked(): void
    {
        $guest = User::factory()->create(['role' => 'guest']);
        $this->actingAs($guest)->get('/marketing-report/schedules-export')->assertForbidden();
    }
}
