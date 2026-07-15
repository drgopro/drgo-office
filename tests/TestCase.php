<?php

namespace Tests;

use App\Models\CalendarCategory;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 요청 내 정적 캐시가 테스트 간 누수되지 않도록 초기화 (RefreshDatabase는 static을 비우지 않음)
        CalendarCategory::clearMapCache();
    }
}
