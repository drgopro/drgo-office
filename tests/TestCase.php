<?php

namespace Tests;

use App\Models\CalendarCategory;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 요청 내 정적 캐시가 테스트 간 누수되지 않도록 초기화 (RefreshDatabase는 static을 비우지 않음)
        CalendarCategory::clearMapCache();

        // sqlite에는 REGEXP 함수가 없음 — 운영(MySQL)과 동일하게 동작하도록 등록 (SKU 생성 쿼리 등)
        $connection = DB::connection();
        if ($connection->getDriverName() === 'sqlite') {
            $connection->getPdo()->sqliteCreateFunction(
                'regexp',
                fn ($pattern, $value) => preg_match('/'.$pattern.'/u', (string) $value) === 1 ? 1 : 0
            );
            // FLOOR — PHP 내장 sqlite에 수학 함수가 없을 수 있음 (재고 필터의 세트 조립 가능 수 계산)
            $connection->getPdo()->sqliteCreateFunction('floor', fn ($v) => floor((float) $v));
            $connection->getPdo()->sqliteCreateFunction(
                'substring_index',
                function ($value, $delim, $count) {
                    $parts = explode((string) $delim, (string) $value);

                    return $count >= 0
                        ? implode($delim, array_slice($parts, 0, (int) $count))
                        : implode($delim, array_slice($parts, (int) $count));
                }
            );
        }
    }
}
