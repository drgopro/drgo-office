<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Product;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcelImportController extends Controller
{
    private const TEMPLATES = [
        'products' => [
            'headers' => ['SKU', '제품명', '카테고리', '매입가', '판매가', '안전재고', '메모'],
            'required' => ['SKU', '제품명'],
        ],
        'clients' => [
            'headers' => ['이름', '닉네임', '전화번호', '주소', '상세주소', '등급(일반/VIP/렌탈)', '성별(남/여/기타)', '소속', '특이사항', '메모'],
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

        $filename = "drgo-{$type}-template.xlsx";

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
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

        Product::create([
            'sku' => $sku,
            'name' => $name,
            'category' => $data['카테고리'] ?? null,
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
        $name = $data['이름'] ?? null;
        if (! $name) {
            return false;
        }

        $gradeMap = ['일반' => 'normal', 'VIP' => 'vip', 'vip' => 'vip', '렌탈' => 'rental'];
        $genderMap = ['남' => 'male', '남성' => 'male', '여' => 'female', '여성' => 'female', '기타' => 'other'];

        Client::create([
            'name' => $name,
            'nickname' => $data['닉네임'] ?? null,
            'phone' => $data['전화번호'] ?? null,
            'address' => $data['주소'] ?? null,
            'address_detail' => $data['상세주소'] ?? null,
            'grade' => $gradeMap[$data['등급(일반/VIP/렌탈)'] ?? ''] ?? 'normal',
            'gender' => $genderMap[$data['성별(남/여/기타)'] ?? ''] ?? null,
            'affiliation' => $data['소속'] ?? null,
            'important_memo' => $data['특이사항'] ?? null,
            'memo' => $data['메모'] ?? null,
            'assigned_user_id' => Auth::id(),
            'status' => 'active',
        ]);

        return true;
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
