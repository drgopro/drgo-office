<?php

namespace App\Services\EstimateReview;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Setting;

/**
 * 견적서 검토 서비스 — 설정/카탈로그 로딩과 이름→id 변환을 맡고,
 * 판정은 순수 함수 ReviewEngine에 위임한다.
 */
class EstimateReviewService
{
    public const CONFIG_KEY = 'estimate_review.config';

    /** @var array<string, mixed>|null */
    private ?array $cachedCatalog = null;

    /** @var array<string, int>|null 제품명 → id */
    private ?array $nameIndex = null;

    /** @var array<int, string>|null 카테고리 id → 경로("PC>PC 본체>송출용") */
    private ?array $categoryPaths = null;

    /**
     * 견적 품목 배열로 검토 실행.
     *
     * @param  array<int, array<string, mixed>>  $rawItems  product_items 형식 ([{product_id?, name, qty, sale_price?, is_service?, replaced?, manual?}])
     * @return array{findings: array, passed: array, paths: array, counts: array<string,int>, fingerprint: string}
     */
    public function review(array $rawItems): array
    {
        $items = $this->normalizeItems($rawItems);
        $catalog = $this->catalog();
        $config = $this->engineConfig();

        $result = ReviewEngine::run($items, $catalog, $config);
        $result['fingerprint'] = ReviewEngine::fingerprint($items);

        return $result;
    }

    /** @param array<int, array<string, mixed>> $rawItems */
    public function fingerprint(array $rawItems): string
    {
        return ReviewEngine::fingerprint($this->normalizeItems($rawItems));
    }

    /** 운영 설정(Setting JSON) 또는 기본값 */
    public function storedConfig(): array
    {
        $json = Setting::get(self::CONFIG_KEY);
        if ($json) {
            $decoded = json_decode((string) $json, true);
            if (is_array($decoded)) {
                return $decoded + DefaultReviewConfig::make();
            }
        }

        return DefaultReviewConfig::make();
    }

    /**
     * 엔진 입력 형식으로 정규화 — 제품명으로 id 보정, 노출 금지 필드 제거.
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizeItems(array $rawItems): array
    {
        $this->catalog(); // nameIndex 준비
        $items = [];
        foreach ($rawItems as $raw) {
            if (! is_array($raw)) {
                continue;
            }
            $name = trim((string) ($raw['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $pid = (int) ($raw['product_id'] ?? 0) ?: ($this->nameIndex[$name] ?? null);
            $items[] = [
                'product_id' => $pid ?: null,
                'name' => $name,
                'qty' => max(1, (int) ($raw['qty'] ?? 1)),
                'price' => (int) ($raw['sale_price'] ?? $raw['price'] ?? 0),
                'manual' => ! $pid || ! empty($raw['manual']),
                'is_service' => ! empty($raw['is_service']),
                'replaced' => ! empty($raw['replaced']),
            ];
        }

        return $items;
    }

    /** @return array<int, array<string, mixed>> product_id => 카탈로그 행 */
    private function catalog(): array
    {
        if ($this->cachedCatalog !== null) {
            return $this->cachedCatalog;
        }
        $this->categoryPaths = $this->buildCategoryPaths();

        $chains = [];
        foreach ($this->categoryPaths as $id => $path) {
            $chains[$id] = $this->categoryChain($id);
        }

        $products = Product::query()
            ->with('bundleItems:id,bundle_product_id,component_product_id,quantity')
            ->get(['id', 'name', 'category_id', 'group_id', 'is_bundle']);

        $catalog = [];
        $index = [];
        foreach ($products as $p) {
            $catalog[$p->id] = [
                'name' => $p->name,
                'group_id' => $p->group_id,
                'category_chain' => $chains[$p->category_id] ?? [],
                'is_bundle' => (bool) $p->is_bundle,
                'components' => $p->is_bundle
                    ? $p->bundleItems->map(fn ($b) => [(int) $b->component_product_id, max(1, (int) $b->quantity)])->all()
                    : [],
            ];
            $index[$p->name] = $p->id;
        }
        $this->nameIndex = $index;

        return $this->cachedCatalog = $catalog;
    }

    /**
     * 이름/경로 키 설정 → 엔진의 id 키 설정으로 변환.
     * 없는 제품명·카테고리 경로는 조용히 건너뛴다(제품 목록 변동에 안전).
     */
    private function engineConfig(): array
    {
        $stored = $this->storedConfig();
        $this->catalog();
        $pathToId = array_flip($this->categoryPaths ?? []);

        $categoryRoles = [];
        foreach ($stored['categoryRolesByPath'] ?? [] as $path => $roles) {
            if (isset($pathToId[$path])) {
                $categoryRoles[$pathToId[$path]] = $roles;
            }
        }
        $productRoles = [];
        foreach ($stored['productRolesByName'] ?? [] as $name => $roles) {
            if (isset($this->nameIndex[$name])) {
                $productRoles[$this->nameIndex[$name]] = $roles;
            }
        }
        $specTemplates = [];
        foreach ($stored['specTemplatesByPath'] ?? [] as $path => $fields) {
            if (isset($pathToId[$path])) {
                $specTemplates[$pathToId[$path]] = $fields;
            }
        }

        // 규격: 카테고리 경로 기본값 → 제품명 지정이 덮어씀
        $specs = [];
        foreach ($stored['specsByPath'] ?? [] as $path => $spec) {
            if (! isset($pathToId[$path])) {
                continue;
            }
            $catId = $pathToId[$path];
            foreach ($this->cachedCatalog as $pid => $row) {
                if (in_array($catId, $row['category_chain'], true)) {
                    $specs[$pid] = ($specs[$pid] ?? []) + $spec;
                }
            }
        }
        foreach ($stored['specsByName'] ?? [] as $name => $spec) {
            if (isset($this->nameIndex[$name])) {
                $specs[$this->nameIndex[$name]] = $spec + ($specs[$this->nameIndex[$name]] ?? []);
            }
        }

        return [
            'version' => (int) ($stored['version'] ?? 1),
            'categoryRoles' => $categoryRoles,
            'groupRoles' => [],
            'productRoles' => $productRoles,
            'rules' => $stored['rules'] ?? [],
            'checks' => $stored['checks'] ?? [],
            'specTemplates' => $specTemplates,
            'specs' => $specs,
        ];
    }

    private $cats = null;

    /** @return array<int, string> 카테고리 id → 이름 경로 */
    private function buildCategoryPaths(): array
    {
        $cats = $this->cats ??= ProductCategory::query()->get(['id', 'parent_id', 'name'])->keyBy('id');
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

        return $paths;
    }

    /** @return array<int, int> 깊은 것부터(자기 → 루트) 카테고리 id 체인 */
    private function categoryChain(?int $categoryId): array
    {
        $cats = $this->cats ??= ProductCategory::query()->get(['id', 'parent_id', 'name'])->keyBy('id');
        $chain = [];
        $cur = $categoryId ? $cats->get($categoryId) : null;
        $guard = 0;
        while ($cur && $guard++ < 10) {
            $chain[] = (int) $cur->id;
            $cur = $cur->parent_id ? $cats->get($cur->parent_id) : null;
        }

        return $chain;
    }
}
