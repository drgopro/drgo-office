<?php

namespace App\Services;

use DateTimeImmutable;

/**
 * 캘린더 대기함 계산 (명세 5~7장) — DB를 모르는 순수 함수.
 * 화면 표시와 서버 배치 검사가 이 한 곳을 같이 쓴다. '오늘'은 항상 인자로 받는다.
 * 시스템은 사실을 계산해 보여줄 뿐, 날짜·시간·담당자를 고르거나 순위를 매기지 않는다.
 *
 * @phpstan-type Line array{product_id:int, name:string, qty:int, stock:int, reserved?:int, lead_days:?int, check?:?string, answer?:?array{ok:bool, expected_date?:?string}}
 * @phpstan-type Bench array{kind:string, created_on:string, due_date?:?string, window_type:string, window_from?:?string, window_to?:?string, blocked?:bool}
 */
class BenchCalculator
{
    /** 이 일수 안에 준비되면 '3일 내 준비 가능' */
    public const PREP_DAYS = 3;

    public const CHECK_IN_WAREHOUSE = 'in_warehouse';

    public const CHECK_MISSING = 'missing';

    /**
     * 제품 준비 계산 (5.1 줄 단위, 5.2 전체 상태).
     *
     * @param  array<int, Line>|null  $lines  연결 견적서 품목 — 견적서 미연결이면 null
     * @return array{state:string, rows:array<int, array<string, mixed>>, max:?int, ready_on:?string, unconfirmed:array<int, string>, label:string}
     */
    public static function prep(?array $lines, string $today): array
    {
        if ($lines === null) {
            return ['state' => 'none', 'rows' => [], 'max' => null, 'ready_on' => null, 'unconfirmed' => [], 'label' => '견적서 미연결'];
        }

        $rows = array_map(fn (array $line): array => self::prepRow($line, $today), $lines);
        $unconfirmed = array_values(array_map(
            fn (array $r): string => $r['name'],
            array_filter($rows, fn (array $r): bool => $r['status'] === 'stock' && ! $r['confirmed'])
        ));
        $statuses = array_column($rows, 'status');

        $max = null;
        $readyOn = null;
        if (in_array('ask', $statuses, true)) {
            $state = 'ask';
        } elseif (in_array('nodate', $statuses, true)) {
            $state = 'nodate';
        } else {
            $max = max([0, ...array_map(fn (array $r): int => (int) $r['days'], $rows)]); // 준비할 제품이 없는 견적서면 0
            $readyOn = self::addDays($today, $max);
            $state = $max === 0 ? 'stock' : ($max <= self::PREP_DAYS ? 'ok' : 'late');
        }

        $label = [
            'stock' => '재고로 충당',
            'ok' => '3일 내 준비 가능',
            'late' => '3일 내 준비 불가 · '.($readyOn ? self::md($readyOn) : '').' 준비 예상',
            'ask' => '준비 확인 필요',
            'nodate' => '준비 불가 · 준비일 미정',
        ][$state].($unconfirmed ? ' · 창고 확인 전 '.count($unconfirmed) : '');

        return ['state' => $state, 'rows' => $rows, 'max' => $max, 'ready_on' => $readyOn, 'unconfirmed' => $unconfirmed, 'label' => $label];
    }

    /**
     * @param  Line  $line
     * @return array<string, mixed>
     */
    private static function prepRow(array $line, string $today): array
    {
        $qty = (int) $line['qty'];
        $free = max(0, (int) $line['stock'] - (int) ($line['reserved'] ?? 0));
        $check = $line['check'] ?? null;
        $answer = $line['answer'] ?? null;
        $base = ['product_id' => $line['product_id'], 'name' => $line['name'], 'qty' => $qty, 'free' => $free];

        if ($free >= $qty && $check !== self::CHECK_MISSING) {
            return $base + ['status' => 'stock', 'days' => 0, 'source' => 'stock', 'missing' => false, 'confirmed' => $check === self::CHECK_IN_WAREHOUSE];
        }

        // 재고 부족 또는 '실제 없음' — 주문 경로. 주문 소요일이 없을 때만 담당자 답변으로 계산
        $days = $line['lead_days'] ?? null;
        $source = 'order';
        if ($days === null && $answer !== null) {
            $source = 'staff';
            if ($answer['ok']) {
                $days = self::PREP_DAYS;
            } elseif (! empty($answer['expected_date'])) {
                $days = max(1, self::diffDays($answer['expected_date'], $today));
            }
        }
        $status = $days === null
            ? ($answer !== null && ! $answer['ok'] ? 'nodate' : 'ask')
            : ($days <= self::PREP_DAYS ? 'ok' : 'late');

        return $base + ['status' => $status, 'days' => $days, 'source' => $source, 'missing' => $free >= $qty, 'confirmed' => false];
    }

