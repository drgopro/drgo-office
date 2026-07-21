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

// 진행중 렌탈·방송룸 계약의 결제 반복 일정 자동 연장 (매월 1일)
Schedule::command('contracts:sync-calendar --force')->monthlyOn(1, '03:00')->withoutOverlapping();

// 서버 디스크 사용률 점검 — 80% 초과 시 관리자 알림 (매일 오전 8시)
Schedule::command('disk:check')->dailyAt('08:00')->withoutOverlapping();

// DB 백업 — mysqldump gzip, 14일 보관 (매일 새벽 3시 30분)
Schedule::command('db:backup')->dailyAt('03:30')->withoutOverlapping();

// 고아 첨부파일 정리 — 부모가 영구 삭제된 첨부·미참조 디스크 파일 (매주 월요일 새벽 4시)
Schedule::command('attachments:prune-orphans')->weeklyOn(1, '04:00')->withoutOverlapping();

// 위키 임시저장 정리 — 7일 지난 초안 삭제 (매일 새벽 4시 15분)
Schedule::command('wiki:prune-drafts')->dailyAt('04:15')->withoutOverlapping();
