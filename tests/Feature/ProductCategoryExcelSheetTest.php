<?php

namespace Tests\Feature;

use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * 제품 가져오기 템플릿의 카테고리 목록 시트 — 트리를 한 번에 읽어 평탄화한다.
 */
class ProductCategoryExcelSheetTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    /** @return array{rows: array<int, string>, queries: int} 카테고리 시트의 전체 경로(G~I열 중 채워진 값) 목록과 카테고리 조회 수 */
    private function downloadCategorySheet(): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $content = $this->actingAs($this->admin)->get('/api/import/template/products')->assertOk()->streamedContent();
        $queries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], '"product_categories"'))->count();
        DB::disableQueryLog();

        $file = tempnam(storage_path('framework/testing'), 'xlsx');
        file_put_contents($file, $content);
        $sheet = IOFactory::load($file)->getSheetByName('📋 카테고리 목록');
        unlink($file);

        $rows = [];
        for ($r = 2; $r <= $sheet->getHighestRow(); $r++) {
            $path = (string) ($sheet->getCell("I{$r}")->getValue() ?: $sheet->getCell("G{$r}")->getValue());
            if ($path !== '') {
                $rows[] = $path;
            }
        }

        return ['rows' => $rows, 'queries' => $queries];
    }

    public function test_category_tree_is_flattened_in_sort_order_with_single_query(): void
    {
        $part = ProductCategory::create(['name' => '부품', 'code' => 'PART', 'depth' => 1, 'sort_order' => 2]);
        $pc = ProductCategory::create(['name' => 'PC', 'code' => 'PC', 'depth' => 1, 'sort_order' => 1]);
        $cpu = ProductCategory::create(['name' => 'CPU', 'code' => 'CPU', 'parent_id' => $part->id, 'depth' => 2, 'sort_order' => 1]);
        $intel = ProductCategory::create(['name' => '인텔', 'code' => 'INTEL', 'parent_id' => $cpu->id, 'depth' => 3, 'sort_order' => 1]);
        ProductCategory::create(['name' => '14세대', 'code' => 'I14', 'parent_id' => $intel->id, 'depth' => 4, 'sort_order' => 1]);
        ProductCategory::create(['name' => '게이밍', 'code' => 'PCGAME', 'parent_id' => $pc->id, 'depth' => 2, 'sort_order' => 1]);

        $result = $this->downloadCategorySheet();

        $this->assertSame(['PC > 게이밍', '부품 > CPU > 인텔 > 14세대'], $result['rows']);
        $this->assertSame(1, $result['queries']);
    }
}