    /**
     * 대기 일정 한 건의 계산 결과 전체 — 준비 상태, 배치 가능일(5.3), 단계·표시(6장).
     *
     * @param  Bench  $bench
     * @param  array<int, Line>|null  $lines
     * @return array<string, mixed>
     */
    public static function evaluate(array $bench, ?array $lines, string $today): array
    {
        $prep = self::prep($lines, $today);
        $prepStart = match ($prep['state']) {
            'ok' => self::addDays($today, self::PREP_DAYS + 1),
            'late' => self::addDays($today, (int) $prep['max'] + 1),
            default => self::addDays($today, 1),
        };

        $earliest = $prepStart;
        $windowFrom = $bench['window_from'] ?? null;
        if ($bench['window_type'] !== 'asap' && $windowFrom && $windowFrom > $earliest) {
            $earliest = $windowFrom;
        }
        $latest = $bench['due_date'] ?? null;
        $windowTo = $bench['window_to'] ?? null;
        if ($bench['window_type'] === 'between' && $windowTo && (! $latest || $windowTo < $latest)) {
            $latest = $windowTo;
        }
        $impossible = $latest !== null && $earliest > $latest;

        $result = [
            'prep' => $prep,
            'prep_start' => $prepStart,
            'earliest' => $earliest,
            'latest' => $latest,
            'impossible' => $impossible,
            'blocked' => ! empty($bench['blocked']),
            'range_label' => $latest ? self::mdw($earliest).' ~ '.self::mdw($latest) : self::mdw($earliest).'부터',
            'window_label' => self::windowLabel($bench),
            'wait_days' => self::diffDays($today, $bench['created_on']),
        ];

        return $result + self::urgency($bench, $prep['state'], $impossible, $today);
    }

    /**
     * 우선순위 단계와 표시 (6장). 단계 1 이하가 '긴급'.
     *
     * @param  Bench  $bench
     * @return array{level:float, flag:string, urgent:bool}
     */
    private static function urgency(array $bench, string $prepState, bool $impossible, string $today): array
    {
        $due = $bench['due_date'] ?? null;
        [$level, $flag] = match (true) {
            ! empty($bench['blocked']) => [9, '조건 대기'],
            $prepState === 'ask' => [1.5, '제품 준비 확인 필요'],
            $prepState === 'nodate' => [8, '제품 준비일 미정'],
            // 명세 6장 표 순서 — 기한이 지났으면 '가능한 날짜 없음'보다 '기한 지남'을 먼저 보여 준다
            $due !== null && $due < $today => self::dueUrgency($due, $today),
            $impossible => [0, in_array($prepState, ['ok', 'late'], true) ? '기한 안에 제품 준비 불가' : '가능한 날짜 없음'],
            $due !== null => self::dueUrgency($due, $today),
            default => [3, ''],
        };

        return ['level' => (float) $level, 'flag' => $flag, 'urgent' => $level <= 1];
    }

    /** @return array{0:int, 1:string} */
    private static function dueUrgency(string $due, string $today): array
    {
        $remain = self::diffDays($due, $today);
        if ($remain < 0) {
            return [0, '기한 지남 '.(-$remain).'일'];
        }
        if ($remain <= 3) {
            return [1, $remain === 0 ? '오늘 기한' : '기한 D-'.$remain];
        }

        return [2, '기한 '.self::md($due)];
    }

    /**
     * 정렬 (6장): 단계 → 기한 빠른 순 → 재방문 먼저 → 대기 시작일 오래된 순.
     * 각 항목은 kind, due_date, created_on, level을 가진다.
     *
     * @template T of array<string, mixed>
     *
     * @param  array<int, T>  $items
     * @return array<int, T>
     */
    public static function sort(array $items): array
    {
        usort($items, function (array $a, array $b): int {
            if ($a['level'] !== $b['level']) {
                return $a['level'] <=> $b['level'];
            }
            if (! empty($a['due_date']) && ! empty($b['due_date']) && $a['due_date'] !== $b['due_date']) {
                return $a['due_date'] <=> $b['due_date'];
            }
            if ($a['kind'] !== $b['kind'] && ($a['kind'] === 'revisit' || $b['kind'] === 'revisit')) {
                return $a['kind'] === 'revisit' ? -1 : 1;
            }

            return $a['created_on'] <=> $b['created_on'];
        });

        return $items;
    }

