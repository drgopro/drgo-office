<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Setting;
use App\Services\EstimateReview\EstimateReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 견적서 검토 기준 관리 — 규칙·역할·제품 역할·규격 (permission:estimates.review_admin).
 *
 * 설정은 Setting('estimate_review.config') JSON 한 벌로 저장하고,
 * 저장 즉시 다음 검토부터 반영된다 (검토가 끝난 견적도 품목이 바뀌면 다시 검토).
 */
class EstimateReviewSettingsController extends Controller
{
    public function page(EstimateReviewService $service)
    {
        return view('estimate-review.settings', [
            'config' => $service->storedConfig(),
            'categories' => $this->categories(),
            'products' => $this->products(),
        ]);
    }

    /** 설정 전체 저장 — 관리 화면이 통째로 보낸다 (작은 팀 규모라 마지막 저장이 이긴다) */
    public function save(Request $request): JsonResponse
    {
        $request->validate([
            'config' => 'required|array',
            'config.version' => 'nullable|integer',
            'config.checks' => 'required|array',
            'config.types' => 'required|array',
            'config.roles' => 'required|array|max:200',
            'config.roles.*.id' => 'required|string|max:50',
            'config.roles.*.label' => 'required|string|max:100',
            'config.roles.*.group' => 'required|string|max:50',
            'config.rules' => 'required|array|max:300',
            'config.rules.*.id' => 'required|string|max:80',
            'config.rules.*.type' => 'required|in:need,ask',
            'config.rules.*.on' => 'required|array|min:1',
            'config.categoryRolesByPath' => 'present|array',
            'config.productRolesByName' => 'present|array',
            'config.specTemplatesByPath' => 'present|array',
            'config.specsByName' => 'present|array',
            'config.specsByPath' => 'present|array',
        ]);

        // validate()의 반환값은 규칙에 적힌 키만 남기므로, 검증 통과 후 원본 전체를 저장한다
        $json = json_encode($request->input('config'), JSON_UNESCAPED_UNICODE);
        if (strlen($json) > 2 * 1024 * 1024) {
            return response()->json(['message' => '설정이 너무 큽니다 (2MB 초과).'], 422);
        }

        Setting::set(EstimateReviewService::CONFIG_KEY, $json);

        return response()->json(['ok' => true, 'saved_at' => now()->format('H:i:s')]);
    }

    /** @return array<int, array{path: string, count: int}> */
    private function categories(): array
    {
        $cats = ProductCategory::query()->get(['id', 'parent_id', 'name'])->keyBy('id');
        $paths = [];
        foreach ($cats as $id => $cat) {
            $parts = [];
            $cur = $cat;
            $guard = 0;
            while ($cur && $guard++ < 10) {
                array_unshift($parts, trim((string) $cur->name));
                $cur = $cur->parent_id ? $cats->get($cur->parent_id) : null;
            }
            $paths[$id] = implode('>', $parts);
        }

        $counts = Product::query()->selectRaw('category_id, count(*) as n')->groupBy('category_id')->pluck('n', 'category_id');

        // 경로별 제품 수 — 하위 카테고리 수량을 상위 경로에도 합산 (트리 표시용)
        $byPath = [];
        foreach ($paths as $id => $path) {
            $n = (int) ($counts[$id] ?? 0);
            $parts = explode('>', $path);
            for ($i = 1; $i <= count($parts); $i++) {
                $prefix = implode('>', array_slice($parts, 0, $i));
                $byPath[$prefix] = ($byPath[$prefix] ?? 0) + $n;
            }
        }
        ksort($byPath);

        return collect($byPath)->map(fn ($n, $path) => ['path' => $path, 'count' => $n])->values()->all();
    }

    /** @return array<int, array{name: string, path: string, bundle: bool}> */
    private function products(): array
    {
        $cats = ProductCategory::query()->get(['id', 'parent_id', 'name'])->keyBy('id');
        $pathOf = function (?int $id) use ($cats): string {
            $parts = [];
            $cur = $id ? $cats->get($id) : null;
            $guard = 0;
            while ($cur && $guard++ < 10) {
                array_unshift($parts, trim((string) $cur->name));
                $cur = $cur->parent_id ? $cats->get($cur->parent_id) : null;
            }

            return implode('>', $parts);
        };

        return Product::query()->orderBy('name')
            ->get(['id', 'name', 'category_id', 'is_bundle'])
            ->map(fn ($p) => ['name' => $p->name, 'path' => $pathOf($p->category_id), 'bundle' => (bool) $p->is_bundle])
            ->values()->all();
    }
}
