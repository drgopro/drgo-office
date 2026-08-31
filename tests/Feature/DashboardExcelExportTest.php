<?php

namespace Tests\Feature;

use App\Models\RevenueEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_stats_page_excel_links_use_download_notice_helper(): void
    {
        // 엑셀 링크가 '파일 다운로드 준비 중' 안내를 띄우는 drgoDownload 헬퍼로 연결되는지
        $user = User::factory()->create(['role' => 'admin']);
        $res = $this->actingAs($user)->get('/marketing-report')->assertOk();
        $this->assertSame(3, substr_count($res->getContent(), 'drgoDownload(this.href, this)'));
        $res->assertSee('파일 다운로드 준비 중');
    }
}
