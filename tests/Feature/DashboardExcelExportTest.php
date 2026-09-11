<?php

namespace Tests\Feature;

use App\Models\Assignee;
use App\Models\Client;
use App\Models\Estimate;
use App\Models\Project;
use App\Models\RevenueEntry;
use App\Models\Schedule;
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

    public function test_export_includes_margin_sheet_with_purchase_profit(): void
    {
        // 마진 분석 시트 — 결제된 견적서별 판매/매입 차익 (실구매액 우선, 대체 제외, 환불 차감)
        $user = User::factory()->create(['role' => 'admin']);
        $estimate = Estimate::create([
            'status' => 'created', 'client_nickname' => '고블린',
            'product_items' => [
                // 실구매액 90,000 기록 — 매입가×수량(80,000)보다 우선
                ['name' => '카메라', 'sale_price' => 150000, 'qty' => 1, 'subtotal' => 150000,
                    'purchase_price' => 80000, 'purchase_amount' => 90000, 'ordered' => true],
                // 실구매 미기록 — 매입가 20,000×2 = 40,000 참고치
                ['name' => '조명', 'sale_price' => 50000, 'qty' => 2, 'subtotal' => 100000, 'purchase_price' => 20000],
                // 스냅샷 서비스 항목 — 매입 0, 전액 순익
                ['name' => '세팅비', 'sale_price' => 50000, 'qty' => 1, 'subtotal' => 50000, 'is_service' => true],
                // 대체 항목 — 완전 제외
                ['name' => '단종 캡처보드', 'sale_price' => 70000, 'qty' => 1, 'subtotal' => 70000,
                    'purchase_price' => 30000, 'replaced' => true],
            ],
            'service_items' => [['name' => '출장비', 'amount' => 30000]],
            'product_total' => 300000, 'service_total' => 30000, 'total_amount' => 330000,
            'validity_days' => 3, 'created_by' => $user->id,
        ]);
        $estimate->update(['status' => 'paid']); // paid_at 스탬프

        $from = now()->startOfMonth()->format('Y-m-d');
        $to = now()->format('Y-m-d');
        $res = $this->actingAs($user)->get("/api/dashboard-export/excel?from={$from}&to={$to}")->assertOk();

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($tmp, $res->streamedContent());
        $sheet = IOFactory::load($tmp)->getSheetByName('마진 분석');
        unlink($tmp);

        $this->assertNotNull($sheet, "'마진 분석' 시트가 없습니다");
        $this->assertSame('고블린', $sheet->getCell('C2')->getValue());
        $this->assertSame(330000, (int) $sheet->getCell('D2')->getValue()); // 총 판매액 (대체 제외, 서비스 포함)
        $this->assertSame(250000, (int) $sheet->getCell('E2')->getValue()); // 제품 판매액 (카메라+조명)
        $this->assertSame(130000, (int) $sheet->getCell('F2')->getValue()); // 매입액 90,000 + 40,000
        $this->assertSame(120000, (int) $sheet->getCell('G2')->getValue()); // 제품 마진
        $this->assertSame(80000, (int) $sheet->getCell('H2')->getValue());  // 서비스 매출 (50,000+30,000)
        $this->assertSame(200000, (int) $sheet->getCell('I2')->getValue()); // 순익
        $this->assertEqualsWithDelta(60.6, (float) $sheet->getCell('J2')->getValue(), 0.05); // 마진율
        $this->assertSame('합계', $sheet->getCell('A3')->getValue());
        $this->assertSame(200000, (int) $sheet->getCell('I3')->getValue());
        // 견적서 원문 링크 — 오피스 빌더 URL 하이퍼링크
        $this->assertSame('견적서 원문', $sheet->getCell('K1')->getValue());
        $this->assertStringContainsString('견적서 #', (string) $sheet->getCell('K2')->getValue());
        $this->assertStringContainsString("/estimates/{$estimate->id}/edit", $sheet->getCell('K2')->getHyperlink()->getUrl());
        // 하단 주석이 A~K 병합 — 결제일(A) 열이 주석 길이만큼 넓어지는 회귀 방지
        $this->assertContains('A5:K5', array_keys($sheet->getMergeCells()));
    }

    public function test_export_includes_work_log_sheet(): void
    {
        // 작업 일지 시트 — 요일/날짜/의뢰자/플랫폼/경력/작업 방식/유형/작업자/수량 + 우측 선택지 목록 + 자동필터
        $user = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal', 'client_type' => 'enterprise']);
        $assignee = Assignee::create(['name' => '김직원', 'is_active' => true]);

        $schedule = Schedule::create([
            'title' => '캠 풀세팅', 'color' => 'gold',
            'start_date' => now()->format('Y-m-d'), 'end_date' => now()->format('Y-m-d'), 'start_time' => '14:00',
            'client_name' => '고블린',
            'request_data' => ['client_id' => $client->id, 'platform' => '치지직', 'career' => '초보'],
            'created_by' => $user->id,
        ]);
        $schedule->assignees()->attach($assignee->id, ['sort_order' => 0]);
        // 사내업무 일정 — 작업 일지 제외 대상
        Schedule::create([
            'title' => '재고 정리', 'color' => 'blue',
            'start_date' => now()->format('Y-m-d'), 'end_date' => now()->format('Y-m-d'), 'created_by' => $user->id,
        ]);

        $from = now()->startOfMonth()->format('Y-m-d');
        $to = now()->format('Y-m-d');
        $res = $this->actingAs($user)->get("/api/dashboard-export/excel?from={$from}&to={$to}")->assertOk();

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($tmp, $res->streamedContent());
        $sheet = IOFactory::load($tmp)->getSheetByName('작업 일지');
        unlink($tmp);

        $this->assertNotNull($sheet, "'작업 일지' 시트가 없습니다");
        $dows = ['일', '월', '화', '수', '목', '금', '토'];
        $this->assertSame($dows[now()->dayOfWeek], $sheet->getCell('A2')->getValue());
        $this->assertSame(now()->format('n/j'), $sheet->getCell('B2')->getValue());
        $this->assertSame('고블린', $sheet->getCell('C2')->getValue());
        $this->assertSame('치지직', $sheet->getCell('D2')->getValue());
        $this->assertSame('초보', $sheet->getCell('E2')->getValue());
        $this->assertSame('방문', $sheet->getCell('F2')->getValue()); // 방문의뢰 카테고리 → 방문
        $this->assertSame('엔터', $sheet->getCell('G2')->getValue()); // 의뢰자 client_type 폴백
        $this->assertSame('김직원', $sheet->getCell('H2')->getValue());
        $this->assertSame(1, (int) $sheet->getCell('I2')->getValue());
        $this->assertNull($sheet->getCell('A3')->getValue()); // 사내업무 제외

        // 우측 선택지 목록 + 헤더 자동필터
        $this->assertSame('플랫폼', $sheet->getCell('L1')->getValue());
        $this->assertSame('SOOP', $sheet->getCell('L2')->getValue());
        $this->assertSame('경력', $sheet->getCell('M1')->getValue());
        $this->assertSame('작업 방식', $sheet->getCell('N1')->getValue());
        $this->assertSame('수정원격', $sheet->getCell('N13')->getValue());
        $this->assertSame('의뢰자 유형', $sheet->getCell('O1')->getValue());
        $this->assertSame('기업', $sheet->getCell('O5')->getValue());
        $this->assertSame('A1:I2', $sheet->getAutoFilter()->getRange());
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
