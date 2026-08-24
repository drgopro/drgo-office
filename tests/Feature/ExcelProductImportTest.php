<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ExcelProductImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADERS = ['SKU(비우면 자동)', '제품명', '카테고리1차(코드/이름)', '카테고리2차(코드/이름)', '카테고리3차(코드/이름)', '카테고리4차(코드/이름)', '매입가', '판매가', '재고수량', '안전재고', '메모'];

    /** @param array<int, array<int, mixed>> $rows */
    private function makeXlsx(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(self::HEADERS, null, 'A1');
        $sheet->fromArray($rows, null, 'A2');

        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'products.xlsx', null, null, true);
    }

    private function master(): User
    {
        return User::factory()->create(['role' => 'master']);
    }

    public function test_import_creates_product_with_stock_and_movement(): void
    {
        ProductCategory::create(['name' => '부품', 'code' => 'PART', 'depth' => 1, 'sort_order' => 1]);

        $file = $this->makeXlsx([
            ['PART-100', '테스트 CPU', '부품', '', '', '', 900400, 990000, 5, 1, '비고'],
        ]);

        $this->actingAs($this->master())
            ->post('/api/import/products', ['file' => $file])
            ->assertOk();

        $product = Product::where('sku', 'PART-100')->first();
        $this->assertNotNull($product);
        $this->assertSame(5, $product->inventory->quantity);

        $movement = StockMovement::where('product_id', $product->id)->first();
        $this->assertNotNull($movement);
        $this->assertSame('in', $movement->movement_type);
        $this->assertSame(5, $movement->quantity_after);
    }

    public function test_import_without_stock_column_value_creates_zero_inventory(): void
    {
        ProductCategory::create(['name' => '부품', 'code' => 'PART', 'depth' => 1, 'sort_order' => 1]);

        $file = $this->makeXlsx([
            ['PART-101', '재고 없는 제품', '부품', '', '', '', 1000, 2000, '', 0, ''],
        ]);

        $this->actingAs($this->master())
            ->post('/api/import/products', ['file' => $file])
            ->assertOk();

        $product = Product::where('sku', 'PART-101')->first();
        $this->assertNotNull($product->inventory);
        $this->assertSame(0, $product->inventory->quantity);
        $this->assertSame(0, StockMovement::where('product_id', $product->id)->count());
    }

    public function test_reimport_updates_existing_product_by_sku_and_adjusts_stock(): void
    {
        $cat = ProductCategory::create(['name' => '부품', 'code' => 'PART', 'depth' => 1, 'sort_order' => 1]);
        $product = Product::create([
            'sku' => 'PART-200', 'name' => '기존 제품', 'category' => '부품', 'category_id' => $cat->id,
            'purchase_price' => 1000, 'sale_price' => 2000, 'safety_stock' => 0,
            'is_active' => true, 'show_in_estimate' => false,
        ]);
        Inventory::create(['product_id' => $product->id, 'quantity' => 2, 'last_updated_at' => now()]);

        $file = $this->makeXlsx([
            ['PART-200', '기존 제품', '부품', '', '', '', '', 2500, 7, '', ''],
        ]);

        $this->actingAs($this->master())
            ->post('/api/import/products', ['file' => $file])
            ->assertOk();

        // 중복 생성 없이 업데이트
        $this->assertSame(1, Product::where('sku', 'PART-200')->count());

        $fresh = $product->fresh();
        $this->assertSame(2500, $fresh->sale_price);
        $this->assertSame(1000, $fresh->purchase_price); // 빈 칸은 기존 값 유지
        $this->assertSame(7, $fresh->inventory->quantity);

        $movement = StockMovement::where('product_id', $product->id)->latest('id')->first();
        $this->assertSame('adjust', $movement->movement_type);
        $this->assertSame(7, $movement->quantity_after);
    }

    public function test_import_recognizes_loose_headers_and_comma_numbers(): void
    {
        // 템플릿이 아닌 사용자 엑셀표 — '재고 수량', '매입 가격' 같은 헤더와 쉼표 숫자도 인식
        $cat = ProductCategory::create(['name' => '부품', 'code' => 'PART', 'depth' => 1, 'sort_order' => 1]);
        $existing = Product::create([
            'sku' => 'PART-400', 'name' => '느슨한 헤더 제품', 'category' => '부품', 'category_id' => $cat->id,
            'purchase_price' => 1000, 'sale_price' => 2000, 'safety_stock' => 0,
            'is_active' => true, 'show_in_estimate' => false,
        ]);
        Inventory::create(['product_id' => $existing->id, 'quantity' => 1, 'last_updated_at' => now()]);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['상품명', '카테고리 1차', '매입 가격', '판매가(원)', '재고 수량'], null, 'A1');
        $sheet->fromArray([
            ['느슨한 헤더 제품', '부품', '1,500', '3,000', '12'],
            ['새 헤더 제품', '부품', '9,900', '', '4개'],
        ], null, 'A2');
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        $this->actingAs($this->master())
            ->post('/api/import/products', ['file' => new UploadedFile($path, 'my-products.xlsx', null, null, true)])
            ->assertOk()->assertJsonPath('success', 2);

        $fresh = $existing->fresh();
        $this->assertSame(12, $fresh->inventory->quantity); // 재고 수량 반영
        $this->assertSame(1500, $fresh->purchase_price);
        $this->assertSame(3000, $fresh->sale_price);

        $new = Product::where('name', '새 헤더 제품')->first();
        $this->assertNotNull($new);
        $this->assertSame(4, $new->inventory->quantity);
        $this->assertSame(9900, $new->purchase_price);
    }

    public function test_reimport_matches_by_name_when_sku_blank(): void
    {
        $cat = ProductCategory::create(['name' => '부품', 'code' => 'PART', 'depth' => 1, 'sort_order' => 1]);
        $product = Product::create([
            'sku' => 'PART-300', 'name' => '이름 매칭 제품', 'category' => '부품', 'category_id' => $cat->id,
            'purchase_price' => 1000, 'sale_price' => 2000, 'safety_stock' => 0,
            'is_active' => true, 'show_in_estimate' => false,
        ]);

        $file = $this->makeXlsx([
            ['', '이름 매칭 제품', '부품', '', '', '', '', '', 3, '', ''],
        ]);

        $this->actingAs($this->master())
            ->post('/api/import/products', ['file' => $file])
            ->assertOk();

        // SKU가 비어있어도 제품명으로 기존 제품을 찾아 재고만 반영 (중복 생성 없음)
        $this->assertSame(1, Product::where('name', '이름 매칭 제품')->count());
        $this->assertSame(3, $product->fresh()->inventory->quantity);
    }
}
