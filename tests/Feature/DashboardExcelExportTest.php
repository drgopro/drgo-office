<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\RevenueEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/** 통계(대시보드) 엑셀 출력 — 매출 원장 분기 포함 500 회귀 방지 */
class DashboardExcelExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_excel_downloads_with_revenue_ledger(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        RevenueEntry::create([
            'kind' => 'estimate_paid', 'recognized_on' => now()->format('Y-m-d'),
            'product_amount' => 300000, 'service_amount' => 200000, 'amount' => 500000,
        ]);

        $from = now()->startOfMonth()->format('Y-m-d');
        $to = now()->format('Y-m-d');
        $res = $this->actingAs($user)->get("/api/dashboard-export/excel?from={$from}&to={$to}");

        $res->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $res->headers->get('Content-Type'));

        // 스트림 본문이 실제 xlsx(zip 시그니처 PK)로 시작하는지
        $body = $res->streamedContent();
        $this->assertStringStartsWith('PK', $body);
    }

    public function test_export_includes_cancellation_sheet(): void
    {
        // 피드백: 취소 발생 시점 추적 — 생성일/의뢰자/취소일/사유를 시트로 제공
        $user = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['name' => '홍길동', 'grade' => 'normal']);
        $p = Project::create([
            'client_id' => $client->id, 'name' => '캠 세팅', 'project_type' => 'visit',
            'stage' => 'cancelled', 'cancel_reason' => '의뢰자 사정으로 취소',
            'cancel_detail' => '예산 문제', 'cancelled_at' => now(),
        ]);
        $p->forceFill(['created_at' => now()->subDays(7)])->save();
        // 기간 밖 취소 건 — 시트에 미포함
        Project::create([
            'client_id' => $client->id, 'name' => '옛 취소 건', 'project_type' => 'visit',
            'stage' => 'cancelled', 'cancel_reason' => '일정이 맞지 않음', 'cancelled_at' => now()->subMonths(3),
        ]);

        $from = now()->subDays(30)->format('Y-m-d');
        $to = now()->format('Y-m-d');
        $res = $this->actingAs($user)->get("/api/dashboard-export/excel?from={$from}&to={$to}")->assertOk();

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($tmp, $res->streamedContent());
        $sheet = IOFactory::load($tmp)->getSheetByName('취소 내역');
        @unlink($tmp);

        $this->assertNotNull($sheet, "'취소 내역' 시트가 없습니다");
        $this->assertSame('프로젝트 생성일', $sheet->getCell('A1')->getValue());
        $this->assertSame(now()->subDays(7)->format('Y.m.d'), $sheet->getCell('A2')->getValue());
        $this->assertSame('홍길동', $sheet->getCell('B2')->getValue());
        $this->assertSame('캠 세팅', $sheet->getCell('C2')->getValue());
        $this->assertSame(now()->format('Y.m.d'), $sheet->getCell('D2')->getValue());
        $this->assertSame('의뢰자 사정으로 취소', $sheet->getCell('E2')->getValue());
        $this->assertSame('예산 문제', $sheet->getCell('F2')->getValue());
        $this->assertNull($sheet->getCell('A3')->getValue()); // 기간 밖 취소 건 제외
    }

    public function test_stats_page_excel_links_use_download_notice_helper(): void
    {
        // 엑셀 링크가 '파일 다운로드 준비 중' 안내를 띄우는 drgoDownload 헬퍼로 연결되는지
        $user = User::factory()->create(['role' => 'admin']);
        $res = $this->actingAs($user)->get('/marketing-report')->assertOk();
        $this->assertSame(3, substr_count($res->getContent(), 'drgoDownload(this.href, this)'));
        $res->assertSee('파일 다운로드 준비 중');
    }
}
