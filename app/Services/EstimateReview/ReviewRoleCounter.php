<?php

namespace App\Services\EstimateReview;

/**
 * 역할 수량 계산 — 명세 5장.
 *
 * 제품의 역할 결정 순서(5.1): 제품 개별 지정 → 옵션 그룹 지정 → 가장 깊은 카테고리 지정
 * (각 단계는 '행이 있으면' 그 값만 쓴다. 빈 배열 []은 "역할 없음"이라는 명시적 지정).
 * 세트 상품은 위 결과와 구성품 역할 합계의 역할별 max(5.1-3, 명세 시안과의 차이 반영: 구성품은 합계).
 *
 * 입력은 전부 배열(순수 함수) — 같은 입력이면 같은 출력.
 */
class ReviewRoleCounter
{
    /**
     * 품목 전체의 역할별 합계 — count(role) = Σ 수량 × 제품의 role 값 (5.2).
     * 수기 품목(product_id 없음)은 역할 0.
     *
     * @param  array<int, array<string, mixed>>  $items  [['product_id'=>?int,'qty'=>int,'replaced'=>bool,...]]
     * @param  array<int, array<string, mixed>>  $catalog  product_id => 제품 정보
     * @param  array<string, mixed>  $config
     * @return array<string, int> role code => count
     */
    public static function countAll(array $items, array $catalog, array $config): array
    {
        $counts = [];
        foreach ($items as $item) {
            if (! empty($item['replaced'])) {
                continue; // 대체(취소선) 항목 제외
            }
            $qty = max(1, (int) ($item['qty'] ?? 1));
            foreach (self::rolesOf($item['product_id'] ?? null, $catalog, $config) as $role => $per) {
                $counts[$role] = ($counts[$role] ?? 0) + $qty * $per;
            }
        }

        return $counts;
    }

    /**
     * 제품 1개의 역할 맵 (role => 수량/개).
     *
     * @return array<string, int>
     */
    public static function rolesOf(?int $productId, array $catalog, array $config): array
    {
        if (! $productId || ! isset($catalog[$productId])) {
            return []; // 수기/미등록 — 역할 없음 (5.2)
        }
        $product = $catalog[$productId];

        $base = self::declaredRoles($productId, $product, $config);

        // 세트: base(지정/카테고리 결과)와 구성품 합계의 역할별 max.
        // 단, 제품 개별 지정이 있으면 그것만 쓴다 (5.1-1 "다른 값과 합치지 않는다").
        if (! empty($product['is_bundle']) && ! array_key_exists($productId, $config['productRoles'] ?? [])) {
            $componentSum = [];
            foreach ($product['components'] ?? [] as [$childId, $childQty]) {
                foreach (self::rolesOf((int) $childId, $catalog, $config) as $role => $per) {
                    $componentSum[$role] = ($componentSum[$role] ?? 0) + max(1, (int) $childQty) * $per;
                }
            }
            $merged = $base ?? [];
            foreach ($componentSum as $role => $cnt) {
                $merged[$role] = max($merged[$role] ?? 0, $cnt);
            }

            return $merged;
        }

        return $base ?? [];
    }

    /**
     * 지정 단계 해석 — 제품 → 옵션 그룹 → 최심 카테고리. 행이 없으면 null.
     *
     * @return array<string, int>|null
     */
    private static function declaredRoles(int $productId, array $product, array $config): ?array
    {
        if (array_key_exists($productId, $config['productRoles'] ?? [])) {
            return self::toMap($config['productRoles'][$productId]);
        }
        $groupId = $product['group_id'] ?? null;
        if ($groupId && array_key_exists($groupId, $config['groupRoles'] ?? [])) {
            return self::toMap($config['groupRoles'][$groupId]);
        }
        // category_chain은 깊은 것부터 (deepest → root). 지정이 '존재'하는 첫 카테고리만 쓴다 —
        // 상위와 합치지 않고, []이면 역할 없음 (5.1-2)
        foreach ($product['category_chain'] ?? [] as $catId) {
            if (array_key_exists($catId, $config['categoryRoles'] ?? [])) {
                return self::toMap($config['categoryRoles'][$catId]);
            }
        }

        return null;
    }

    /**
     * [["mount",1],...] → ['mount'=>1,...]
     *
     * @return array<string, int>
     */
    private static function toMap(array $pairs): array
    {
        $map = [];
        foreach ($pairs as $pair) {
            $map[(string) $pair[0]] = ($map[(string) $pair[0]] ?? 0) + (int) ($pair[1] ?? 1);
        }

        return $map;
    }
}
