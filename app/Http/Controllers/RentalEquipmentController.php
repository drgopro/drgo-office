<?php

namespace App\Http\Controllers;

use App\Models\RentalCategory;
use App\Models\RentalGroup;
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
    public function index()
    {
        return view('rental.index');
    }

    // === 보드 통합 조회 ===

    public function board(): JsonResponse
    {
        $items = RentalItem::orderBy('name')->get([
            'id', 'name', 'serial', 'category_id', 'components', 'description',
            'current_target_id', 'home_target_id', 'group_id',
        ]);

        $targets = RentalTarget::orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'address', 'note']);

        $groups = RentalGroup::orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $categories = RentalCategory::orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

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
            'groups' => $groups,
            'categories' => $categories,
            'assignments' => $assignments,
            'logs' => $logs,
        ]);
    }

    // === 장비 CRUD ===

    public function storeItem(Request $request): JsonResponse
    {
        $validated = $this->validateItem($request);

        // 원래 위치가 지정되면 현재 위치도 같은 곳으로 초기화 (보드에 바로 표시)
        if (! empty($validated['home_target_id'])) {
            $validated['current_target_id'] = $validated['home_target_id'];
        }

        $item = RentalItem::create($validated);
        $this->log($item->id, $item->current_target_id, '장비 추가', $item->name.' 등록');

        return response()->json($item, 201);
    }

    public function updateItem(Request $request, RentalItem $item): JsonResponse
    {
        $validated = $this->validateItem($request);

        // 현재 위치가 비어 있는 상태에서 home이 지정되면 현재 위치도 함께 채움
        if ($item->current_target_id === null && ! empty($validated['home_target_id'])) {
            $validated['current_target_id'] = $validated['home_target_id'];
        }

        $item->update($validated);
        $this->log($item->id, $item->current_target_id, '장비 편집', $item->name.' 정보 수정');

        return response()->json($item);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateItem(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:200',
            'serial' => 'nullable|string|max:100',
            'category_id' => 'nullable|exists:rental_categories,id',
            'components' => 'nullable|string',
            'description' => 'nullable|string',
            'home_target_id' => 'nullable|exists:rental_targets,id',
            'group_id' => 'nullable|exists:rental_groups,id',
        ]);
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
            'return' => 'nullable|boolean',
            'memo' => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($validated) {
            /** @var RentalItem $item */
            $item = RentalItem::findOrFail($validated['item_id']);
            $prevTargetId = $item->current_target_id;

            $isReturn = ! empty($validated['return']);
            $newTargetId = $isReturn
                ? $item->home_target_id
                : ($validated['target_id'] ?? null);

            if ($prevTargetId === $newTargetId) {
                return response()->json(['message' => '변경 없음'], 200);
            }

            $item->update(['current_target_id' => $newTargetId]);

            $prev = $prevTargetId ? RentalTarget::find($prevTargetId) : null;
            $next = $newTargetId ? RentalTarget::find($newTargetId) : null;

            [$action, $detail] = $this->describeMovement($item->name, $prev, $next, $isReturn);

            if (! empty($validated['memo'])) {
                $detail .= " ({$validated['memo']})";
            }

            $this->log($item->id, $newTargetId, $action, $detail);

            return response()->json($item->fresh('currentTarget'));
        });
    }

    // === 카테고리 CRUD ===

    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $validated['sort_order'] = (RentalCategory::max('sort_order') ?? 0) + 1;

        $category = RentalCategory::create($validated);
        $this->log(null, null, '카테고리 추가', $category->name.' 등록');

        return response()->json($category, 201);
    }

    public function updateCategory(Request $request, RentalCategory $category): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $category->update($validated);
        $this->log(null, null, '카테고리 편집', $category->name.' 정보 수정');

        return response()->json($category);
    }

    public function destroyCategory(RentalCategory $category): JsonResponse
    {
        $name = $category->name;
        $category->delete();
        $this->log(null, null, '카테고리 삭제', $name.' 제거');

        return response()->json(['message' => '삭제되었습니다.']);
    }

    // === 그룹 CRUD ===

    public function storeGroup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
        ]);

        $validated['sort_order'] = (RentalGroup::max('sort_order') ?? 0) + 1;

        $group = RentalGroup::create($validated);
        $this->log(null, null, '그룹 추가', $group->name.' 등록');

        return response()->json($group, 201);
    }

    public function updateGroup(Request $request, RentalGroup $group): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
        ]);

        $group->update($validated);
        $this->log(null, null, '그룹 편집', $group->name.' 정보 수정');

        return response()->json($group);
    }

    public function destroyGroup(RentalGroup $group): JsonResponse
    {
        $name = $group->name;
        $group->delete();
        $this->log(null, null, '그룹 삭제', $name.' 제거');

        return response()->json(['message' => '삭제되었습니다.']);
    }

    /**
     * 그룹 내 모든 장비를 한 target으로 일괄 이동 (또는 반납).
     */
    public function assignGroup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'group_id' => 'required|exists:rental_groups,id',
            'target_id' => 'nullable|exists:rental_targets,id',
            'return' => 'nullable|boolean',
            'memo' => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($validated) {
            $group = RentalGroup::findOrFail($validated['group_id']);
            $items = RentalItem::where('group_id', $group->id)->get();

            if ($items->isEmpty()) {
                return response()->json(['message' => '그룹에 장비가 없습니다.'], 422);
            }

            $isReturn = ! empty($validated['return']);
            $targetName = null;
            $count = 0;

            foreach ($items as $item) {
                $newTargetId = $isReturn
                    ? $item->home_target_id
                    : ($validated['target_id'] ?? null);

                if ($item->current_target_id === $newTargetId) {
                    continue;
                }

                $item->update(['current_target_id' => $newTargetId]);
                $count++;
            }

            if ($validated['target_id'] ?? null) {
                $targetName = RentalTarget::find($validated['target_id'])?->name;
            }

            $action = $isReturn ? '그룹 반납' : '그룹 이동';
            $detail = "[{$group->name}] "
                .($isReturn ? '각 장비 원래 위치로 복귀' : ($targetName ? "→ {$targetName}" : '위치 해제'))
                ." ({$count}개)";

            if (! empty($validated['memo'])) {
                $detail .= " ({$validated['memo']})";
            }

            $this->log(null, $validated['target_id'] ?? null, $action, $detail);

            return response()->json(['updated' => $count]);
        });
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function describeMovement(string $itemName, ?RentalTarget $prev, ?RentalTarget $next, bool $isReturn): array
    {
        if ($isReturn) {
            if ($next && $prev) {
                return ['반납 처리', "{$itemName}: {$prev->name} → {$next->name} (원위치 복귀)"];
            }
            if ($next) {
                return ['반납 처리', "{$itemName} → {$next->name} (원위치 복귀)"];
            }

            return ['반납 처리', "{$itemName} 위치 해제".($prev ? " (이전: {$prev->name})" : '')];
        }

        if ($next && $prev) {
            return ['위치 변경', "{$itemName}: {$prev->name} → {$next->name}"];
        }
        if ($next) {
            return ['위치 지정', "{$itemName} → {$next->name}"];
        }

        return ['위치 해제', "{$itemName} 위치 해제".($prev ? " (이전: {$prev->name})" : '')];
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
