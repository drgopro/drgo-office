<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 일정 시작 전 웹푸시 알림 (매분)
Schedule::command('schedules:notify')->everyMinute()->withoutOverlapping();

// 미배송 송장 배송상태 갱신 (30분 주기)
Schedule::command('shipments:refresh')->everyThirtyMinutes()->withoutOverlapping();
