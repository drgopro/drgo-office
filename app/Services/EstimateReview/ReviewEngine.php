<?php

namespace App\Services\EstimateReview;

/**
 * 견적서 검토 판정 엔진 — 명세 「견적서 검토 기능 구현 명세」 5~7장.
 *
 * 순수 함수: 입력(품목 + 제품 카탈로그 스냅샷 + 설정)이 같으면 항상 같은 결과.
 * DB·시간·전역 상태에 의존하지 않는다. 설정/카탈로그 로딩은 호출자(서비스 레이어) 책임.
 *
 * 입력 형태
 *  items:   [['product_id'=>?int,'name'=>string,'qty'=>int,'price'=>int,'manual'=>bool,
 *             'is_service'=>bool,'replaced'=>bool], ...]
 *  catalog: [product_id => ['name','group_id'=>?int,'category_chain'=>[깊은순 카테고리 id...],
 *             'is_bundle'=>bool,'components'=>[[product_id,qty],...]]]
 *  config:  ['version'=>int,
 *            'categoryRoles'=>[catId=>[[role,qty],...]], 'groupRoles'=>..., 'productRoles'=>...,
 *            'rules'=>[...6.1/6.2 형태...], 'checks'=>['video'=>bool,...],
 *            'specTemplates'=>[catId=>[필수필드,...]], 'specs'=>[productId=>규격 배열]]
 *
 * 출력: ['findings'=>[], 'passed'=>[], 'paths'=>[], 'counts'=>['miss_qty','link_fit','ask','passed']]
 *  finding = ['key','kind(miss|qty|link|fit|ask)','group','title',...]
 *  주의: 응답 어디에도 purchase_price를 싣지 않는다 (명세 3장).
 */
