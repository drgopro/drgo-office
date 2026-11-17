<?php

namespace Tests\Unit;

use App\Services\BenchCalculator;
use PHPUnit\Framework\TestCase;

/**
 * 캘린더 대기함 계산 — 명세 10장 계산 11케이스 + 7.4 배치 검사.
 * 순수 함수 테스트 (DB 없음). 오늘 = 2026-11-17, 재고는 명세 10장 표 그대로.
 */
class BenchCalculatorTest extends TestCase
{
    private const TODAY = '2026-11-17';

    /** 제품명 => [재고, 배정, 주문 소요일] */
    private const STOCK = [
        'Halo' => [1, 1, 2],
        '스탠드' => [6, 2, 2],
        'XLR' => [20, 3, 1],
        'GC311G2' => [3, 1, 2],
        'R8' => [0, 0, 7],
        'HDMI' => [5, 0, 2],
        'AT2035' => [0, 0, null],
        'AG03' => [2, 2, 3],
        'KH-38' => [4, 0, 2],
        'RF50' => [1, 0, 4],
    ];

    private const Q790 = ['R8' => 1, 'RF50' => 1, 'GC311G2' => 1, 'HDMI' => 1, 'AT2035' => 1, 'AG03' => 1, 'XLR' => 1, 'KH-38' => 1];

