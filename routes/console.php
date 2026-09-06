<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 일정 시작 전 웹푸시 알림 (매분)
Schedule::command('schedules:notify')->everyMinute()->withoutOverlapping();
Schedule::command('schedules:remind-stale')->dailyAt('09:10'); // 지난 미완료 일정 상태 정리 리마인드 (경과 1·3·7일차)

// 미배송 송장 배송상태 갱신 (30분 주기)
Schedule::command('shipments:refresh')->everyThirtyMinutes()->withoutOverlapping();

// drgo.pro 게시판 새 글/답변/댓글 감시 → 채널톡 알림 (5분 주기)
Schedule::command('drgo:watch-boards')->everyFiveMinutes()->withoutOverlapping();

// 컴퓨존 시세 자동 갱신 (매일 새벽 5시 30분)
Schedule::command('products:refresh-market-prices')->dailyAt('05:30')->withoutOverlapping();

// 채널톡 팀챗 일정 알림 — 2일 뒤 일정을 매일 아침 9시 발송 (미설정 시 자동 건너뜀)
Schedule::command('schedules:channeltalk-digest')->dailyAt('09:00')->withoutOverlapping();

// 할 일 리마인드 — 마감 D-1·경과 미완료 할 일을 완료 안 한 담당자에게 매일 아침 멘션
Schedule::command('todos:remind')->dailyAt('09:00')->withoutOverlapping();

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