class ReviewEngine
{
    /**
     * @return array{findings: array, passed: array, paths: array, counts: array<string, int>}
     */
    public static function run(array $items, array $catalog, array $config): array
    {
        $items = array_values(array_filter($items, fn ($i) => empty($i['replaced'])));
        $findings = [];
        $passed = [];
        $paths = [];

        // ── 역할 수량 (5장) + 규칙 평가 (6.1/6.2) ──
        $roleCounts = ReviewRoleCounter::countAll($items, $catalog, $config);
        $count = fn (array $roles) => array_sum(array_map(fn ($r) => $roleCounts[$r] ?? 0, $roles));

        foreach ($config['rules'] ?? [] as $rule) {
            if (empty($rule['active'])) {
                continue;
            }
            if (($rule['type'] ?? 'need') === 'need') {
                $trigger = $count((array) $rule['on']);
                if ($trigger === 0) {
                    continue; // 해당 없음 — 어디에도 세지 않음 (7.2)
                }
                $need = ($rule['mode'] ?? 'perUnit') === 'atLeast' ? (int) $rule['amt'] : $trigger * (int) $rule['amt'];
                $have = $count((array) $rule['need']);
                if ($have >= $need) {
                    $passed[] = ['rule' => $rule['id'], 'title' => "{$rule['what']} 확인"];

                    continue;
                }
                $findings[] = [
                    'key' => "{$rule['id']}:{$need}:{$have}",
                    'kind' => $have === 0 ? 'miss' : 'qty',
                    'group' => (string) ($rule['group'] ?? ''),
                    'title' => $have === 0 ? "{$rule['what']} 없음" : "{$rule['what']} ".($need - $have).'개 부족',
                    'why' => (string) ($rule['why'] ?? ''),
                    'short' => $need - $have,
                    'need_roles' => (array) $rule['need'],
                    'fix' => $rule['fix'] ?? null,
                ];
            } else { // ask (6.2)
                $n = $count((array) $rule['on']);
                if ($n < max(1, (int) ($rule['min'] ?? 1))) {
                    continue;
                }
                $c = $count((array) ($rule['cmp'] ?? []));
                $cond = (string) ($rule['cond'] ?? 'always');
                if ($cond === 'short' && $c >= $n) {
                    $passed[] = ['rule' => $rule['id'], 'title' => ($rule['title'] ?? $rule['id']).' — 충족'];

                    continue;
                }
                if ($cond === 'exceeds' && $n <= $c) {
                    continue; // 묻지 않음
                }
                $findings[] = [
                    'key' => "{$rule['id']}:{$n}:{$c}",
                    'kind' => 'ask',
                    'group' => (string) ($rule['group'] ?? ''),
                    'title' => str_replace(['{n}', '{d}'], [(string) $n, (string) abs($n - $c)], (string) $rule['title']),
                    'desc' => (string) ($rule['desc'] ?? ''),
                    'choices' => (array) ($rule['choices'] ?? []),
                    'fix' => $rule['fix'] ?? null,
                ];
            }
        }

        // ── 전개 유닛 (세트 → 구성품, 수량만큼 복제) — 규격 기반 검사용 ──
        $units = self::expandUnits($items, $catalog, $config);
        $checks = $config['checks'] ?? [];

        foreach (['video', 'audio'] as $family) {
            if (! empty($checks[$family])) {
                $r = ReviewCompatChecker::linkCheck($family, $units);
                $findings = [...$findings, ...$r['findings']];
                $passed = [...$passed, ...$r['passed']];
                $paths = [...$paths, ...$r['paths']];
            }
        }
        if (! empty($checks['lens'])) {
            $r = ReviewCompatChecker::lensCheck($units);
            $findings = [...$findings, ...$r['findings']];
            $passed = [...$passed, ...$r['passed']];
        }
        if (! empty($checks['battery'])) {
            $r = ReviewCompatChecker::batteryCheck($units);
            $findings = [...$findings, ...$r['findings']];
            $passed = [...$passed, ...$r['passed']];
        }
        if (! empty($checks['mount'])) {
            $r = ReviewCompatChecker::mountCheck($units);
            $findings = [...$findings, ...$r['findings']];
            $passed = [...$passed, ...$r['passed']];
        }

        // ── 데이터 공백 (6.8) ──
        if (! empty($checks['spec'])) {
            $findings = [...$findings, ...self::specGaps($units, $catalog, $config)];
        }
        $findings = [...$findings, ...self::manualWarning($items)];
        $findings = [...$findings, ...self::alwaysWarnings($units)];

        // 같은 키 중복 제거 (7.2 — 같은 키 중복 ask는 1건)
        $unique = [];
        foreach ($findings as $finding) {
            $unique[$finding['key']] ??= $finding;
        }
        $findings = array_values($unique);

        $kinds = array_count_values(array_map(fn ($f) => $f['kind'], $findings));

        return [
            'findings' => $findings,
            'passed' => $passed,
            'paths' => $paths,
            'counts' => [
                'miss_qty' => ($kinds['miss'] ?? 0) + ($kinds['qty'] ?? 0),
                'link_fit' => ($kinds['link'] ?? 0) + ($kinds['fit'] ?? 0),
                'ask' => $kinds['ask'] ?? 0,
                'passed' => count($passed),
            ],
        ];
    }

    /** 검토 지문 — 명세 8.2: 품목(제품·수량)만. 가격/할인/메모/순서/설정버전 제외 (설정 변경은 재검토 사유가 아님) */
    public static function fingerprint(array $items): string
    {
        $parts = [];
        foreach ($items as $item) {
            if (! empty($item['replaced'])) {
                continue;
            }
            $id = ! empty($item['product_id']) ? (string) $item['product_id'] : 'manual:'.trim((string) ($item['name'] ?? ''));
            $parts[] = $id.'x'.max(1, (int) ($item['qty'] ?? 1));
        }
        sort($parts);

        return hash('sha256', implode('|', $parts));
    }

    /**
     * 세트 전개 — is_bundle 제품은 구성품 유닛으로, 그 외는 자기 유닛으로. 수량만큼 복제.
     *
     * @return array<int, array{product_id:int, name:string, spec:array}>
     */
    private static function expandUnits(array $items, array $catalog, array $config): array
    {
        $units = [];
        foreach ($items as $item) {
            $pid = $item['product_id'] ?? null;
            if (! $pid || ! isset($catalog[$pid])) {
                continue; // 수기 품목 — 자동 검사 제외 (6.8이 경고)
            }
            $qty = max(1, (int) ($item['qty'] ?? 1));
            for ($i = 0; $i < $qty; $i++) {
                foreach (self::productUnits((int) $pid, $catalog, $config) as $unit) {
                    $units[] = $unit;
                }
            }
        }

        return $units;
    }

