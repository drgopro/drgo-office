<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcelImportController extends Controller
{
    private const TEMPLATES = [
        'products' => [
            // 카테고리는 4차까지 가능 — 코드 또는 이름으로 입력 가능, 가장 하위 단계만 채워도 됨
            // SKU 비어있으면 2차 카테고리 코드 기반으로 자동 생성됨
            'headers' => ['SKU(비우면 자동)', '제품명', '카테고리1차(코드/이름)', '카테고리2차(코드/이름)', '카테고리3차(코드/이름)', '카테고리4차(코드/이름)', '매입가', '판매가', '재고수량', '안전재고', '메모', '검색태그(쉼표 구분)'],
            'required' => ['제품명'],
        ],
        'clients' => [
            'headers' => ['이름', '닉네임', '전화번호', '주소', '상세주소', '등급(일반/VIP/렌탈)', '성별(남/여/기타)', '소속', '플랫폼(쉼표:SOOP,유튜브,치지직,틱톡,팬더티비,기타)', '플랫폼-기타', '방송주제(쉼표:소통,게임,노래,먹방,야외,버추얼,코인,주식,기타,미정)', '방송주제-기타', '방송아이디', '최초등록일(YYYY-MM-DD)', '특이사항', '메모'],
            'required' => ['이름'],
        ],
        'projects' => [
            'headers' => ['프로젝트명', '의뢰자이름', '유형(방문/원격/AS)', '단계', '메모'],
            'required' => ['프로젝트명', '의뢰자이름', '유형(방문/원격/AS)'],
        ],
    ];

    public function template(string $type): StreamedResponse
    {
        if (! isset(self::TEMPLATES[$type])) {
            abort(404, '알 수 없는 유형입니다.');
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('데이터 입력');
        $headers = self::TEMPLATES[$type]['headers'];
        $required = self::TEMPLATES[$type]['required'];

        foreach ($headers as $col => $header) {
            $cell = $sheet->getCell([$col + 1, 1]);
            $cell->setValue($header);
            $cell->getStyle()->getFont()->setBold(true);
            if (in_array($header, $required)) {
                $cell->getStyle()->getFont()->getColor()->setRGB('CC0000');
            }
            $sheet->getColumnDimensionByColumn($col + 1)->setAutoSize(true);
        }

        // products 템플릿에는 카테고리 참조 시트 추가
        if ($type === 'products') {
            $this->addProductCategoriesSheet($spreadsheet);
            // 입력 시트 2행에 예시 안내
            $sheet->setCellValue('A2', '(비우면 자동: PCSET-001)');
            $sheet->setCellValue('B2', '게이밍 PC 본체');
            $sheet->setCellValue('C2', 'PC');
            $sheet->setCellValue('D2', 'PCSET');
            $sheet->setCellValue('E2', 'PCGAME');
            $sheet->setCellValue('F2', '');
            $sheet->getStyle('A2:J2')->getFont()->setItalic(true)->getColor()->setRGB('999999');
        }

        $filename = "drgo-{$type}-template.xlsx";

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * 두 번째 시트로 등록된 카테고리 트리(코드/이름/경로) 노출 — 사용자 참조용
     */
    private function addProductCategoriesSheet(Spreadsheet $spreadsheet): void
    {
        $catSheet = $spreadsheet->createSheet();
        $catSheet->setTitle('📋 카테고리 목록');

        $headers = ['1차 코드', '1차 이름', '2차 코드', '2차 이름', '3차 코드', '3차 이름', '4차 코드', '4차 이름', '전체 경로'];
        foreach ($headers as $col => $h) {
            $cell = $catSheet->getCell([$col + 1, 1]);
            $cell->setValue($h);
            $cell->getStyle()->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $cell->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2563EB');
            $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $catSheet->getColumnDimensionByColumn($col + 1)->setAutoSize(true);
        }

        // 모든 카테고리 트리 평탄화
        $roots = ProductCategory::whereNull('parent_id')->orderBy('sort_order')->orderBy('id')->get();
        $row = 2;
        foreach ($roots as $r1) {
            $children1 = ProductCategory::where('parent_id', $r1->id)->orderBy('sort_order')->orderBy('id')->get();
            if ($children1->isEmpty()) {
                $catSheet->setCellValue("A{$row}", $r1->code);
                $catSheet->setCellValue("B{$row}", $r1->name);
                $catSheet->setCellValue("G{$row}", $r1->name);
                $row++;

                continue;
            }
            foreach ($children1 as $r2) {
                $children2 = ProductCategory::where('parent_id', $r2->id)->orderBy('sort_order')->orderBy('id')->get();
                if ($children2->isEmpty()) {
                    $catSheet->setCellValue("A{$row}", $r1->code);
                    $catSheet->setCellValue("B{$row}", $r1->name);
                    $catSheet->setCellValue("C{$row}", $r2->code);
                    $catSheet->setCellValue("D{$row}", $r2->name);
                    $catSheet->setCellValue("G{$row}", "{$r1->name} > {$r2->name}");
                    $row++;

                    continue;
                }
                foreach ($children2 as $r3) {
                    $children3 = ProductCategory::where('parent_id', $r3->id)->orderBy('sort_order')->orderBy('id')->get();
                    if ($children3->isEmpty()) {
                        $catSheet->setCellValue("A{$row}", $r1->code);
                        $catSheet->setCellValue("B{$row}", $r1->name);
                        $catSheet->setCellValue("C{$row}", $r2->code);
                        $catSheet->setCellValue("D{$row}", $r2->name);
                        $catSheet->setCellValue("E{$row}", $r3->code);
                        $catSheet->setCellValue("F{$row}", $r3->name);
                        $catSheet->setCellValue("I{$row}", "{$r1->name} > {$r2->name} > {$r3->name}");
                        $row++;

                        continue;
                    }
                    foreach ($children3 as $r4) {
                        $catSheet->setCellValue("A{$row}", $r1->code);
                        $catSheet->setCellValue("B{$row}", $r1->name);
                        $catSheet->setCellValue("C{$row}", $r2->code);
                        $catSheet->setCellValue("D{$row}", $r2->name);
                        $catSheet->setCellValue("E{$row}", $r3->code);
                        $catSheet->setCellValue("F{$row}", $r3->name);
                        $catSheet->setCellValue("G{$row}", $r4->code);
                        $catSheet->setCellValue("H{$row}", $r4->name);
                        $catSheet->setCellValue("I{$row}", "{$r1->name} > {$r2->name} > {$r3->name} > {$r4->name}");
                        $row++;
                    }
                }
            }
        }

        // 줄무늬 배경 + 테두리
        if ($row > 2) {
            $range = 'A2:I'.($row - 1);
            $catSheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D0D7DE');
            for ($r = 2; $r < $row; $r++) {
                if ($r % 2 === 0) {
                    $catSheet->getStyle("A{$r}:I{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
                }
            }
        }

        // 안내 메시지
        $catSheet->setCellValue("A{$row}", '');
        $catSheet->setCellValue('A'.($row + 1), '※ 데이터 입력 시트의 카테고리1/2/3차 컬럼에 위 코드 또는 이름을 그대로 입력하세요.');
        $catSheet->getStyle('A'.($row + 1))->getFont()->setItalic(true)->getColor()->setRGB('666666');
        $catSheet->mergeCells('A'.($row + 1).':I'.($row + 1));
    }

    public function import(Request $request, string $type)
    {
        if (! isset(self::TEMPLATES[$type])) {
            return response()->json(['error' => '알 수 없는 유형입니다.'], 422);
        }

        $request->validate(['file' => 'required|file']);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['xlsx', 'xls', 'csv'])) {
            return response()->json(['error' => 'xlsx, xls, csv 파일만 지원합니다.'], 422);
        }

        try {
            if ($ext === 'csv') {
                $reader = IOFactory::createReader('Csv');
                $reader->setInputEncoding('UTF-8');
            } else {
                $reader = IOFactory::createReaderForFile($file->getRealPath());
            }
            $spreadsheet = $reader->load($file->getRealPath());
        } catch (\Exception $e) {
            return response()->json(['error' => '파일을 읽을 수 없습니다: '.$e->getMessage()], 422);
        }

        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        if (count($rows) < 2) {
            return response()->json(['error' => '데이터가 없습니다. 2행부터 데이터를 입력하세요.'], 422);
        }

        $headers = array_values(array_map('trim', $rows[1] ?? []));
        unset($rows[1]);

        $success = 0;
        $failed = 0;
        $errors = [];

        foreach ($rows as $rowNum => $row) {
            $values = array_values(array_map(fn ($v) => is_string($v) ? trim($v) : $v, $row));
            // 헤더 수와 값 수 맞추기
            while (count($values) < count($headers)) {
                $values[] = null;
            }
            $data = array_combine($headers, array_slice($values, 0, count($headers)));
            if (! array_filter($data)) {
                continue;
            }

            try {
                $result = match ($type) {
                    'products' => $this->importProduct($data, $request->boolean('auto_create_categories', true)),
                    'clients' => $this->importClient($data),
                    'projects' => $this->importProject($data),
                };
                if ($result) {
                    $success++;
                } else {
                    $failed++;
                    $errors[] = "{$rowNum}행: 필수값 누락";
                }
            } catch (\Exception $e) {
                $failed++;
                $errors[] = "{$rowNum}행: ".$e->getMessage();
            }
        }

        return response()->json([
            'message' => "완료: {$success}건 성공, {$failed}건 실패",
            'success' => $success,
            'failed' => $failed,
            'errors' => array_slice($errors, 0, 20),
        ]);
    }

    /**
     * 입력값($ref)이 영문/숫자 코드 같으면 code로, 한글 등이면 name으로 간주해 자동 생성.
     * code가 비어있는 카테고리는 만들 수 없으므로 ref가 name이면 code는 자동 생성(NAMEMD5 등).
     */
    private function autoCreateCategory(string $ref, ?int $parentId, int $depth): ProductCategory
    {
        $isCodeLike = (bool) preg_match('/^[A-Z0-9_-]+$/i', $ref);

        if ($isCodeLike) {
            $code = strtoupper($ref);
            $name = $ref; // 이름 정보가 따로 없으니 일단 코드와 동일하게
        } else {
            $name = $ref;
            // code 자동 생성: 영문/숫자만 추출 후 10자 제한, 충돌 시 suffix
            $base = strtoupper(preg_replace('/[^A-Z0-9]/i', '', Str::ascii($ref))) ?: 'CAT';
            $base = substr($base, 0, 7) ?: 'CAT';
            $code = $base;
            $i = 1;
            while (ProductCategory::where('code', $code)->exists()) {
                $code = $base.$i++;
                if (strlen($code) > 10) {
                    $code = substr($base, 0, 8).$i;
                }
            }
        }

        $sortOrder = (int) (ProductCategory::where('parent_id', $parentId)->max('sort_order') ?? 0) + 1;

        return ProductCategory::create([
            'parent_id' => $parentId,
            'name' => $name,
            'code' => substr($code, 0, 10),
            'depth' => $depth,
            'sort_order' => $sortOrder,
        ]);
    }

    /**
     * 제품 엑셀 컬럼 유연 인식 — 템플릿 헤더가 아니어도 '재고', '재고 수량', '수량',
     * '매입 가격' 등 키워드로 표준 키에 매핑한다 (표준 키가 이미 있으면 그대로 둠).
     */
    private function normalizeProductColumns(array $data): array
    {
        foreach ($data as $key => $value) {
            $k = mb_strtolower(preg_replace('/\s+/u', '', (string) $key));
            $canon = match (true) {
                str_contains($k, 'sku') => 'SKU',
                str_contains($k, '제품명') || str_contains($k, '상품명') || str_contains($k, '품명') => '제품명',
                str_contains($k, '매입') && ! str_contains($k, '처') => '매입가',
                str_contains($k, '판매') && ! str_contains($k, '처') && ! str_contains($k, '상태') => '판매가',
                str_contains($k, '안전') => '안전재고',
                str_contains($k, '재고') || $k === '수량' || str_contains($k, '현재고') => '재고수량',
                str_contains($k, '메모') || str_contains($k, '비고') => '메모',
                str_contains($k, '태그') || str_contains($k, '검색어') => '검색태그',
                str_contains($k, '카테고리1') || str_contains($k, '1차') => '카테고리1차(코드/이름)',
                str_contains($k, '카테고리2') || str_contains($k, '2차') => '카테고리2차(코드/이름)',
                str_contains($k, '카테고리3') || str_contains($k, '3차') => '카테고리3차(코드/이름)',
                str_contains($k, '카테고리4') || str_contains($k, '4차') => '카테고리4차(코드/이름)',
                default => null,
            };
            if ($canon !== null && ! array_key_exists($canon, $data)) {
                $data[$canon] = $value;
            }
        }

        return $data;
    }

    /** 셀 값 → 정수 (쉼표·'개'·'원' 등 비숫자 문자 허용). 빈 값은 null */
    private function cellToInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $digits = preg_replace('/[^\d-]/', '', (string) $value);

        return $digits === '' || $digits === '-' ? null : (int) $digits;
    }

    private function importProduct(array $data, bool $autoCreate = true): bool
    {
        $data = $this->normalizeProductColumns($data);
        $name = $data['제품명'] ?? null;
        $sku = trim((string) ($data['SKU(비우면 자동)'] ?? $data['SKU'] ?? ''));
        if (! $name) {
            return false;
        }

        // 카테고리 1차→2차→3차→4차 순서로 트리 따라가며 가장 깊은 매치를 사용
        // 각 컬럼은 'code' 또는 'name' 어느 쪽이든 허용. 없으면 자동 생성(autoCreate=true).
        $catRefs = [
            trim((string) ($data['카테고리1차(코드/이름)'] ?? '')),
            trim((string) ($data['카테고리2차(코드/이름)'] ?? '')),
            trim((string) ($data['카테고리3차(코드/이름)'] ?? '')),
            trim((string) ($data['카테고리4차(코드/이름)'] ?? '')),
        ];

        $categoryId = null;
        $categoryName = null;
        $parentId = null;
        foreach ($catRefs as $depthIdx => $ref) {
            if ($ref === '') {
                break;
            }
            $node = ProductCategory::where('parent_id', $parentId)
                ->where(function ($q) use ($ref) {
                    $q->where('code', $ref)->orWhere('name', $ref);
                })
                ->first();

            if (! $node) {
                if (! $autoCreate) {
                    throw new \Exception("카테고리 '{$ref}' 를 ".($parentId ? '하위에서' : '1차에서').' 찾을 수 없습니다.');
                }
                $node = $this->autoCreateCategory($ref, $parentId, $depthIdx + 1);
            }

            $categoryId = $node->id;
            $categoryName = $node->name;
            $parentId = $node->id;
        }

        // 재고수량 — 빈 칸이면 건드리지 않음 (0과 구분)
        $qty = $this->cellToInt($data['재고수량'] ?? null);
        if ($qty !== null) {
            $qty = max(0, $qty);
        }

        // 기존 제품 매칭: SKU 우선, SKU가 비어있으면 제품명 — 재업로드 시 중복 생성 대신 업데이트
        $existing = $sku !== ''
            ? Product::where('sku', $sku)->first()
            : Product::where('name', $name)->first();

        // SKU 비어있으면 2차 카테고리 코드 기반으로 자동 생성
        if (! $existing && $sku === '') {
            if (! $categoryId) {
                throw new \Exception('카테고리를 지정해야 SKU를 자동 생성할 수 있습니다.');
            }
            $cat = ProductCategory::find($categoryId);
            $sku = $this->autoGenerateSku($cat);
        }

        return DB::transaction(function () use ($existing, $sku, $name, $categoryId, $categoryName, $data, $qty) {
            if ($existing) {
                // 빈 칸은 기존 값 유지 — 채워진 값만 덮어씀
                $updates = ['name' => $name];
                if ($categoryId) {
                    $updates['category_id'] = $categoryId;
                    $updates['category'] = $categoryName;
                }
                foreach (['매입가' => 'purchase_price', '판매가' => 'sale_price', '안전재고' => 'safety_stock'] as $col => $field) {
                    $v = $this->cellToInt($data[$col] ?? null);
                    if ($v !== null) {
                        $updates[$field] = $v;
                    }
                }
                if (($data['메모'] ?? '') !== '' && $data['메모'] !== null) {
                    $updates['memo'] = $data['메모'];
                }
                if (($data['검색태그'] ?? '') !== '' && $data['검색태그'] !== null) {
                    $updates['search_tags'] = mb_substr((string) $data['검색태그'], 0, 300);
                }
                $existing->update($updates);

                if ($qty !== null) {
                    $this->setImportedStock($existing, $qty);
                }

                return true;
            }

            $product = Product::create([
                'sku' => $sku,
                'name' => $name,
                'category_id' => $categoryId,
                'category' => $categoryName,
                'purchase_price' => $this->cellToInt($data['매입가'] ?? null) ?? 0,
                'sale_price' => $this->cellToInt($data['판매가'] ?? null) ?? 0,
                'safety_stock' => $this->cellToInt($data['안전재고'] ?? null) ?? 0,
                'memo' => $data['메모'] ?? null,
                'search_tags' => ($data['검색태그'] ?? '') !== '' ? mb_substr((string) $data['검색태그'], 0, 300) : null,
                'is_active' => true,
            ]);

            Inventory::create([
                'product_id' => $product->id,
                'quantity' => $qty ?? 0,
                'last_updated_at' => now(),
            ]);

            if (($qty ?? 0) > 0) {
                StockMovement::create([
                    'product_id' => $product->id,
                    'movement_type' => 'in',
                    'quantity' => $qty,
                    'quantity_after' => $qty,
                    'user_id' => Auth::id(),
                    'memo' => '엑셀 임포트 초기 재고',
                ]);
            }

            return true;
        });
    }

    /**
     * 임포트 재고 반영 — 기존 수량과 다르면 조정(adjust) 이력을 남기고 갱신.
     */
    private function setImportedStock(Product $product, int $qty): void
    {
        if ($product->is_bundle) {
            return; // 세트 상품은 자체 재고 없음 — 구성품에서 관리
        }

        $inventory = Inventory::firstOrCreate(
            ['product_id' => $product->id],
            ['quantity' => 0, 'last_updated_at' => now()]
        );

        if ($inventory->quantity === $qty) {
            return;
        }

        StockMovement::create([
            'product_id' => $product->id,
            'movement_type' => 'adjust',
            'quantity' => abs($qty - $inventory->quantity),
            'quantity_after' => $qty,
            'user_id' => Auth::id(),
            'memo' => '엑셀 임포트 재고 반영',
        ]);

        $inventory->update(['quantity' => $qty, 'last_updated_at' => now()]);
    }

    /**
     * 2차 카테고리 코드를 베이스로 한 자동 SKU 생성 (예: PCSET-001)
     */
    private function autoGenerateSku(ProductCategory $category): string
    {
        $prefix = $category->getSkuBaseCode();
        $last = Product::withTrashed()
            ->where('sku', 'like', "{$prefix}-%")
            ->where('sku', 'regexp', "^{$prefix}-[0-9]+$")
            ->orderByRaw("CAST(SUBSTRING_INDEX(sku, '-', -1) AS UNSIGNED) DESC")
            ->first();
        $next = $last ? ((int) last(explode('-', $last->sku)) + 1) : 1;

        return $prefix.'-'.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    private function importClient(array $data): bool
    {
        $name = trim((string) ($data['이름'] ?? ''));
        $nickname = trim((string) ($data['닉네임'] ?? ''));
        // 닉네임이 비어있으면 이름으로 대체 (둘 다 비어있으면 행 건너뜀)
        if ($nickname === '') {
            $nickname = $name;
        }
        if ($nickname === '') {
            return false;
        }

        $gradeMap = ['일반' => 'normal', 'VIP' => 'vip', 'vip' => 'vip', '렌탈' => 'rental'];
        $genderMap = ['남' => 'male', '남성' => 'male', '여' => 'female', '여성' => 'female', '기타' => 'other'];

        $platformOptions = ['SOOP', '유튜브', '치지직', '틱톡', '팬더티비', '기타'];
        $topicOptions = ['소통', '게임', '노래', '먹방', '야외', '버추얼', '코인', '주식', '기타', '미정'];

        $platformsRaw = $data['플랫폼(쉼표:SOOP,유튜브,치지직,틱톡,팬더티비,기타)'] ?? null;
        $platforms = $platformsRaw
            ? array_values(array_filter(array_map('trim', preg_split('/[,\/]+/', (string) $platformsRaw)), fn ($v) => in_array($v, $platformOptions, true)))
            : [];

        $topicsRaw = $data['방송주제(쉼표:소통,게임,노래,먹방,야외,버추얼,코인,주식,기타,미정)'] ?? null;
        $contentTypes = $topicsRaw
            ? array_values(array_filter(array_map('trim', preg_split('/[,\/]+/', (string) $topicsRaw)), fn ($v) => in_array($v, $topicOptions, true)))
            : [];

        $registeredAt = $this->parseDate($data['최초등록일(YYYY-MM-DD)'] ?? null);

        $client = new Client([
            'nickname' => $nickname,
            'name' => $name !== '' ? $name : null,
            'phone' => $data['전화번호'] ?? null,
            'address' => $data['주소'] ?? null,
            'address_detail' => $data['상세주소'] ?? null,
            'grade' => $gradeMap[$data['등급(일반/VIP/렌탈)'] ?? ''] ?? 'normal',
            'gender' => $genderMap[$data['성별(남/여/기타)'] ?? ''] ?? null,
            'affiliation' => $data['소속'] ?? null,
            'platforms' => $platforms ?: null,
            'platform_etc' => in_array('기타', $platforms, true) ? ($data['플랫폼-기타'] ?? null) : null,
            'content_types' => $contentTypes ?: null,
            'topic_etc' => in_array('기타', $contentTypes, true) ? ($data['방송주제-기타'] ?? null) : null,
            'broadcast_id' => $data['방송아이디'] ?? null,
            'important_memo' => $data['특이사항'] ?? null,
            'memo' => $data['메모'] ?? null,
            'assigned_user_id' => Auth::id(),
            'status' => 'active',
        ]);
        $client->save();

        // 최초 등록일 강제 지정 (수동 입력값 우선)
        if ($registeredAt) {
            $client->timestamps = false;
            $client->created_at = $registeredAt;
            $client->save();
            $client->timestamps = true;
        }

        return true;
    }

    private function parseDate(mixed $value): ?\DateTimeInterface
    {
        if (! $value) {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value;
        }
        if (is_numeric($value)) {
            try {
                return Date::excelToDateTimeObject((float) $value);
            } catch (\Throwable) {
                return null;
            }
        }
        try {
            return new \DateTimeImmutable((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function importProject(array $data): bool
    {
        $name = $data['프로젝트명'] ?? null;
        $clientName = $data['의뢰자이름'] ?? null;
        $typeStr = $data['유형(방문/원격/AS)'] ?? null;
        if (! $name || ! $clientName || ! $typeStr) {
            return false;
        }

        $typeMap = ['방문' => 'visit', '방문세팅' => 'visit', '원격' => 'remote', '원격세팅' => 'remote', 'AS' => 'as', 'as' => 'as'];
        $projectType = $typeMap[$typeStr] ?? null;
        if (! $projectType) {
            throw new \Exception("유형 '{$typeStr}'은(는) 올바르지 않습니다 (방문/원격/AS)");
        }

        $client = Client::where('name', $clientName)->orWhere('nickname', $clientName)->first();
        if (! $client) {
            throw new \Exception("의뢰자 '{$clientName}'을(를) 찾을 수 없습니다");
        }

        $stageMap = ['상담' => 'consulting', '장비파악' => 'equipment', '일정제안' => 'proposal', '견적/계약' => 'estimate', '결제/예약' => 'payment', '세팅' => 'visit', 'AS' => 'as', '완료' => 'done', '취소' => 'cancelled'];

        Project::create([
            'client_id' => $client->id,
            'name' => $name,
            'project_type' => $projectType,
            'stage' => $stageMap[$data['단계'] ?? ''] ?? 'consulting',
            'status' => 'active',
            'memo' => $data['메모'] ?? null,
            'assigned_user_id' => Auth::id(),
        ]);

        return true;
    }
}