    /**
     * @param  array<string, int>  $items  제품명 => 수량
     * @param  array<string, string>  $checks  제품명 => 창고 확인
     * @param  array<string, array{ok:bool, expected_date?:?string}>  $answers
     * @return array<int, array<string, mixed>>
     */
    private function lines(array $items, array $checks = [], array $answers = []): array
    {
        $lines = [];
        foreach ($items as $name => $qty) {
            [$stock, $reserved, $lead] = self::STOCK[$name];
            $lines[] = [
                'product_id' => array_search($name, array_keys(self::STOCK), true) + 1,
                'name' => $name, 'qty' => $qty, 'stock' => $stock, 'reserved' => $reserved, 'lead_days' => $lead,
                'check' => $checks[$name] ?? null, 'answer' => $answers[$name] ?? null,
            ];
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $over
     * @return array<string, mixed>
     */
    private function bench(array $over = []): array
    {
        return $over + ['kind' => 'revisit', 'created_on' => '2026-11-15', 'due_date' => null, 'window_type' => 'asap', 'window_from' => null, 'window_to' => null, 'blocked' => false];
    }

    public function test_case_1_order_within_three_days_and_unconfirmed_stock(): void
    {
        $r = BenchCalculator::evaluate($this->bench(), $this->lines(['Halo' => 1, '스탠드' => 1]), self::TODAY);

        $this->assertSame('ok', $r['prep']['state']);
        $this->assertSame('2026-11-21', $r['earliest']);
        $this->assertSame(['스탠드'], $r['prep']['unconfirmed']);
        $this->assertSame('3일 내 준비 가능 · 창고 확인 전 1', $r['prep']['label']);
    }

    public function test_case_2_same_area_dates_inside_and_before_window(): void
    {
        $r = BenchCalculator::evaluate($this->bench(['due_date' => '2026-11-24']), $this->lines(['Halo' => 1, '스탠드' => 1]), self::TODAY);

        $this->assertSame('lead', BenchCalculator::dateStatus($r, '2026-11-20')); // 제품 준비 전
        $this->assertSame('ok', BenchCalculator::dateStatus($r, '2026-11-24'));   // 이 날로
        $this->assertSame('late', BenchCalculator::dateStatus($r, '2026-11-25')); // 기한 이후
    }

    public function test_case_3_stock_with_due_in_two_days(): void
    {
        $r = BenchCalculator::evaluate($this->bench(['due_date' => '2026-11-19']), $this->lines(['GC311G2' => 1]), self::TODAY);

        $this->assertSame('stock', $r['prep']['state']);
        $this->assertSame('2026-11-18', $r['earliest']);
        $this->assertSame(1.0, $r['level']);
        $this->assertSame('기한 D-2', $r['flag']);
        $this->assertTrue($r['urgent']);
    }

    public function test_case_4_missing_in_warehouse_makes_due_impossible(): void
    {
        $lines = $this->lines(['GC311G2' => 1], ['GC311G2' => BenchCalculator::CHECK_MISSING]);
        $r = BenchCalculator::evaluate($this->bench(['due_date' => '2026-11-19']), $lines, self::TODAY);

        $this->assertSame('ok', $r['prep']['state']);
        $this->assertTrue($r['prep']['rows'][0]['missing']);
        $this->assertSame('2026-11-21', $r['earliest']);
        $this->assertTrue($r['impossible']);
        $this->assertSame(0.0, $r['level']);
        $this->assertSame('기한 안에 제품 준비 불가', $r['flag']);
    }

    public function test_case_5_product_without_lead_days_needs_answer(): void
    {
        $bench = $this->bench(['kind' => 'undated', 'window_type' => 'between', 'window_from' => '2026-11-30', 'window_to' => '2026-12-05']);
        $r = BenchCalculator::evaluate($bench, $this->lines(self::Q790), self::TODAY);

        $this->assertSame('ask', $r['prep']['state']);
        $this->assertSame(1.5, $r['level']);
        $this->assertSame('제품 준비 확인 필요', $r['flag']);
        $this->assertFalse($r['urgent']);
    }

    public function test_case_6_staff_says_ready_within_three_days(): void
    {
        $bench = $this->bench(['kind' => 'undated', 'window_type' => 'between', 'window_from' => '2026-11-30', 'window_to' => '2026-12-05']);
        $r = BenchCalculator::evaluate($bench, $this->lines(self::Q790, [], ['AT2035' => ['ok' => true]]), self::TODAY);

        $this->assertSame('late', $r['prep']['state']); // EOS R8 주문 7일
        $this->assertSame('2026-11-24', $r['prep']['ready_on']);
        $this->assertSame('2026-11-30', $r['earliest']); // 희망 시작
        $this->assertSame('2026-12-05', $r['latest']);
    }

    public function test_case_7_staff_says_no_without_date(): void
    {
        $r = BenchCalculator::evaluate($this->bench(), $this->lines(self::Q790, [], ['AT2035' => ['ok' => false]]), self::TODAY);

        $this->assertSame('nodate', $r['prep']['state']);
        $this->assertSame(8.0, $r['level']);
        $this->assertSame('제품 준비일 미정', $r['flag']);
    }

    public function test_case_8_staff_says_no_with_expected_date(): void
    {
        $lines = $this->lines(self::Q790, [], ['AT2035' => ['ok' => false, 'expected_date' => '2026-11-28']]);
        $r = BenchCalculator::evaluate($this->bench(), $lines, self::TODAY);

        $this->assertSame('late', $r['prep']['state']);
        $this->assertSame('2026-11-28', $r['prep']['ready_on']);
        $this->assertSame('3일 내 준비 불가 · 11/28 준비 예상 · 창고 확인 전 5', $r['prep']['label']);
    }

    public function test_case_9_no_estimate(): void
    {
        $r = BenchCalculator::evaluate($this->bench(), null, self::TODAY);

        $this->assertSame('none', $r['prep']['state']);
        $this->assertSame('2026-11-18', $r['earliest']);
        $this->assertSame(3.0, $r['level']);
    }

    public function test_case_10_block_condition_waits_at_the_bottom_and_cannot_be_placed(): void
    {
        $bench = $this->bench(['blocked' => true]);
        $r = BenchCalculator::evaluate($bench, null, self::TODAY);

        $this->assertSame(9.0, $r['level']);
        $this->assertSame('조건 대기', $r['flag']);
        $this->assertNotNull(BenchCalculator::checkPlacement($bench, $r, '2026-11-20', self::TODAY)['error']);
    }

    public function test_case_11_seed_order_and_urgent_count(): void
    {
        $seed = [
            '송파 세팅' => [['kind' => 'hold', 'created_on' => '2026-11-14', 'due_date' => '2026-12-04', 'window_type' => 'after', 'window_from' => '2026-11-20'], ['XLR' => 2, '스탠드' => 2]],
            '수원 재방문' => [['kind' => 'revisit', 'created_on' => '2026-11-15', 'due_date' => '2026-11-24'], ['Halo' => 1, '스탠드' => 1]],
            '레드캣 AS' => [['kind' => 'revisit', 'created_on' => '2026-11-12', 'due_date' => '2026-11-19'], ['GC311G2' => 1]],
            '마포 원룸' => [['kind' => 'undated', 'created_on' => '2026-11-10', 'window_type' => 'between', 'window_from' => '2026-11-30', 'window_to' => '2026-12-05'], self::Q790],
            '인천 교체' => [['kind' => 'hold', 'created_on' => '2026-11-11', 'blocked' => true], null],
            'OBS 원격' => [['kind' => 'undated', 'created_on' => '2026-11-16', 'due_date' => '2026-11-27'], null],
        ];
        $items = [];
        foreach ($seed as $title => [$over, $est]) {
            $bench = $this->bench($over);
            $items[] = $bench + ['title' => $title] + BenchCalculator::evaluate($bench, $est === null ? null : $this->lines($est), self::TODAY);
        }

        $this->assertSame(
            ['레드캣 AS', '마포 원룸', '수원 재방문', 'OBS 원격', '송파 세팅', '인천 교체'],
            array_column(BenchCalculator::sort($items), 'title')
        );
        $this->assertCount(1, array_filter($items, fn (array $i): bool => $i['urgent']));
    }

    public function test_due_labels(): void
    {
        $flag = fn (string $due): string => BenchCalculator::evaluate($this->bench(['due_date' => $due]), null, self::TODAY)['flag'];

        $this->assertSame('기한 지남 2일', $flag('2026-11-15'));
        // 기한이 오늘이면 내일부터만 배치할 수 있어 가능한 날짜가 없다 (시안 동작)
        $this->assertSame('가능한 날짜 없음', $flag('2026-11-17'));
        $this->assertSame('기한 D-1', $flag('2026-11-18'));
        $this->assertSame('기한 D-3', $flag('2026-11-20'));
        $this->assertSame('기한 11/21', $flag('2026-11-21'));
    }

    public function test_placement_blocks_today_and_past_even_with_override(): void
    {
        $bench = $this->bench();
        $r = BenchCalculator::evaluate($bench, null, self::TODAY);

        $this->assertNotNull(BenchCalculator::checkPlacement($bench, $r, '2026-11-17', self::TODAY)['error']);
        $this->assertNotNull(BenchCalculator::checkPlacement($bench, $r, '2026-11-10', self::TODAY)['error']);
        $this->assertSame(['error' => null, 'alerts' => [], 'notices' => []], BenchCalculator::checkPlacement($bench, $r, '2026-11-18', self::TODAY));
    }

    public function test_placement_alerts_until_warehouse_is_confirmed(): void
    {
        $bench = $this->bench();
        $items = ['Halo' => 1, '스탠드' => 1];

        $r = BenchCalculator::evaluate($bench, $this->lines($items), self::TODAY);
        $check = BenchCalculator::checkPlacement($bench, $r, '2026-11-19', self::TODAY);
        $this->assertNull($check['error']);
        $this->assertCount(2, $check['alerts']); // 제품 시작일보다 이름 + 창고 확인 전
        $this->assertStringContainsString('11/21(토)부터', $check['alerts'][0]);
        $this->assertSame('창고 실재고 확인 전: 스탠드', $check['alerts'][1]);

        $r = BenchCalculator::evaluate($bench, $this->lines($items, ['스탠드' => BenchCalculator::CHECK_IN_WAREHOUSE]), self::TODAY);
        $this->assertSame([], BenchCalculator::checkPlacement($bench, $r, '2026-11-21', self::TODAY)['alerts']);
    }

    public function test_placement_alerts_for_unanswered_and_undated_products(): void
    {
        $bench = $this->bench();

        $r = BenchCalculator::evaluate($bench, $this->lines(['AT2035' => 1]), self::TODAY);
        $this->assertSame(['준비 여부 미확인: AT2035'], BenchCalculator::checkPlacement($bench, $r, '2026-11-18', self::TODAY)['alerts']);

        $r = BenchCalculator::evaluate($bench, $this->lines(['AT2035' => 1], [], ['AT2035' => ['ok' => false]]), self::TODAY);
        $this->assertSame(['준비 예상일 없음: AT2035'], BenchCalculator::checkPlacement($bench, $r, '2026-11-18', self::TODAY)['alerts']);
    }

    public function test_placement_notices_do_not_block(): void
    {
        $bench = $this->bench(['due_date' => '2026-12-03', 'window_type' => 'between', 'window_from' => '2026-11-30', 'window_to' => '2026-12-05']);
        $r = BenchCalculator::evaluate($bench, null, self::TODAY);

        $early = BenchCalculator::checkPlacement($bench, $r, '2026-11-25', self::TODAY);
        $this->assertNull($early['error']);
        $this->assertSame([], $early['alerts']);
        $this->assertSame(['고객 희망(11/30~12/5 희망)보다 빠릅니다.'], $early['notices']);

        $late = BenchCalculator::checkPlacement($bench, $r, '2026-12-06', self::TODAY);
        $this->assertSame(['기한 12/3을 넘깁니다.', '고객 희망(11/30~12/5 희망)보다 늦습니다.'], $late['notices']);
    }
}
