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
        $result['findings'] = $this->attachCandidates($result['findings'], $catalog, $config);

        return $result;
    }

    /**
     * 발견에 후보 제품 제안 부착 — 누락/수량 발견은 필요한 역할의 제품,
     * 확인 질문은 규칙의 추천 제품(fix). 담을 수 있는(sellable) 제품만, 추천 제품 우선.
     */
    private function attachCandidates(array $findings, array $catalog, array $config): array
    {
        $roleCache = [];
        $byRoles = function (array $roles) use (&$roleCache, $catalog, $config): array {
            $key = implode(',', $roles);
            if (isset($roleCache[$key])) {
                return $roleCache[$key];
            }
            $out = [];
            foreach ($catalog as $pid => $row) {
                if (! $row['sellable']) {
                    continue;
                }
                $has = ReviewRoleCounter::rolesOf($pid, $catalog, $config);
                foreach ($roles as $role) {
                    if (! empty($has[$role])) {
                        $out[] = $pid;
                        break;
                    }
                }
            }

            return $roleCache[$key] = $out;
        };

        $this->catalog(); // nameIndex 준비
        $cand = fn (int $pid) => ['product_id' => $pid, 'name' => $catalog[$pid]['name'], 'sale_price' => $catalog[$pid]['sale_price']];

        foreach ($findings as &$f) {
            $fixId = ! empty($f['fix']) ? ($this->nameIndex[$f['fix']] ?? null) : null;
            if (in_array($f['kind'] ?? '', ['miss', 'qty'], true) && ! empty($f['need_roles'])) {
                $pids = $byRoles((array) $f['need_roles']);
                // 추천 제품(fix) 맨 앞, 이후 이름순 — 15개까지
                usort($pids, fn ($a, $b) => (($b === $fixId) <=> ($a === $fixId)) ?: strcmp($catalog[$a]['name'], $catalog[$b]['name']));
                $f['cands'] = array_map($cand, array_slice($pids, 0, 15));
            } elseif (($f['kind'] ?? '') === 'ask' && $fixId && ($catalog[$fixId]['sellable'] ?? false)) {
                $f['cands'] = [$cand($fixId)];
            }
        }

        return $findings;
    }

    /** @param array<int, array<string, mixed>> $rawItems */
    public function fingerprint(array $rawItems): string
    {
        return ReviewEngine::fingerprint($this->normalizeItems($rawItems));
    }

    /** 출력/발행 게이트 사용 여부 — 관리 화면에서 끌 수 있다 */
    public function gateEnabled(): bool
    {
        return ($this->storedConfig()['gate'] ?? true) !== false;
    }

    /**
     * 출력/발행 관문 — 통과하지 못하면 사유 문자열, 통과하면 null.
     * 검토 완료 + 검토 시점 품목(지문)과 현재 품목이 같아야 한다.
     *
     * @param  array<string, mixed>|null  $reviewState  estimates.review_state
     * @param  array<int, array<string, mixed>>  $items  검사 기준 품목 (저장될/저장된 product_items)
     */
    public function gateError(?array $reviewState, array $items): ?string
    {
        if (! $this->gateEnabled()) {
            return null;
        }
        if (empty($reviewState['done'])) {
            return '검토가 완료되지 않은 견적서입니다. 검토 버튼으로 품목 점검을 마친 뒤 진행하세요.';
        }
        if (($reviewState['fingerprint'] ?? null) !== $this->fingerprint($items)) {
            return '검토 후 품목이 바뀌었습니다. 다시 검토를 완료한 뒤 진행하세요.';
        }

        return null;
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
            ->get(['id', 'name', 'category_id', 'group_id', 'is_bundle', 'is_active', 'show_in_estimate', 'sale_price']);

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
                // 후보 제안용 — 견적 빌더에 담을 수 있는 제품인지 + 판매가 (매입가는 싣지 않는다)
                'sellable' => (bool) $p->is_active && (bool) $p->show_in_estimate,
                'sale_price' => (int) $p->sale_price,
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