    /** @return array<int, array{product_id:int, name:string, spec:array}> */
    private static function productUnits(int $pid, array $catalog, array $config, int $depth = 0): array
    {
        $product = $catalog[$pid];
        if (! empty($product['is_bundle']) && ! empty($product['components']) && $depth < 3) {
            $units = [];
            foreach ($product['components'] as [$childId, $childQty]) {
                if (! isset($catalog[$childId])) {
                    continue;
                }
                for ($i = 0; $i < max(1, (int) $childQty); $i++) {
                    $units = [...$units, ...self::productUnits((int) $childId, $catalog, $config, $depth + 1)];
                }
            }

            return $units;
        }

        return [[
            'product_id' => $pid,
            'name' => (string) ($product['name'] ?? ('#'.$pid)),
            'spec' => (array) (($config['specs'] ?? [])[$pid] ?? []),
        ]];
    }

    /** 카테고리 필수 규격이 빈 제품 → ask (6.8). 제품당 1건 */
    private static function specGaps(array $units, array $catalog, array $config): array
    {
        $templates = $config['specTemplates'] ?? [];
        $specs = $config['specs'] ?? [];
        $findings = [];
        $seen = [];
        foreach ($units as $unit) {
            $pid = $unit['product_id'];
            if (isset($seen[$pid])) {
                continue;
            }
            $seen[$pid] = true;
            $required = null;
            foreach ($catalog[$pid]['category_chain'] ?? [] as $catId) {
                if (array_key_exists($catId, $templates)) {
                    $required = (array) $templates[$catId];
                    break;
                }
            }
            if (! $required) {
                continue;
            }
            $spec = (array) ($specs[$pid] ?? []);
            foreach ($required as $field) {
                if (! array_key_exists($field, $spec)) { // 필드 자체가 없음 = 미입력 ([]는 입력된 값)
                    $findings[] = [
                        'key' => "spec:{$pid}",
                        'kind' => 'ask', 'group' => '규격',
                        'title' => "{$unit['name']} 규격 미입력",
                        'desc' => '필수 규격('.implode(', ', $required).')이 비어 있어 자동 검사에서 빠집니다.',
                        'choices' => ['직접 확인함'],
                        'product_id' => $pid,
                    ];
                    break;
                }
            }
        }

        return $findings;
    }

    /** 수기 장비 품목 요약 경고 1건 (6.8) — 가격 > 0, 서비스(세팅비) 제외 */
    private static function manualWarning(array $items): array
    {
        $names = [];
        foreach ($items as $item) {
            $isManual = ! empty($item['manual']) || empty($item['product_id']);
            if ($isManual && (int) ($item['price'] ?? 0) > 0 && empty($item['is_service'])) {
                $names[] = trim((string) ($item['name'] ?? ''));
            }
        }
        if ($names === []) {
            return [];
        }
        $joined = implode(',', $names);

        return [[
            'key' => 'manual:'.mb_strlen($joined).':'.count($names),
            'kind' => 'ask', 'group' => '수기 품목',
            'title' => '수기 품목 '.count($names).'개는 자동 검사에서 빠집니다',
            'desc' => $joined,
            'choices' => ['확인함'],
        ]];
    }

    /** 항상 경고할 제품 (6.8 warnAlways) */
    private static function alwaysWarnings(array $units): array
    {
        $findings = [];
        $seen = [];
        foreach ($units as $unit) {
            $warn = $unit['spec']['warnAlways'] ?? null;
            if (! $warn || isset($seen[$unit['product_id']])) {
                continue;
            }
            $seen[$unit['product_id']] = true;
            $findings[] = [
                'key' => 'warn:'.$unit['product_id'],
                'kind' => 'ask', 'group' => '주의',
                'title' => (string) ($warn['title'] ?? $unit['name'].' 확인 필요'),
                'desc' => (string) ($warn['desc'] ?? ''),
                'choices' => ['확인함'],
                'product_id' => $unit['product_id'],
            ];
        }

        return $findings;
    }
}
