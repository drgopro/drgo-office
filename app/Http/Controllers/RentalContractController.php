<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\RentalContract;
use App\Models\RentalProduct;
use App\Services\ContractCalendarSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RentalContractController extends Controller
{
    public function __construct(private ContractCalendarSync $calendarSync) {}

    /** 탭(장비 렌탈 / 서버 렌탈) 골격 — 목록·통계는 /api/rental-contracts?kind= 로 탭별 로드 */
    public function index(Request $request)
    {
        $kind = $this->kindFrom($request->query('kind'));

        return view('rental-contracts.index', compact('kind'));
    }

    /** 계약 목록 — kind 지정 시 해당 종류만 (미지정 시 전체, 구버전 탭 호환) */
    public function list(Request $request)
    {
        $query = RentalContract::with(['client', 'product'])->orderByDesc('start_date')->limit(500);
        if ($request->filled('kind')) {
            $query->where('kind', $this->kindFrom($request->query('kind')));
        }

        $contracts = $query->get()->map(fn (RentalContract $c) => [
            'id' => $c->id,
            'kind' => $c->kind,
            'product_id' => $c->product_id,
            'product_name' => $c->displayProductName(),
            'fee_manual' => (bool) $c->fee_manual,
            'client_id' => $c->client_id,
            'client_name' => $c->client?->name,
            'client_nickname' => $c->client?->nickname,
            'start_date' => $c->start_date?->format('Y-m-d'),
            'end_date' => $c->end_date?->format('Y-m-d'),
            'monthly_fee' => $c->monthly_fee,
            'status' => $c->status,
            'memo' => $c->memo,
            'server_name' => $c->server_name,
            'server_spec' => $c->server_spec,
            'server_access' => $c->server_access,
            'calendar_synced' => ! empty($c->calendar_meta),
        ]);

        return response()->json($contracts);
    }

    private function kindFrom(?string $kind): string
    {
        return in_array($kind, RentalContract::KINDS, true) ? $kind : RentalContract::KIND_EQUIPMENT;
    }

    /**
     * 상품 선택 → 상품명 스냅샷 + 금액 자동 입력.
     * fee_manual 이 참이면(세트 일부 제외 등 예외) 보낸 monthly_fee 를 그대로 쓰고, 아니면 상품 기본 금액으로 덮는다.
     * 상품 없이 등록하면 보낸 금액·상품명을 그대로 저장(기존 계약 호환).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyProduct(array $data, ?RentalContract $existing = null): array
    {
        $productId = array_key_exists('product_id', $data) ? $data['product_id'] : $existing?->product_id;
        $feeManual = array_key_exists('fee_manual', $data) ? (bool) $data['fee_manual'] : (bool) ($existing?->fee_manual ?? false);

        if ($productId) {
            $product = RentalProduct::find($productId);
            if ($product) {
                $data['product_name'] = $product->name;
                if (! $feeManual) {
                    $data['monthly_fee'] = $product->default_fee;
                }
            }
        } elseif (array_key_exists('product_id', $data)) {
            // 상품 연결 해제 — 직접 입력한 상품명·금액만 남는다
            $data['fee_manual'] = true;
        }

        if (array_key_exists('monthly_fee', $data) && $data['monthly_fee'] === null) {
            $data['monthly_fee'] = $existing?->monthly_fee ?? 0;
        }

        if (! array_key_exists('fee_manual', $data) && $existing === null) {
            $data['fee_manual'] = $productId ? false : true; // 상품 없이 등록 = 직접 입력
        }

        return $data;
    }

    /** 장비 계약에 서버 상품을 붙이는 식의 종류 불일치는 거부 */
    private function assertProductKindMatches(?int $productId, string $kind): void
    {
        if (! $productId) {
            return;
        }
        $product = RentalProduct::find($productId);
        if ($product && $product->kind !== $kind) {
            throw ValidationException::withMessages(['product_id' => '선택한 상품은 '.RentalContract::KIND_LABELS[$product->kind].' 상품입니다. '.RentalContract::KIND_LABELS[$kind].' 탭의 상품을 고르세요.']);
        }
    }

    /**
     * 장비 렌탈에는 서버 필드가 남지 않도록 — 종류를 서버→장비로 바꾸면 접속 정보 등을 함께 지운다.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function clearServerFieldsUnlessServer(array $data, string $kind): array
    {
        if ($kind !== RentalContract::KIND_SERVER) {
            $data['server_name'] = null;
            $data['server_spec'] = null;
            $data['server_access'] = null;
        }

        return $data;
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'kind' => ['sometimes', Rule::in(RentalContract::KINDS)],
            'product_id' => ['nullable', 'integer', Rule::exists('rental_products', 'id')->whereNull('deleted_at')],
            'product_name' => 'nullable|string|max:100',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'monthly_fee' => 'required_without:product_id|nullable|integer|min:0',
            'fee_manual' => 'sometimes|boolean',
            'status' => ['required', Rule::in(RentalContract::STATUSES)],
            'memo' => 'nullable|string',
            'server_name' => 'nullable|string|max:100',
            'server_spec' => 'nullable|string|max:200',
            'server_access' => 'nullable|string|max:200',
        ]);

        $this->assertProductKindMatches($validated['product_id'] ?? null, $validated['kind'] ?? RentalContract::KIND_EQUIPMENT);
        $validated = $this->clearServerFieldsUnlessServer($validated, $validated['kind'] ?? RentalContract::KIND_EQUIPMENT);
        $validated = $this->applyProduct($validated);

        $contract = DB::transaction(function () use ($validated) {
            $contract = RentalContract::create($validated);
            $this->calendarSync->sync($contract); // 캘린더 양방향 등록 (시작/종료 이벤트 + 결제일 반복)

            return $contract;
        });

        return response()->json($contract->load('client'), 201);
    }

    public function update(Request $request, RentalContract $contract)
    {
        $validated = $request->validate([
            'client_id' => 'sometimes|exists:clients,id',
            'kind' => ['sometimes', Rule::in(RentalContract::KINDS)],
            'product_id' => ['nullable', 'integer', Rule::exists('rental_products', 'id')->whereNull('deleted_at')],
            'product_name' => 'nullable|string|max:100',
            'start_date' => 'sometimes|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'monthly_fee' => 'sometimes|nullable|integer|min:0',
            'fee_manual' => 'sometimes|boolean',
            'status' => ['sometimes', Rule::in(RentalContract::STATUSES)],
            'memo' => 'nullable|string',
            'server_name' => 'nullable|string|max:100',
            'server_spec' => 'nullable|string|max:200',
            'server_access' => 'nullable|string|max:200',
        ]);

        $this->assertProductKindMatches(
            array_key_exists('product_id', $validated) ? $validated['product_id'] : $contract->product_id,
            $validated['kind'] ?? $contract->kind,
        );
        $validated = $this->clearServerFieldsUnlessServer($validated, $validated['kind'] ?? $contract->kind);
        $validated = $this->applyProduct($validated, $contract);

        DB::transaction(function () use ($contract, $validated) {
            $contract->update($validated);
            $this->calendarSync->sync($contract); // 변경 사항 캘린더 재동기화
        });

        return response()->json($contract->load('client'));
    }

    public function destroy(RentalContract $contract)
    {
        DB::transaction(function () use ($contract) {
            $this->calendarSync->remove($contract); // 연결된 캘린더 일정 정리
            $contract->delete();
        });

        return response()->json(['ok' => true]);
    }

    // 의뢰자 검색 (autocomplete)
    public function searchClients(Request $request)
    {
        $q = $request->query('q', '');
        $clients = Client::where('status', '!=', 'blacklist')
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('nickname', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            })
            ->limit(20)
            ->get()
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'nickname' => $c->nickname, 'phone' => $c->phone]);

        return response()->json($clients);
    }
}
