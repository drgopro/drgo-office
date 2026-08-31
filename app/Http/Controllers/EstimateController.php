<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Estimate;
use App\Models\Inventory;
use App\Models\PayappPayment;
use App\Models\Product;
use App\Models\Project;
use App\Models\Setting;
use App\Services\EstimatePaymentSync;
use App\Services\EstimateStockSync;
use App\Services\PayAppClient;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class EstimateController extends Controller
{
    public function index()
    {
        return view('estimates.index');
    }

    public function estimates(Request $request)
    {
        $query = Estimate::with('creator')
            ->where('status', '!=', 'temp')
            ->orderBy('created_at', 'desc');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('client_name', 'like', "%{$search}%")
                    ->orWhere('client_nickname', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('id', $search)
                    ->orWhere('estimate_no', $search);
            });
        }

        return response()->json($query->limit(100)->get());
    }

    /** 항목별 환불 후보 목록 — 프로젝트 환불 모달이 견적서 항목을 보고 선택 (잔여 수량 포함) */
    public function refundItems(Estimate $estimate)
    {
        return response()->json([
            'estimate_id' => $estimate->id,
            'no' => $estimate->display_no,
            'title' => $estimate->title,
            'items' => collect($estimate->product_items ?? [])->map(fn ($i, $idx) => [
                'index' => $idx,
                'name' => $i['name'] ?? '',
                'qty' => (int) ($i['qty'] ?? 1),
                'sale_price' => (int) ($i['sale_price'] ?? 0),
                'refund_qty' => (int) ($i['refund_qty'] ?? 0),
                'refund_amount' => (int) ($i['refund_amount'] ?? 0),
                'refunded' => ! empty($i['refunded']),
                // 세트 구성품 — 하위 항목 단위 부분환불용 (총 수량 = 구성 수량 × 세트 수량)
                'bundle_items' => collect($i['bundle_items'] ?? [])->map(fn ($b, $bIdx) => [
                    'bundle_index' => $bIdx,
                    'name' => $b['name'] ?? '',
                    'qty' => max(1, (int) ($b['qty'] ?? 1)) * max(1, (int) ($i['qty'] ?? 1)),
                    'price' => (int) ($b['price'] ?? 0),
                    'refund_qty' => (int) ($b['refund_qty'] ?? 0),
                    'refund_amount' => (int) ($b['refund_amount'] ?? 0),
                ])->values(),
            ])->values(),
        ]);
    }

    /** 빌더 1분 자동 임시저장 — 정식 저장과 별개 스냅샷 (저장 시 비워짐) */
    public function saveDraft(Request $request, Estimate $estimate)
    {
        $request->validate([
            'draft' => 'required|array',
            'draft.product_items' => 'nullable|array|max:300',
            'draft.service_items' => 'nullable|array|max:100',
        ]);

        // validated()는 중첩 규칙에 있는 키만 남기므로 스냅샷 전체는 원본 입력에서 가져온다
        $estimate->update(['draft' => $request->input('draft'), 'draft_saved_at' => now()]);

        return response()->json(['saved_at' => $estimate->draft_saved_at->format('H:i:s')]);
    }

    /** 임시저장 불러오기 — 저장된 스냅샷과 시각 반환 */
    public function getDraft(Estimate $estimate)
    {
        return response()->json([
            'draft' => $estimate->draft,
            'saved_at' => $estimate->draft_saved_at?->format('Y-m-d H:i:s'),
        ]);
    }

    /** 의뢰자 공개 링크 — 목록의 링크 복사 버튼용 (토큰이 없으면 생성) */
    public function publicLink(Estimate $estimate)
    {
        return response()->json(['public_url' => $estimate->publicUrl()]);
    }

    public function store(Request $request)
    {
        // 의뢰자 상세 '+ 새 견적서' — client_id로 진입 시 의뢰자 연동·이름을 미리 채운다
        $validated = $request->validate(['client_id' => 'nullable|exists:clients,id']);
        $client = ! empty($validated['client_id']) ? Client::find($validated['client_id']) : null;

        $estimate = Estimate::create([
            'status' => 'temp',
            'client_id' => $client?->id,
            'client_name' => $client?->name,
            'client_nickname' => $client?->nickname,
            'client_phone' => $client?->phone,
            'product_items' => [],
            'service_items' => [],
            'product_total' => 0,
            'service_total' => 0,
            'total_amount' => 0,
            'validity_days' => 3,
            'created_by' => Auth::id(),
        ]);

        return response()->json($estimate, 201);
    }

    /**
     * 엑셀 견적서 파싱 — 기존 엑셀 견적 양식(B=대분류, C=중분류, D=제품명, E=단가, G=수량, I=금액, J=비고)을
     * 읽어 견적서에 담을 항목 목록으로 변환한다. 제품 관리에 같은 이름(공백 무시)이 있으면 제품으로 연결하고
     * 없으면 수기 항목으로 표시한다. 저장하지 않고 파싱 결과만 반환 — 담기는 빌더(클라이언트)에서 수행.
     */
    public function parseExcel(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
            'sheet' => 'nullable|integer|min:0',
        ]);

        try {
            $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        } catch (\Throwable $e) {
            return response()->json(['message' => '엑셀 파일을 읽지 못했습니다: '.$e->getMessage()], 422);
        }

        $sheetNames = $spreadsheet->getSheetNames();
        $sheetIndex = min((int) $request->input('sheet', 0), count($sheetNames) - 1);
        $sheet = $spreadsheet->getSheet($sheetIndex);

        // 제품 매칭 맵 — 이름에서 공백을 제거해 소문자로 비교 (제품 리스트 검색과 동일 규칙)
        $normalize = fn ($v) => mb_strtolower(str_replace(' ', '', trim((string) $v)));
        $products = Product::where('is_active', true)
            ->with('categoryRelation.parent.parent')
            ->get(['id', 'name', 'sku', 'sale_price', 'purchase_price', 'category_id', 'service_kind'])
            ->keyBy(fn ($p) => $normalize($p->name));

        $skipWords = ['소계', '합계', 'amount', 'remark'];
        $isSkipWord = fn ($v) => in_array($normalize($v), array_map($normalize, $skipWords), true);
        $num = function ($v) {
            $s = str_replace([',', ' ', '원'], '', trim((string) $v));

            return is_numeric($s) ? (float) $s : null;
        };

        $items = [];
        $title = '';
        $cat = '';
        $mid = '';
        $highestRow = min($sheet->getHighestDataRow(), 2000);
        for ($row = 1; $row <= $highestRow; $row++) {
            $b = trim((string) $sheet->getCell('B'.$row)->getFormattedValue());
            $c = trim((string) $sheet->getCell('C'.$row)->getFormattedValue());
            $d = trim((string) $sheet->getCell('D'.$row)->getFormattedValue());
            $e = $num($sheet->getCell('E'.$row)->getCalculatedValue());
            $g = $num($sheet->getCell('G'.$row)->getCalculatedValue());
            $i = $num($sheet->getCell('I'.$row)->getCalculatedValue());
            $j = trim((string) $sheet->getCell('J'.$row)->getFormattedValue());

            if ($isSkipWord($b) || $isSkipWord($c) || $isSkipWord($d)) {
                continue; // 소계/합계/헤더 행
            }
            if ($b !== '') {
                $cat = $b;
                $mid = ''; // 대분류가 바뀌면 중분류 리셋
            }
            if ($c !== '') {
                $mid = $c;
            }
            if ($d === '') {
                continue;
            }
            if ($e === null && $i === null) {
                // 가격 없는 텍스트 행 — 첫 행은 견적서 제목 후보 (예: 'XL스튜디오 HDMI 변경시')
                if ($title === '' && $cat === '') {
                    $title = $d;
                }

                continue;
            }

            $qty = max(1, (int) ($g ?? 1));
            $unit = $e ?? ($i !== null ? (int) round($i / $qty) : 0);
            $amount = $i ?? $unit * $qty;
            $product = $products->get($normalize($d));
            $items[] = [
                'category' => $cat !== '' ? $cat : '기타',
                'mid_category' => $mid,
                'name' => mb_substr($d, 0, 200),
                'unit_price' => (int) $unit,
                'qty' => $qty,
                'amount' => (int) $amount,
                'remark' => mb_substr($j, 0, 500),
                'product_id' => $product?->id,
                'sku' => $product?->sku,
                'purchase_price' => $product ? (int) $product->purchase_price : 0,
                'price_differs' => $product ? (int) $product->sale_price !== (int) $unit : false,
                'is_service' => $product?->isService() ?? false,
            ];
        }

        return response()->json([
            'sheets' => $sheetNames,
            'sheet' => $sheetIndex,
            'title' => $title,
            'items' => $items,
            'matched' => collect($items)->whereNotNull('product_id')->count(),
            'total' => count($items),
        ]);
    }

    /**
     * 직접발송 사전 재고 확인 — 해당 항목(또는 세트 구성품)을 직접발송 처리하면
     * 재고가 음수로 떨어지는 제품 목록을 반환한다. 차감 자체는 저장 시 EstimateStockSync가 수행.
     */
    public function directShipCheck(Request $request, Estimate $estimate)
    {
        $v = $request->validate([
            'index' => 'required|integer|min:0',
            'bundle_index' => 'nullable|integer|min:0',
        ]);

        $items = $estimate->product_items ?? [];
        if (! array_key_exists($v['index'], $items)) {
            return response()->json(['shortages' => [], 'unknown' => true]); // 미저장 항목 — 확인 불가, 저장 시 반영
        }

        $new = $items;
        if (($v['bundle_index'] ?? null) !== null) {
            if (! isset($new[$v['index']]['bundle_items'][$v['bundle_index']])) {
                return response()->json(['shortages' => [], 'unknown' => true]);
            }
            $new[$v['index']]['bundle_items'][$v['bundle_index']]['ordered'] = true;
            $new[$v['index']]['bundle_items'][$v['bundle_index']]['source'] = '사무실 발송';
        } else {
            $new[$v['index']]['ordered'] = true;
            $new[$v['index']]['purchase_source'] = '사무실 발송';
        }

        $before = EstimateStockSync::netShippedMap($items);
        $after = EstimateStockSync::netShippedMap($new);
        $shortages = [];
        foreach ($after as $pid => $q) {
            $delta = $q - ($before[$pid] ?? 0);
            if ($delta <= 0) {
                continue;
            }
            $stock = (int) (Inventory::where('product_id', $pid)->value('quantity') ?? 0);
            if ($stock - $delta < 0) {
                $shortages[] = [
                    'name' => Product::whereKey($pid)->value('name') ?? '(삭제된 제품)',
                    'stock' => $stock,
                    'need' => $delta,
                    'after' => $stock - $delta,
                ];
            }
        }

        return response()->json(['shortages' => $shortages]);
    }

    public function edit(Estimate $estimate)
    {
        $estimate->syncSnapshotPrices(); // 결제/발행 전 견적서는 현재 제품 판매가 반영
        $estimate->load('client', 'creator');
        $settings = Setting::getMany([
            'seller_name', 'seller_biz_no', 'seller_address',
            'seller_biz_type', 'seller_biz_item', 'seller_phone',
        ]);

        return view('estimates.edit', compact('estimate', 'settings'));
    }

    public function update(Request $request, Estimate $estimate, PayAppClient $payapp)
    {
        $validated = $request->validate([
            'client_id' => 'nullable|exists:clients,id',
            'project_id' => 'nullable|exists:projects,id', // 의뢰자 프로젝트 연동 (선택)
            'title' => 'nullable|string|max:200', // 견적서 제목 — 출력물 상단 헤더에 표시
            'client_name' => 'nullable|string|max:100',
            'client_nickname' => 'nullable|string|max:100',
            'client_phone' => 'nullable|string|max:50',
            'ship_address' => 'nullable|string|max:300', // 배송받을 주소 — 내부용 (의뢰자 견적서 미표시)
            'ship_entrance' => 'nullable|string|max:200', // 공동현관 출입 정보 — 내부용
            'product_items' => 'nullable|array|max:300',
            // 스냅샷 필드 전체를 규칙에 포함 — 누락 필드가 저장되면 출력물 렌더에서 500이 나고,
            // 규칙에 없는 키는 validated()에서 걸러져 데이터가 유실되므로 양쪽 모두를 방지
            'product_items.*.product_id' => 'nullable|integer',
            'product_items.*.sku' => 'nullable|string|max:100',
            'product_items.*.category' => 'nullable|string|max:100',
            'product_items.*.category_root' => 'nullable|string|max:100',
            'product_items.*.name' => 'required|string|max:200',
            'product_items.*.purchase_price' => 'nullable|numeric|min:0',
            'product_items.*.sale_price' => 'required|numeric|min:0',
            'product_items.*.qty' => 'required|integer|min:1|max:9999',
            'product_items.*.subtotal' => 'required|numeric|min:0',
            'product_items.*.time_required' => 'nullable|string|max:50',
            'product_items.*.use_time' => 'nullable|boolean', // 소요시간 입력폼 사용 여부 (제품 설정 스냅샷)
            'product_items.*.manual' => 'nullable|boolean',
            'product_items.*.mid_category' => 'nullable|string|max:100', // 엑셀 가져오기 C열 중분류 — 제품명 위 작은 라벨
            'product_items.*.remark' => 'nullable|string|max:500', // 항목 비고 — 의뢰자 견적서에 표시
            'product_items.*.price_fixed' => 'nullable|boolean', // 엑셀 가격 보존 — 제품 판매가 동기화 제외
            'product_items.*.is_service' => 'nullable|boolean', // 서비스/제품 분류 스냅샷 — 매출 통계의 세팅비/장비 구분
            'product_items.*.deal_type' => 'nullable|in:special,discount', // 특가/할인 표시 — 스냅샷 전용 (제품 가격 불변)
            'product_items.*.original_price' => 'nullable|numeric|min:0', // 특가/할인 전 정가 — 출력물 취소선 표시
            'product_items.*.discount_rate' => 'nullable|numeric|min:0|max:100', // 할인율 % (금액 직접 입력이면 비움)
            'product_items.*.ordered' => 'nullable|boolean', // 주문/배송 뷰의 주문완료 표시
            'product_items.*.purchase_source' => 'nullable|string|max:100', // 주문 내역의 구매처
            'product_items.*.order_memo' => 'nullable|string|max:500', // 주문 내역의 메모
            'product_items.*.purchase_amount' => 'nullable|numeric|min:0', // 주문 내역의 구매 금액
            // 항목별 환불/결제취소 기록 — 프로젝트 환불·주문 내역 수동 체크 (빌더 저장 시 유실 방지)
            'product_items.*.refunded' => 'nullable|boolean',
            'product_items.*.refund_qty' => 'nullable|integer|min:0',
            'product_items.*.refund_amount' => 'nullable|numeric|min:0',
            'product_items.*.refunded_at' => 'nullable|string|max:20',
            // 세트 구성품 스냅샷 — 빌더 전용 표시 (출력물·의뢰자 견적서에는 세트 한 줄만)
            'product_items.*.bundle_items' => 'nullable|array|max:50',
            'product_items.*.bundle_items.*.name' => 'required|string|max:200',
            'product_items.*.bundle_items.*.qty' => 'nullable|integer|min:1|max:999',
            'product_items.*.bundle_items.*.price' => 'nullable|numeric|min:0',
            'product_items.*.bundle_items.*.refund_qty' => 'nullable|integer|min:0', // 구성품 부분환불 기록
            'product_items.*.bundle_items.*.refund_amount' => 'nullable|numeric|min:0',
            'product_items.*.bundle_items.*.ordered' => 'nullable|boolean', // 구성품 개별 주문완료
            'product_items.*.bundle_items.*.source' => 'nullable|string|max:100', // 구성품 구매처 (직접발송='사무실 발송')
            'product_items.*.bundle_items.*.memo' => 'nullable|string|max:500', // 구성품별 주문 메모
            'service_items' => 'nullable|array|max:100',
            'service_items.*.name' => 'required|string|max:200',
            'service_items.*.amount' => 'required|numeric|min:0',
            // 'temp'도 허용 — 신규 견적서 작성 직후 status가 'temp'로 남아있을 수 있음
            'status' => 'nullable|in:temp,created,editing,completed,issued,paid,hold,cancelled',
            'memo' => 'nullable|string',
            'internal_memo' => 'nullable|string', // 직원용 내부 비고 — 의뢰자 견적서에 미표시
        ]);

        // 연동 정합성 — 프로젝트는 이 견적서의 의뢰자 소유여야 한다 (타 의뢰자 프로젝트 연결 방지)
        if (! empty($validated['project_id'])) {
            $ownerClientId = Project::whereKey($validated['project_id'])->value('client_id');
            $clientId = array_key_exists('client_id', $validated) ? $validated['client_id'] : $estimate->client_id;
            if (! $clientId || (int) $ownerClientId !== (int) $clientId) {
                return response()->json([
                    'message' => '프로젝트 연동은 선택한 의뢰자의 프로젝트만 가능합니다.',
                    'errors' => ['project_id' => ['선택한 의뢰자의 프로젝트가 아닙니다.']],
                ], 422);
            }
        }

        try {
            $productTotal = (int) collect($validated['product_items'] ?? [])->sum('subtotal');
            $serviceTotal = (int) collect($validated['service_items'] ?? [])->sum('amount');

            // temp → created로 자동 전환 (첫 저장 시)
            if ($estimate->status === 'temp' && (! isset($validated['status']) || $validated['status'] === 'temp')) {
                $validated['status'] = 'created';
            }

            $becameIssued = ($validated['status'] ?? null) === 'issued' && $estimate->status !== 'issued';
            $becamePaid = ($validated['status'] ?? null) === 'paid' && $estimate->status !== 'paid';
            $becameCancelled = ($validated['status'] ?? null) === 'cancelled' && $estimate->status === 'paid';
            $oldItemsForStock = $estimate->product_items; // 직접발송 재고 연동 — 저장 전 스냅샷

            $estimate->update([
                ...$validated,
                'product_total' => $productTotal,
                'service_total' => $serviceTotal,
                'total_amount' => $productTotal + $serviceTotal,
                'issued_at' => $becameIssued ? now() : $estimate->issued_at,
                // 정식 저장되면 자동 임시저장본은 더 이상 최신이 아니므로 비운다
                'draft' => null,
                'draft_saved_at' => null,
            ]);

            // 직접발송 재고 연동 — 스냅샷이 실제로 전송된 저장에서만 전/후 비교로 차감·복원
            if (array_key_exists('product_items', $validated)) {
                EstimateStockSync::apply($estimate, $oldItemsForStock, $estimate->fresh()->product_items);
            }

            // 첫 실제 저장(temp 탈출) 시 표시 번호 발급 — 만들고 버린 견적서는 번호를 쓰지 않는다
            $this->assignEstimateNo($estimate->fresh());

            // 발행완료로 전환 시 페이앱 결제요청 자동 생성 (실패해도 저장은 유지)
            $warning = $becameIssued ? $this->ensurePayappRequest($estimate->fresh(), $payapp) : null;

            // 결제완료/결제취소 수동 전환 — 프로젝트 결제 내역·캘린더 일정에 전파
            if ($becamePaid) {
                EstimatePaymentSync::estimatePaid($estimate->fresh(), '수동 기록');
            } elseif ($becameCancelled) {
                EstimatePaymentSync::estimateCancelled($estimate->fresh());
            }

            // 과거 자동 생성된 연동 청구(입금 이력 없는 미입금 건) 정리 — 연동만으로 잔금이 잡히지 않도록
            EstimatePaymentSync::syncProjectBilling($estimate->fresh());

            return response()->json([
                ...$estimate->fresh()->toArray(),
                'payapp_warning' => $warning,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => '견적서 저장 실패: '.$e->getMessage(),
                'exception' => class_basename($e),
                'file' => basename($e->getFile()).':'.$e->getLine(),
            ], 500);
        }
    }

    /**
     * 표시 번호 발급 — 첫 실제 저장(temp 탈출) 때 현재 최대 번호+1.
     * unique 제약이 동시 저장의 중복 번호를 막고, 충돌(23000)이 나면
     * 최대값을 다시 읽어 재시도한다.
     */
    private function assignEstimateNo(Estimate $estimate): void
    {
        if ($estimate->estimate_no || $estimate->status === 'temp') {
            return;
        }

        retry(3, function () use ($estimate) {
            $estimate->estimate_no = (int) Estimate::max('estimate_no') + 1;
            $estimate->saveQuietly();
        }, 30);
    }

    /**
     * 발행완료 처리 — 의뢰자 페이지의 결제 버튼 활성화를 위해
     * 페이앱 결제요청도 자동 생성한다 (실패해도 발행은 유지, 경고만 반환).
     */
    public function issue(Estimate $estimate, PayAppClient $payapp)
    {
        $estimate->update([
            'status' => 'issued',
            'issued_at' => now(),
        ]);
        $this->assignEstimateNo($estimate);

        $warning = $this->ensurePayappRequest($estimate, $payapp);

        return response()->json([
            ...$estimate->fresh()->toArray(),
            'payapp_warning' => $warning,
            'public_url' => $estimate->publicUrl(),
        ]);
    }

    /**
     * 페이앱 결제요청이 없으면 생성 시도. 실패 사유를 경고 문자열로 반환 (성공 시 null).
     */
    private function ensurePayappRequest(Estimate $estimate, PayAppClient $payapp): ?string
    {
        if ($estimate->payapp_payurl) {
            return null; // 이미 결제요청 있음
        }
        if (! $payapp->isConfigured()) {
            return '페이앱 연동 정보가 설정되지 않아 결제 버튼 없이 발행됩니다.';
        }

        $result = $payapp->requestPayment($estimate);
        if (! $result['ok']) {
            return '결제요청 생성 실패: '.$result['error'];
        }

        $estimate->update([
            'payapp_mul_no' => $result['mul_no'],
            'payapp_payurl' => $result['payurl'],
            'payapp_state' => 1,
            'payapp_requested_at' => now(),
            'payapp_paid_at' => null,
        ]);

        return null;
    }

    /** 견적서 편집에서 의뢰자 연동 프로젝트 선택용 — 진행 중 프로젝트만 (연동은 선택 사항) */
    public function clientProjects(Client $client)
    {
        return response()->json(
            $client->projects()->whereNull('completed_at')->orderByDesc('id')
                ->get(['id', 'name', 'stage', 'client_id'])
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'stage_label' => $p->stageLabel()])
        );
    }

    public function print(Estimate $estimate)
    {
        $estimate->syncSnapshotPrices(); // 결제/발행 전 견적서는 현재 제품 판매가 반영
        $settings = Setting::getMany([
            'seller_name', 'seller_biz_no', 'seller_address',
            'seller_biz_type', 'seller_biz_item', 'seller_phone', 'seller_stamp_path',
        ]);

        return view('estimates.print', compact('estimate', 'settings'));
    }

    /** 주문/배송 운송장 등록 — 견적서 편집에서 새 창으로 연다 */
    public function shipmentsPage(Estimate $estimate)
    {
        return view('estimates.shipments', compact('estimate'));
    }

    /** 직인 이미지 스트리밍 — 내부 인쇄·의뢰자 공개 견적서 공용 (토큰·로그인 불필요) */
    public function sellerStamp()
    {
        $path = Setting::get('seller_stamp_path');
        abort_if(! $path || ! Storage::exists($path), 404);

        return Storage::response($path, 'seller-stamp', ['Cache-Control' => 'public, max-age=3600']);
    }

    /**
     * 의뢰자용 공개 견적서 — 난수 토큰으로만 접근 (로그인 불필요, ID 추측 불가).
     * 결제요청이 생성돼 있으면 하단에 페이앱 결제 버튼 노출.
     */
    public function publicView(string $token)
    {
        abort_if(strlen($token) < 32, 404);
        $estimate = Estimate::where('share_token', $token)->firstOrFail();
        $estimate->syncSnapshotPrices(); // 결제/발행 전 견적서는 현재 제품 판매가 반영

        $settings = Setting::getMany([
            'seller_name', 'seller_biz_no', 'seller_address',
            'seller_biz_type', 'seller_biz_item', 'seller_phone', 'seller_stamp_path',
        ]);

        return view('estimates.print', [
            'estimate' => $estimate,
            'settings' => $settings,
            'publicMode' => true,
        ]);
    }

    /**
     * 페이앱 결제요청 생성 — 공개 견적서의 결제 버튼 활성화.
     * 금액/연락처 변경 후 다시 누르면 새 결제요청으로 교체된다.
     */
    public function payappRequest(Estimate $estimate, PayAppClient $payapp)
    {
        if ($estimate->status === 'paid') {
            return response()->json(['message' => '이미 결제 완료된 견적서입니다.'], 422);
        }

        $result = $payapp->requestPayment($estimate);
        if (! $result['ok']) {
            return response()->json(['message' => $result['error']], 422);
        }

        $estimate->update([
            'payapp_mul_no' => $result['mul_no'],
            'payapp_payurl' => $result['payurl'],
            'payapp_state' => 1,
            'payapp_requested_at' => now(),
            'payapp_paid_at' => null,
        ]);

        return response()->json([
            'message' => '결제요청이 생성되었습니다.',
            'payurl' => $result['payurl'],
            'public_url' => $estimate->publicUrl(),
        ]);
    }

    /** 페이앱 결제요청 취소 — 공개 견적서의 결제 버튼 비활성화 */
    public function payappCancel(Estimate $estimate, PayAppClient $payapp)
    {
        if ($estimate->status === 'paid') {
            return response()->json(['message' => '이미 결제 완료된 건은 페이앱 관리자에서 승인취소해주세요.'], 422);
        }

        $result = $payapp->cancelRequest($estimate);
        if (! $result['ok']) {
            return response()->json(['message' => $result['error']], 422);
        }

        // mul_no는 남겨 페이앱의 취소 통보가 외부 결제로 중복 기록되지 않게 함
        $estimate->update([
            'payapp_payurl' => null,
            'payapp_state' => 16,
        ]);

        return response()->json(['message' => '결제요청이 취소되었습니다.']);
    }

    /**
     * 페이앱 결제 결과 통지(feedbackurl) 수신 — 인증 세션 밖에서 호출됨.
     * 검증 실패 시에도 200 외 응답으로 페이앱이 재시도하도록 둔다.
     */
    public function payappFeedback(Request $request, PayAppClient $payapp)
    {
        // 페이앱의 URL 사전 점검(GET/빈 요청)은 처리 없이 통과 — 단 진단을 위해 기록
        // (http→https 리다이렉트로 POST가 GET으로 강등되면 여기로 들어와 결제 데이터가 유실됨)
        if (! $request->isMethod('post') || ! $request->has('pay_state')) {
            $payapp->log('feedback-사전점검', [], sprintf(
                'method=%s ip=%s pay_state=%s query=%s',
                $request->method(),
                $request->ip(),
                $request->input('pay_state', '(없음)'),
                json_encode($request->except(['linkkey', 'linkval']), JSON_UNESCAPED_UNICODE)
            ));

            return response('OK');
        }

        $payload = $request->all();
        // 기존 카페24 공통 통보 스크립트로 중계 — 우리 검증/처리와 무관하게 원본 전달
        $payapp->relayFeedback($payload);
        $estimate = null;
        if ($id = (int) ($payload['var1'] ?? 0)) {
            $estimate = Estimate::find($id);
        }
        if (! $estimate && ! empty($payload['mul_no'])) {
            $estimate = Estimate::where('payapp_mul_no', $payload['mul_no'])->first();
        }

        $safePayload = collect($payload)->except(['linkkey', 'linkval'])->all();

        // 견적서와 연결되지 않은 통지 — 페이앱 자체(외부) 결제로 기록
        // (판매자 설정의 기본 FEEDBACK URL을 이 주소로 지정하면 페이앱에서 직접 만든 결제도 통지됨)
        if (! $estimate) {
            return $this->recordExternalPayappPayment($payload, $safePayload, $payapp);
        }

        if (! $payapp->verifyFeedback($payload, $estimate)) {
            $payapp->log('feedback-거부', [], $payapp->feedbackDiagnosis($payload, $estimate)
                ."\n".json_encode($safePayload, JSON_UNESCAPED_UNICODE));

            return response('FAIL', 400);
        }

        $state = (int) ($payload['pay_state'] ?? 0);
        $estimate->payapp_state = $state;

        if ($state === PayAppClient::STATE_PAID) {
            // 금액 대조 — 요청 후 견적이 수정된 경우 결제완료 처리 보류 (로그로 확인)
            $paidPrice = (int) ($payload['price'] ?? 0);
            if ($paidPrice === (int) $estimate->total_amount) {
                $estimate->status = 'paid';
                $estimate->payapp_paid_at = now();
            } else {
                Log::warning("페이앱 결제금액 불일치: 견적서 #{$estimate->id} 견적 {$estimate->total_amount}원 vs 결제 {$paidPrice}원");
            }
        } elseif (in_array($state, PayAppClient::STATES_REFUNDED, true)) {
            // 환불(승인취소) → 취소된 견적서로 표시. payurl만 비워 재결제는 발행 완료로
            // 변경 시 자동 생성. mul_no는 남겨 이후 통보가 외부 결제로 중복 기록되지 않게 함
            $estimate->status = 'cancelled';
            $estimate->payapp_paid_at = null;
            $estimate->payapp_payurl = null;
        } elseif (in_array($state, PayAppClient::STATES_REQUEST_CANCELLED, true)) {
            $estimate->payapp_payurl = null;
        }

        $estimate->save();

        // 결제 상태 전파 — 프로젝트 결제 내역(원장)·캘린더 일정 표시 동기화
        if ($state === PayAppClient::STATE_PAID && $estimate->status === 'paid') {
            EstimatePaymentSync::estimatePaid($estimate);
        } elseif (in_array($state, PayAppClient::STATES_REFUNDED, true)) {
            // 페이앱 전액환불 — 남은 금액의 취소 트랜잭션 기록 + 전 항목 환불 표시
            EstimatePaymentSync::estimateCancelled($estimate, recordLedger: true);
        }

        $payapp->log('feedback-처리', [], json_encode($safePayload, JSON_UNESCAPED_UNICODE));

        return response('SUCCESS');
    }

    /**
     * 페이앱 자체(외부) 결제 통지 기록 — mul_no 기준 upsert.
     * userid 일치 필수, 연동키가 실려 왔다면 반드시 일치해야 함.
     */
    private function recordExternalPayappPayment(array $payload, array $safePayload, PayAppClient $payapp)
    {
        $useridOk = ($payload['userid'] ?? null) === config('services.payapp.userid');
        $linkSent = isset($payload['linkkey']) || isset($payload['linkval']);
        if (! $useridOk || empty($payload['mul_no']) || ($linkSent && ! $payapp->verifyFeedback($payload, null))) {
            $payapp->log('feedback-거부', [], $payapp->feedbackDiagnosis($payload, null)
                ."\n".json_encode($safePayload, JSON_UNESCAPED_UNICODE));

            return response('FAIL', 400);
        }

        $state = (int) ($payload['pay_state'] ?? 0);
        $payment = PayappPayment::firstOrNew(['mul_no' => (string) $payload['mul_no']]);

        // 역행 통보 무시 — 이미 취소/환불로 기록된 결제에 '결제완료'가 뒤늦게 오는 건
        // 페이앱 재시도가 순서 뒤바뀌어 도착한 것 (같은 결제번호의 재결제는 존재하지 않음)
        $cancelledStates = array_merge(PayAppClient::STATES_REFUNDED, PayAppClient::STATES_REQUEST_CANCELLED);
        if ($payment->exists && in_array($payment->pay_state, $cancelledStates, true) && $state === PayAppClient::STATE_PAID) {
            $payapp->log('feedback-역행무시', [], sprintf('mul_no=%s 기존상태=%d 수신상태=%d — 취소 이후의 결제완료 통보는 무시', $payment->mul_no, $payment->pay_state, $state));

            return response('SUCCESS');
        }

        $parseTime = static function (?string $value): ?Carbon {
            try {
                return $value ? Carbon::parse($value) : null;
            } catch (\Throwable) {
                return null;
            }
        };

        $payment->fill([
            'pay_state' => $state,
            'price' => (int) ($payload['price'] ?? 0) ?: $payment->price,
            'goodname' => mb_substr((string) ($payload['goodname'] ?? ''), 0, 200) ?: $payment->goodname,
            'buyer' => mb_substr((string) ($payload['buyer'] ?? $payload['buyername'] ?? ''), 0, 100) ?: $payment->buyer,
            'recvphone' => mb_substr((string) ($payload['recvphone'] ?? ''), 0, 30) ?: $payment->recvphone,
            'pay_type' => mb_substr((string) ($payload['pay_type'] ?? ''), 0, 30) ?: $payment->pay_type,
            'card_name' => mb_substr((string) ($payload['card_name'] ?? ''), 0, 60) ?: $payment->card_name,
            'csturl' => mb_substr((string) ($payload['csturl'] ?? ''), 0, 500) ?: $payment->csturl,
        ]);
        // 실제 요청/결제 시각 — 통보가 재시도로 늦게 와도 수신 시각이 아닌 원래 시각으로 표시
        $payment->requested_at = $parseTime($payload['reqdate'] ?? null) ?? $payment->requested_at ?? now();
        if ($state === PayAppClient::STATE_PAID && ! $payment->paid_at) {
            $payment->paid_at = $parseTime($payload['pay_date'] ?? null) ?? now();
        }
        $payment->save();

        $payapp->log('feedback-외부결제', [], json_encode($safePayload, JSON_UNESCAPED_UNICODE));

        return response('SUCCESS');
    }

    public function destroy(Estimate $estimate)
    {
        // 삭제 전파 — 미수 청구·프로젝트 견적/계약 카드·캘린더 연동에서 정리 + 직접발송 재고 복원
        EstimatePaymentSync::estimateDeleted($estimate);
        EstimateStockSync::release($estimate);
        $estimate->delete();

        return response()->json(['message' => '삭제되었습니다.']);
    }
}
