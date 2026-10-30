<?php

namespace App\Http\Controllers;

use App\Models\RentalContract;
use App\Models\RentalProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** 렌탈 상품 관리 — 렌탈 담당자(rental.edit)가 탭별 상품을 생성·편집·삭제. 조회는 rental.view */
class RentalProductController extends Controller
{
    /** 상품 목록 — kind 지정 시 그 종류만, 비활성 포함(include_inactive=1)은 관리 모달용 */
    public function index(Request $request): JsonResponse
    {
        $query = RentalProduct::query()->withCount('contracts')->orderBy('sort_order')->orderBy('name');
        if ($request->filled('kind')) {
            $query->ofKind($this->kindFrom($request->query('kind')));
        }
        if (! $request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }

        return response()->json($query->get()->map(fn (RentalProduct $p) => $this->toArray($p))->values());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());
        $validated['kind'] = $this->kindFrom($validated['kind'] ?? null);
        $validated['sort_order'] = $validated['sort_order'] ?? ((int) RentalProduct::ofKind($validated['kind'])->max('sort_order') + 1);

        $product = RentalProduct::create($validated);

        return response()->json($this->toArray($product->loadCount('contracts')), 201);
    }

    public function update(Request $request, RentalProduct $product): JsonResponse
    {
        $validated = $request->validate($this->rules(partial: true));
        unset($validated['kind']); // 종류는 생성 시 고정 — 바꾸면 기존 계약(장비/서버)과 어긋난다
        $product->update($validated);

        return response()->json($this->toArray($product->fresh()->loadCount('contracts')));
    }

    /**
     * 삭제(소프트) — 소프트 삭제는 DB 의 nullOnDelete 를 타지 않으므로 직접 연결을 끊는다.
     * 기존 계약은 product_name 스냅샷·금액을 그대로 두고 '직접 입력' 상태가 된다.
     */
    public function destroy(RentalProduct $product): JsonResponse
    {
        DB::transaction(function () use ($product) {
            $product->contracts()->update(['product_id' => null, 'fee_manual' => true]);
            $product->delete();
        });

        return response()->json(['ok' => true]);
    }

    /** @return array<string, mixed> */
    private function rules(bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return [
            'kind' => ['sometimes', Rule::in(RentalContract::KINDS)],
            'name' => [$req, 'string', 'max:100'],
            'default_fee' => [$req, 'integer', 'min:0'],
            'memo' => ['nullable', 'string', 'max:200'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function kindFrom(?string $kind): string
    {
        return in_array($kind, RentalContract::KINDS, true) ? $kind : RentalContract::KIND_EQUIPMENT;
    }

    /** @return array<string, mixed> */
    private function toArray(RentalProduct $p): array
    {
        return [
            'id' => $p->id,
            'kind' => $p->kind,
            'name' => $p->name,
            'default_fee' => $p->default_fee,
            'memo' => $p->memo,
            'sort_order' => $p->sort_order,
            'is_active' => $p->is_active,
            'contracts_count' => $p->contracts_count ?? 0,
        ];
    }
}
