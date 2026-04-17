<?php

namespace App\Http\Controllers;

use App\Models\RentalItem;
use App\Models\RentalLog;
use App\Models\RentalTarget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * 대여 장비 현황판 — 제품 관리(Product)와 독립된 장비·대상·이력 관리.
 */
class RentalEquipmentController extends Controller
{
    // === 보드 통합 조회 ===

    public function board(): JsonResponse
    {
        $items = RentalItem::orderBy('name')->get([
            'id', 'name', 'serial', 'category', 'components', 'description', 'current_target_id',
        ]);

        $targets = RentalTarget::orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'address', 'note']);

        $assignments = $items
            ->filter(fn ($i) => $i->current_target_id !== null)
            ->mapWithKeys(fn ($i) => [$i->id => $i->current_target_id]);

        $logs = RentalLog::with('user:id,display_name')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn ($l) => [
                'id' => $l->id,
                'user' => $l->user?->display_name,
                'action' => $l->action,
                'detail' => $l->detail,
                'created_at' => $l->created_at,
            ]);

        return response()->json([
            'items' => $items,
            'targets' => $targets,
            'assignments' => $assignments,
            'logs' => $logs,
        ]);
    }

    // === 장비 CRUD ===

    public function storeItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'serial' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:100',
            'components' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        $item = RentalItem::create($validated);
        $this->log($item->id, null, '장비 추가', $item->name.' 등록');

        return response()->json($item, 201);
    }

    public function updateItem(Request $request, RentalItem $item): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'serial' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:100',
            'components' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        $item->update($validated);
        $this->log($item->id, $item->current_target_id, '장비 편집', $item->name.' 정보 수정');

        return response()->json($item);
    }

    public function destroyItem(RentalItem $item): JsonResponse
    {
        $name = $item->name;
        $item->delete();
        $this->log(null, null, '장비 삭제', $name.' 제거');

        return response()->json(['message' => '삭제되었습니다.']);
    }

    // === 대상 CRUD ===

    public function storeTarget(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'note' => 'nullable|string',
        ]);

        $validated['sort_order'] = (RentalTarget::max('sort_order') ?? 0) + 1;

        $target = RentalTarget::create($validated);
        $this->log(null, $target->id, '대상 추가', $target->name.' 등록');

        return response()->json($target, 201);
    }

    public function updateTarget(Request $request, RentalTarget $target): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'note' => 'nullable|string',
        ]);

        $target->update($validated);
        $this->log(null, $target->id, '대상 편집', $target->name.' 정보 수정');

        return response()->json($target);
    }

    public function destroyTarget(RentalTarget $target): JsonResponse
    {
        $name = $target->name;
        $target->delete();
        $this->log(null, null, '대상 삭제', $name.' 제거');

        return response()->json(['message' => '삭제되었습니다.']);
    }

    // === 위치 지정/반납 ===

    public function assign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'item_id' => 'required|exists:rental_items,id',
            'target_id' => 'nullable|exists:rental_targets,id',
            'memo' => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($validated) {
            /** @var RentalItem $item */
            $item = RentalItem::findOrFail($validated['item_id']);
            $prevTargetId = $item->current_target_id;
            $newTargetId = $validated['target_id'] ?? null;

            if ($prevTargetId === $newTargetId) {
                return response()->json(['message' => '변경 없음'], 200);
            }

            $item->update(['current_target_id' => $newTargetId]);

            $prev = $prevTargetId ? RentalTarget::find($prevTargetId) : null;
            $next = $newTargetId ? RentalTarget::find($newTargetId) : null;

            if ($next && $prev) {
                $action = '위치 변경';
                $detail = "{$item->name}: {$prev->name} → {$next->name}";
            } elseif ($next) {
                $action = '위치 지정';
                $detail = "{$item->name} → {$next->name}";
            } else {
                $action = '반납 처리';
                $detail = "{$item->name} 위치 해제".($prev ? " (이전: {$prev->name})" : '');
            }

            if (! empty($validated['memo'])) {
                $detail .= " ({$validated['memo']})";
            }

            $this->log($item->id, $newTargetId, $action, $detail);

            return response()->json($item->fresh('currentTarget'));
        });
    }

    // === 헬퍼 ===

    private function log(?int $itemId, ?int $targetId, string $action, string $detail): void
    {
        RentalLog::create([
            'user_id' => Auth::id(),
            'item_id' => $itemId,
            'target_id' => $targetId,
            'action' => $action,
            'detail' => $detail,
            'created_at' => now(),
        ]);
    }
}
