<?php

namespace App\Services\EstimateReview;

/**
 * 연결·호환 검사 — 명세 6.3~6.7의 고정 로직 5종 (video, audio, lens, battery, mount).
 *
 * 공통 규칙(6.7): 값이 하나라도 겹치면 호환. 비교 대상의 규격 "필드 자체가 없으면"
 * 호환 안 됨으로 단정하지 않고 건너뛴다(미입력은 GapChecker가 ask로 수집).
 * 빈 배열 []은 "해당 규격 없음"이라는 입력된 값이므로 판정에 그대로 쓴다.
 *
 * 입력 단위는 '전개된 유닛' — 세트는 구성품으로 전개하고 수량만큼 복제한 목록이다.
 */
class ReviewCompatChecker
{
    private const MAX_CONNECTORS = 3; // 경로당 연결품 최대 사용 수 (6.3)

    /**
     * 연결 검사 (video/audio) — 출력 1개당 경로 1개를 탐욕 탐색으로 찾는다 (6.3).
     *
     * @param  array<int, array>  $units  전개 유닛 [['product_id','name','spec'=>array]]
     * @return array{findings: array, passed: array, paths: array}
     */
    public static function linkCheck(string $family, array $units): array
    {
        [$outField, $inField] = $family === 'video' ? ['vOut', 'vIn'] : ['aOut', 'aIn'];
        $findings = [];
        $passed = [];
        $paths = [];

        // 출력 목록 — 유닛 순서대로 전개, 제품별 순번 부여
        $outputs = [];
        $seqByProduct = [];
        foreach ($units as $unit) {
            $spec = $unit['spec'];
            if (! isset($spec[$outField])) {
                continue;
            }
            $types = (array) ($spec[$outField]['t'] ?? []);
            // USB 출력 마이크는 믹서가 필요 없음 — 검사 제외 (6.3)
            if ($family === 'audio' && in_array('USB', $types, true)) {
                continue;
            }
            $n = max(1, (int) ($spec[$outField]['n'] ?? 1));
            for ($i = 0; $i < $n; $i++) {
                $seq = $seqByProduct[$unit['product_id']] ?? 0;
                $seqByProduct[$unit['product_id']] = $seq + 1;
                $outputs[] = ['product_id' => $unit['product_id'], 'name' => $unit['name'], 'seq' => $seq, 'types' => $types];
            }
        }
        if ($outputs === []) {
            return ['findings' => [], 'passed' => [], 'paths' => []];
        }

        // 입력 풀 — 받는 규격들과 남은 수
        $inputs = [];
        foreach ($units as $unit) {
            $spec = $unit['spec'];
            if (! isset($spec[$inField])) {
                continue;
            }
            $inputs[] = [
                'product_id' => $unit['product_id'], 'name' => $unit['name'],
                'types' => (array) ($spec[$inField]['t'] ?? []),
                'remaining' => max(1, (int) ($spec[$inField]['n'] ?? 1)),
            ];
        }

        // 연결 풀 — cable 먼저 탐색하도록 정렬. kind none은 연결품이 아님 (6.3)
        $connectors = [];
        foreach ($units as $unit) {
            $cable = $unit['spec']['cable'] ?? null;
            if (! $cable || ($cable['kind'] ?? 'none') === 'none') {
                continue;
            }
            $connectors[] = [
                'product_id' => $unit['product_id'], 'name' => $unit['name'],
                'a' => (string) $cable['a'], 'b' => (string) $cable['b'],
                'kind' => (string) $cable['kind'], 'remaining' => 1,
            ];
        }
        usort($connectors, fn ($x, $y) => ($x['kind'] === 'cable' ? 0 : 1) <=> ($y['kind'] === 'cable' ? 0 : 1));

        $hasAnyInputDevice = $inputs !== [];
        $sinkLabel = $family === 'video' ? '캡처보드' : '믹서';

        foreach ($outputs as $output) {
            $path = self::findPath($output['types'], $inputs, $connectors, [], false);
            if ($path !== null) {
                // 성공 — 사용 자원 차감 (탐욕, 6.3)
                $inputs[$path['input']]['remaining']--;
                foreach ($path['used'] as $ci) {
                    $connectors[$ci]['remaining']--;
                }
                $passed[] = ['check' => $family, 'title' => "{$output['name']} → {$inputs[$path['input']]['name']} 연결"];
                $paths[] = [
                    'family' => $family, 'from' => $output['name'],
                    'via' => array_map(fn ($ci) => $connectors[$ci]['name'], $path['used']),
                    'to' => $inputs[$path['input']]['name'],
                ];

                continue;
            }

            if (! $hasAnyInputDevice) {
                $reason = 'nosink';
                $title = "{$sinkLabel} 없음";
            } elseif (! array_filter($inputs, fn ($in) => $in['remaining'] > 0)) {
                $reason = 'full';
                $title = "{$sinkLabel} 입력 부족";
            } else {
                $reason = 'nopath';
                $title = $output['seq'] > 0 ? ($output['seq'] + 1).'번째 연결 끊김 — '.$output['name'] : "{$output['name']} 연결 끊김";
            }
            $findings[] = [
                'key' => "{$family}:{$output['product_id']}:{$output['seq']}:{$reason}",
                'kind' => 'link', 'group' => $family === 'video' ? '영상 연결' : '오디오 연결',
                'title' => $title, 'product_id' => $output['product_id'], 'reason' => $reason,
            ];
        }

        return ['findings' => $findings, 'passed' => $passed, 'paths' => $paths];
    }

