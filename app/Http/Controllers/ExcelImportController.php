<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            // 카테고리는 3차까지 가능 — 코드 또는 이름으로 입력 가능, 가장 하위 단계만 채워도 됨
            'headers' => ['SKU', '제품명', '카테고리1차(코드/이름)', '카테고리2차(코드/이름)', '카테고리3차(코드/이름)', '매입가', '판매가', '안전재고', '메모'],
            'required' => ['SKU', '제품명'],
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
            $sheet->setCellValue('A2', '예시: SKU-001');
            $sheet->setCellValue('B2', '게이밍 PC');
            $sheet->setCellValue('C2', 'PC');
            $sheet->setCellValue('D2', 'PCSET');
            $sheet->setCellValue('E2', 'PCGAME');
            $sheet->getStyle('A2:I2')->getFont()->setItalic(true)->getColor()->setRGB('999999');
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

        $headers = ['1차 코드', '1차 이름', '2차 코드', '2차 이름', '3차 코드', '3차 이름', '전체 경로'];
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
                    $catSheet->setCellValue("A{$row}", $r1->code);
                    $catSheet->setCellValue("B{$row}", $r1->name);
                    $catSheet->setCellValue("C{$row}", $r2->code);
                    $catSheet->setCellValue("D{$row}", $r2->name);
                    $catSheet->setCellValue("E{$row}", $r3->code);
                    $catSheet->setCellValue("F{$row}", $r3->name);
                    $catSheet->setCellValue("G{$row}", "{$r1->name} > {$r2->name} > {$r3->name}");
                    $row++;
                }
            }
        }

        // 줄무늬 배경 + 테두리
        if ($row > 2) {
            $range = 'A2:G'.($row - 1);
            $catSheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D0D7DE');
            for ($r = 2; $r < $row; $r++) {
                if ($r % 2 === 0) {
                    $catSheet->getStyle("A{$r}:G{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
                }
            }
        }

        // 안내 메시지
        $catSheet->setCellValue("A{$row}", '');
        $catSheet->setCellValue('A'.($row + 1), '※ 데이터 입력 시트의 카테고리1/2/3차 컬럼에 위 코드 또는 이름을 그대로 입력하세요.');
        $catSheet->getStyle('A'.($row + 1))->getFont()->setItalic(true)->getColor()->setRGB('666666');
        $catSheet->mergeCells('A'.($row + 1).':G'.($row + 1));
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
                    'products' => $this->importProduct($data),
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

    private function importProduct(array $data): bool
    {
        $name = $data['제품명'] ?? null;
        $sku = $data['SKU'] ?? null;
        if (! $name || ! $sku) {
            return false;
        }

        // 카테고리 1차→2차→3차 순서로 트리 따라가며 가장 깊은 매치를 사용
        // 각 컬럼은 'code' 또는 'name' 어느 쪽이든 허용
        $catRefs = [
            trim((string) ($data['카테고리1차(코드/이름)'] ?? '')),
            trim((string) ($data['카테고리2차(코드/이름)'] ?? '')),
            trim((string) ($data['카테고리3차(코드/이름)'] ?? '')),
        ];

        $categoryId = null;
        $categoryName = null;
        $parentId = null;
        foreach ($catRefs as $ref) {
            if ($ref === '') {
                break;
            }
            $node = ProductCategory::where('parent_id', $parentId)
                ->where(function ($q) use ($ref) {
                    $q->where('code', $ref)->orWhere('name', $ref);
                })
                ->first();
            if (! $node) {
                throw new \Exception("카테고리 '{$ref}' 를 ".($parentId ? '하위에서' : '1차에서').' 찾을 수 없습니다.');
            }
            $categoryId = $node->id;
            $categoryName = $node->name;
            $parentId = $node->id;
        }

        Product::create([
            'sku' => $sku,
            'name' => $name,
            'category_id' => $categoryId,
            'category' => $categoryName,
            'purchase_price' => (int) ($data['매입가'] ?? 0),
            'sale_price' => (int) ($data['판매가'] ?? 0),
            'safety_stock' => (int) ($data['안전재고'] ?? 0),
            'memo' => $data['메모'] ?? null,
            'is_active' => true,
        ]);

        return true;
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
