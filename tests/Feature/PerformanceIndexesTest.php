<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 반복 조회 경로용 복합 인덱스가 마이그레이션으로 생성되는지 확인한다.
 */
class PerformanceIndexesTest extends TestCase
{
    use RefreshDatabase;

    public function test_performance_indexes_exist(): void
    {
        $this->assertTrue(Schema::hasIndex('activity_logs', ['loggable_type', 'action', 'created_at']));
        $this->assertTrue(Schema::hasIndex('estimates', ['status', 'created_at']));
        $this->assertTrue(Schema::hasIndex('schedule_shipments', ['status', 'created_at']));
        $this->assertTrue(Schema::hasIndex('product_market_prices', ['checked_at']));
    }
}
