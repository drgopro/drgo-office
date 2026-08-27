<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/** 엑셀 견적 가져오기 — B=대분류/C=중분류/D=제품/E=단가/G=수량/I=금액/J=비고 파싱 + 제품 매칭 */
class EstimateExcelImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'master']);
    }

    /** 기존 엑셀 견적 양식과 동일한 구조의 픽스처 생성 */
    private function makeFixture(): UploadedFile
    {
        $ss = new Spreadsheet;
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('견적1');
        $rows = [
            // [B, C, D, E, G, I, J]
            2 => ['', '', 'XL스튜디오 HDMI 변경시', '', '', '', ''],           // 제목 행 (가격 없음)
            3 => ['', '', '', '', '', 'Amount', 'Remark'],                    // 헤더 행 — 스킵
            4 => ['방송장비 연결용', '지하2층 스위치허브', 'EFM ipTIME 스위치허브', '', 1, 96100, ''], // 단가 없음 → 금액/수량
            5 => ['', '', '소계', '', '', 96100, ''],                          // 소계 — 스킵
            6 => ['미러리스 PTZ스테이션', '맥PC', 'Macbook Neo', 1566000, 1, 1566000, ''],
            7 => ['', '멀티미디어 허브', '벨킨 USB C타입 허브', 128500, 2, 257000, '정면 카메라 포함'],
            8 => ['', '', '아트뮤 PD 충전기 GB101', 40000, 1, 40000, ''],      // 중분류 승계
            9 => ['', '', '합계', '', '', 1959100, '부가세포함'],               // 합계 — 스킵
        ];
        foreach ($rows as $r => [$b, $c, $d, $e, $g, $i, $j]) {
            $sheet->setCellValue("B{$r}", $b);
            $sheet->setCellValue("C{$r}", $c);
            $sheet->setCellValue("D{$r}", $d);
            if ($e !== '') {
                $sheet->setCellValue("E{$r}", $e);
            }
            if ($g !== '') {
                $sheet->setCellValue("G{$r}", $g);
            }
            if ($i !== '') {
                $sheet->setCellValue("I{$r}", $i);
            }
            $sheet->setCellValue("J{$r}", $j);
        }

        $path = tempnam(sys_get_temp_dir(), 'est').'.xlsx';
        (new Xlsx($ss))->save($path);

        return new UploadedFile($path, '견적.xlsx', null, null, true);
    }

    public function test_parse_excel_extracts_items_and_matches_products(): void
    {
        // 'Macbook Neo'는 제품 관리에 존재(공백 무시 매칭) — 판매가는 현재가와 다르게 두어 price_differs 확인
        $mac = Product::create([
            'sku' => 'PC-001', 'name' => 'Macbook  Neo', 'category' => 'PC',
            'purchase_price' => 1400000, 'sale_price' => 1700000, 'is_active' => true, 'show_in_estimate' => true,
        ]);

        $res = $this->actingAs($this->admin)
            ->post('/api/estimates/parse-excel', ['file' => $this->makeFixture()])
            ->assertOk()->json();

        $this->assertSame('XL스튜디오 HDMI 변경시', $res['title']);
        $this->assertSame(4, $res['total']);
        $this->assertSame(1, $res['matched']);

        [$hub, $macRow, $belkin, $atmu] = $res['items'];
        // 단가 없는 행 — 금액/수량으로 단가 역산
        $this->assertSame(['방송장비 연결용', '지하2층 스위치허브', 96100, 1, 96100], [
            $hub['category'], $hub['mid_category'], $hub['unit_price'], $hub['qty'], $hub['amount'],
        ]);
        $this->assertNull($hub['product_id']);
        // 제품 매칭 + 엑셀 가격과 현재 판매가 차이 감지
        $this->assertSame($mac->id, $macRow['product_id']);
        $this->assertTrue($macRow['price_differs']);
        $this->assertSame(1566000, $macRow['unit_price']);
        // 비고 + 수량 2
        $this->assertSame(['멀티미디어 허브', 2, 257000, '정면 카메라 포함'], [
            $belkin['mid_category'], $belkin['qty'], $belkin['amount'], $belkin['remark'],
        ]);
        // 중분류 승계 (C열 빈 행)
        $this->assertSame('멀티미디어 허브', $atmu['mid_category']);
        $this->assertSame('미러리스 PTZ스테이션', $atmu['category']);
    }

    public function test_saved_items_keep_remark_and_fixed_price(): void
    {
        $mac = Product::create([
            'sku' => 'PC-001', 'name' => 'Macbook Neo', 'category' => 'PC',
            'purchase_price' => 1400000, 'sale_price' => 1700000, 'is_active' => true, 'show_in_estimate' => true,
        ]);
        $estimate = Estimate::create(['status' => 'created', 'product_items' => [], 'service_items' => [], 'total_amount' => 0, 'created_by' => $this->admin->id]);

        $item = [
            'product_id' => $mac->id, 'sku' => 'PC-001', 'category' => '미러리스 PTZ스테이션', 'category_root' => '미러리스 PTZ스테이션',
            'name' => 'Macbook Neo', 'purchase_price' => 1400000, 'sale_price' => 1566000, 'qty' => 1, 'subtotal' => 1566000,
            'mid_category' => '맥PC', 'remark' => '정면 카메라 포함', 'price_fixed' => true, 'manual' => false,
        ];
        $this->actingAs($this->admin)->patchJson("/api/estimates/{$estimate->id}", [
            'product_items' => [$item], 'service_items' => [], 'status' => 'created',
        ])->assertOk();

        $saved = $estimate->fresh()->product_items[0];
        $this->assertSame('정면 카메라 포함', $saved['remark']);
        $this->assertSame('맥PC', $saved['mid_category']);
        $this->assertTrue((bool) $saved['price_fixed']);

        // price_fixed 항목은 열람 시 현재 판매가(1,700,000)로 덮이지 않는다
        $estimate->fresh()->syncSnapshotPrices();
        $this->assertSame(1566000, (int) $estimate->fresh()->product_items[0]['sale_price']);

        // 공개 견적서에 중분류·비고 표시
        $this->get($estimate->fresh()->publicUrl())
            ->assertOk()->assertSee('정면 카메라 포함')->assertSee('맥PC');
    }

    public function test_parse_excel_rejects_non_excel_file(): void
    {
        $this->actingAs($this->admin)
            ->post('/api/estimates/parse-excel', ['file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertUnprocessable();
    }
}