    /**
     * 같은 지역 일정의 날짜가 배치 가능 기간의 어디에 있는지 (7.3).
     *
     * @param  array<string, mixed>  $evaluation  evaluate() 결과
     * @return 'ok'|'lead'|'early'|'late'
     */
    public static function dateStatus(array $evaluation, string $date): string
    {
        if ($date < $evaluation['earliest']) {
            return $date < $evaluation['prep_start'] ? 'lead' : 'early';
        }

        return $evaluation['latest'] !== null && $date > $evaluation['latest'] ? 'late' : 'ok';
    }

    /**
     * 배치 검사 (7.4) — 차단(넘어갈 수 없음) / 알림(사유 적으면 진행) / 안내(막지 않음).
     * 담당자 휴가 안내는 DB가 필요해 호출하는 쪽에서 덧붙인다.
     *
     * @param  Bench  $bench
     * @param  array<string, mixed>  $evaluation  evaluate() 결과
     * @return array{error:?string, alerts:array<int, string>, notices:array<int, string>}
     */
    public static function checkPlacement(array $bench, array $evaluation, string $date, string $today): array
    {
        if ($date <= $today) {
            return ['error' => '내일 이후 날짜를 골라 주세요.', 'alerts' => [], 'notices' => []];
        }
        if ($evaluation['blocked']) {
            return ['error' => '배치 전 조건이 남아 있습니다. 조건을 해결한 뒤 배치해 주세요.', 'alerts' => [], 'notices' => []];
        }

        $prep = $evaluation['prep'];
        $names = fn (string $status): string => implode(', ', array_map(
            fn (array $r): string => $r['name'],
            array_filter($prep['rows'], fn (array $r): bool => $r['status'] === $status)
        ));
        $alerts = [];
        if ($prep['state'] === 'ask') {
            $alerts[] = '준비 여부 미확인: '.$names('ask');
        } elseif ($prep['state'] === 'nodate') {
            $alerts[] = '준비 예상일 없음: '.$names('nodate');
        } elseif ($date < $evaluation['prep_start']) {
            $alerts[] = ($prep['state'] === 'ok' ? '3일 내 준비 가능' : '3일 내 준비 불가').' 일정이라 '.self::mdw($evaluation['prep_start']).'부터 배치할 수 있습니다.';
        }
        if ($prep['unconfirmed']) {
            $alerts[] = '창고 실재고 확인 전: '.implode(', ', $prep['unconfirmed']);
        }

        $notices = [];
        $due = $bench['due_date'] ?? null;
        if ($due && $date > $due) {
            $notices[] = '기한 '.self::md($due).'을 넘깁니다.';
        }
        if ($bench['window_type'] !== 'asap' && ! empty($bench['window_from']) && $date < $bench['window_from']) {
            $notices[] = '고객 희망('.self::windowLabel($bench).')보다 빠릅니다.';
        }
        if ($bench['window_type'] === 'between' && ! empty($bench['window_to']) && $date > $bench['window_to']) {
            $notices[] = '고객 희망('.self::windowLabel($bench).')보다 늦습니다.';
        }

        return ['error' => null, 'alerts' => $alerts, 'notices' => $notices];
    }

    /** @param  Bench  $bench */
    public static function windowLabel(array $bench): string
    {
        return match ($bench['window_type']) {
            'after' => self::md((string) $bench['window_from']).' 이후 희망',
            'between' => self::md((string) $bench['window_from']).'~'.self::md((string) $bench['window_to']).' 희망',
            default => '빠른 일정 희망',
        };
    }

    public static function addDays(string $date, int $days): string
    {
        return (new DateTimeImmutable($date))->modify(($days >= 0 ? '+' : '').$days.' days')->format('Y-m-d');
    }

    /** $a − $b (일) */
    public static function diffDays(string $a, string $b): int
    {
        return (int) (new DateTimeImmutable($b))->diff(new DateTimeImmutable($a))->format('%r%a');
    }

    /** 11/21 */
    public static function md(string $date): string
    {
        return (new DateTimeImmutable($date))->format('n/j');
    }

    /** 11/21(토) */
    public static function mdw(string $date): string
    {
        $d = new DateTimeImmutable($date);

        return $d->format('n/j').'('.['일', '월', '화', '수', '목', '금', '토'][(int) $d->format('w')].')';
    }
}