    /**
     * 깊이 우선 탐색 — 현재 단자들에서 남은 입력에 닿고 케이블을 1개 이상 거쳤으면 성공 (6.3).
     * 연결품은 양방향, 최대 3개.
     *
     * @param  array<int, string>  $terminals
     * @return array{input:int, used: array<int,int>}|null
     */
    private static function findPath(array $terminals, array $inputs, array $connectors, array $used, bool $usedCable): ?array
    {
        if ($usedCable) {
            foreach ($inputs as $idx => $input) {
                if ($input['remaining'] > 0 && array_intersect($terminals, $input['types'])) {
                    return ['input' => $idx, 'used' => $used];
                }
            }
        }
        if (count($used) >= self::MAX_CONNECTORS) {
            return null;
        }
        foreach ($connectors as $ci => $connector) {
            if ($connector['remaining'] <= 0 || in_array($ci, $used, true)) {
                continue;
            }
            $next = null;
            if (in_array($connector['a'], $terminals, true)) {
                $next = $connector['b'];
            } elseif (in_array($connector['b'], $terminals, true)) {
                $next = $connector['a'];
            }
            if ($next === null || $next === '') {
                continue;
            }
            $found = self::findPath([$next], $inputs, $connectors, [...$used, $ci], $usedCable || $connector['kind'] === 'cable');
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * 렌즈 검사 (6.4) — 카메라→렌즈는 대당 차감, 렌즈→카메라는 차감 없음.
     *
     * @return array{findings: array, passed: array}
     */
    public static function lensCheck(array $units): array
    {
        $findings = [];
        $passed = [];

        $lensPool = [];
        foreach ($units as $i => $unit) {
            if (isset($unit['spec']['lensMount'])) {
                $lensPool[$i] = ['unit' => $unit, 'remaining' => 1, 'mounts' => (array) $unit['spec']['lensMount']];
            }
        }
        $hasLens = $lensPool !== [];

        // 카메라 → 렌즈 (대당 1개 차감)
        $unitSeq = [];
        foreach ($units as $unit) {
            $spec = $unit['spec'];
            if (! isset($spec['lensTake'])) {
                continue;
            }
            if (! empty($spec['lensIncl'])) {
                continue; // KIT — 건너뜀
            }
            $seq = $unitSeq[$unit['product_id']] ?? 0;
            $unitSeq[$unit['product_id']] = $seq + 1;
            $take = (array) $spec['lensTake'];
            $matched = false;
            foreach ($lensPool as $li => $lens) {
                if ($lens['remaining'] > 0 && array_intersect($take, $lens['mounts'])) {
                    $lensPool[$li]['remaining']--;
                    $matched = true;
                    break;
                }
            }
            if ($matched) {
                $passed[] = ['check' => 'lens', 'title' => "{$unit['name']} 렌즈 확인"];
            } else {
                $findings[] = [
                    'key' => "lens:{$unit['product_id']}:{$seq}",
                    'kind' => $hasLens ? 'fit' : 'miss',
                    'group' => '렌즈',
                    'title' => "{$unit['name']}에 맞는 렌즈 없음",
                    'product_id' => $unit['product_id'],
                ];
            }
        }

        // 렌즈 → 카메라 (차감 없음 — 카메라 1대에 렌즈 여러 개는 정상)
        $cameraMounts = [];
        $hasCamera = false;
        foreach ($units as $unit) {
            if (isset($unit['spec']['lensTake'])) {
                $hasCamera = true;
                $cameraMounts = [...$cameraMounts, ...(array) $unit['spec']['lensTake']];
            }
        }
        if ($hasCamera) {
            $seen = [];
            foreach ($lensPool as $lens) {
                $pid = $lens['unit']['product_id'];
                if (isset($seen[$pid])) {
                    continue; // 같은 렌즈 여러 개는 1건
                }
                $seen[$pid] = true;
                if (array_intersect($lens['mounts'], $cameraMounts)) {
                    $passed[] = ['check' => 'lens', 'title' => "{$lens['unit']['name']} 호환 확인"];
                } else {
                    $findings[] = [
                        'key' => "lensfit:{$pid}",
                        'kind' => 'fit', 'group' => '렌즈',
                        'title' => "{$lens['unit']['name']} 호환 안 됨",
                        'product_id' => $pid,
                    ];
                }
            }
        }

        return ['findings' => $findings, 'passed' => $passed];
    }

    /**
     * 배터리 검사 (6.5) — 배터리→카메라는 fit, 카메라→배터리는 ask(장시간 사용). 차감 없음.
     *
     * @return array{findings: array, passed: array}
     */
    public static function batteryCheck(array $units): array
    {
        $findings = [];
        $passed = [];

        $cameraTakes = [];
        foreach ($units as $unit) {
            if (isset($unit['spec']['batTake'])) {
                $cameraTakes[$unit['product_id']] = ['name' => $unit['name'], 'takes' => (array) $unit['spec']['batTake']];
            }
        }
        $batteryFits = [];
        foreach ($units as $unit) {
            if (isset($unit['spec']['batFit'])) {
                $batteryFits[$unit['product_id']] = ['name' => $unit['name'], 'fits' => (array) $unit['spec']['batFit']];
            }
        }

        // 배터리 → 카메라: 맞는 카메라가 없으면 fit (카메라가 하나라도 있을 때만)
        if ($cameraTakes !== []) {
            $allTakes = array_merge(...array_map(fn ($c) => $c['takes'], array_values($cameraTakes)));
            foreach ($batteryFits as $pid => $battery) {
                if (array_intersect($battery['fits'], $allTakes)) {
                    $passed[] = ['check' => 'battery', 'title' => "{$battery['name']} 호환 확인"];
                } else {
                    $findings[] = [
                        'key' => "batfit:{$pid}",
                        'kind' => 'fit', 'group' => '배터리',
                        'title' => "{$battery['name']} 호환 안 됨",
                        'product_id' => $pid,
                    ];
                }
            }
        }

        // 카메라 → 배터리: 필수가 아님 — 맞는 배터리가 없으면 장시간 사용 여부 ask (차감 없음)
        foreach ($cameraTakes as $pid => $camera) {
            $matched = false;
            foreach ($batteryFits as $battery) {
                if (array_intersect($camera['takes'], $battery['fits'])) {
                    $matched = true;
                    break;
                }
            }
            if (! $matched) {
                $findings[] = [
                    'key' => "batlong:{$pid}",
                    'kind' => 'ask', 'group' => '배터리',
                    'title' => "{$camera['name']} 장시간 사용 시 더미 배터리가 필요합니다",
                    'choices' => ['장시간 사용 아님', '고객 보유'],
                    'product_id' => $pid,
                ];
            }
        }

        return ['findings' => $findings, 'passed' => $passed];
    }

    /**
     * 조명 마운트 검사 (6.6) — 부속마다 맞는 조명이 하나라도 있으면 통과 (조명 차감 없음).
     * warnIf: 어댑터가 있어야 맞는 조합은 통과 + ask.
     *
     * @return array{findings: array, passed: array}
     */
    public static function mountCheck(array $units): array
    {
        $findings = [];
        $passed = [];

        $lights = [];
        foreach ($units as $unit) {
            if (isset($unit['spec']['mntTake'])) {
                $lights[$unit['product_id']] = [
                    'name' => $unit['name'],
                    'takes' => (array) $unit['spec']['mntTake'],
                    'warnIf' => $unit['spec']['warnIf'] ?? null,
                ];
            }
        }
        if ($lights === []) {
            return ['findings' => [], 'passed' => []];
        }

        $seen = [];
        foreach ($units as $unit) {
            $pid = $unit['product_id'];
            if (! isset($unit['spec']['mntFit']) || isset($seen[$pid])) {
                continue;
            }
            $seen[$pid] = true;
            $fits = (array) $unit['spec']['mntFit'];

            $plainMatch = false;   // warnIf 조건 없이 맞는 조명 존재
            $warnMatches = [];     // warnIf 값으로만 맞는 조명들
            foreach ($lights as $lightPid => $light) {
                $overlap = array_intersect($fits, $light['takes']);
                if (! $overlap) {
                    continue;
                }
                $warn = $light['warnIf'];
                if ($warn && ($warn['field'] ?? '') === 'mntFit'
                    && count($overlap) === count(array_intersect($overlap, [(string) ($warn['value'] ?? '')]))) {
                    $warnMatches[$lightPid] = $warn; // 겹침이 전부 warnIf 값뿐 — 어댑터 필요 조합
                } else {
                    $plainMatch = true;
                }
            }

            if ($plainMatch) {
                $passed[] = ['check' => 'mount', 'title' => "{$unit['name']} 마운트 확인"];
            } elseif ($warnMatches !== []) {
                $passed[] = ['check' => 'mount', 'title' => "{$unit['name']} 마운트 확인 (어댑터 전제)"];
                foreach ($warnMatches as $lightPid => $warn) {
                    $findings[] = [
                        'key' => "mountadp:{$lightPid}:{$pid}",
                        'kind' => 'ask', 'group' => '조명 마운트',
                        'title' => (string) ($warn['title'] ?? '어댑터 포함 여부를 확인하세요'),
                        'desc' => (string) ($warn['desc'] ?? ''),
                        'choices' => ['어댑터 포함됨', '고객 보유'],
                        'product_id' => $pid,
                    ];
                }
            } else {
                $lightIds = array_keys($lights);
                sort($lightIds);
                $findings[] = [
                    'key' => 'mount:'.$pid.':'.implode(',', $lightIds),
                    'kind' => 'fit', 'group' => '조명 마운트',
                    'title' => "{$unit['name']} 호환 안 됨",
                    'product_id' => $pid,
                ];
            }
        }

        return ['findings' => $findings, 'passed' => $passed];
    }
}
