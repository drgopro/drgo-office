<?php

use App\Models\AgentEvent;
use App\Services\MattermostClient;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 채널톡으로만 발송하는 작업은 연동 스위치(CHANNELTALK_ENABLED)가 켜져 있을 때만 실행 — 채널톡 해지로 기본 꺼짐
$channelTalkOn = fn (): bool => (bool) config('services.channeltalk.enabled');

// 일정 시작 전 웹푸시 알림 (매분)
Schedule::command('schedules:notify')->everyMinute()->withoutOverlapping();
Schedule::command('schedules:remind-stale')->dailyAt('09:10'); // 지난 미완료 일정 상태 정리 리마인드 (경과 1·3·7일차)

// 미배송 송장 배송상태 갱신 (30분 주기)
Schedule::command('shipments:refresh')->everyThirtyMinutes()->withoutOverlapping();

// drgo.pro 게시판 새 글/답변/댓글 감시 → 채널톡 알림 (1분 주기 — 등록 후 1분 내 도착).
// 1코어 서버 시절 CPU 포화로 2분으로 늘렸던 것을, 2코어(Vultr) 이전 후 다시 1분으로
Schedule::command('drgo:watch-boards')->everyMinute()->withoutOverlapping(10)->when($channelTalkOn);

// 컴퓨존 시세 자동 갱신 (매일 새벽 5시 30분)
Schedule::command('products:refresh-market-prices')->dailyAt('05:30')->withoutOverlapping();

// D-2 일정 알림 — 2일 뒤 방문·원격 일정을 매일 아침 9시 Mattermost(웹훅 설정 시)·채널톡으로 발송
Schedule::command('schedules:channeltalk-digest')->dailyAt('09:00')->withoutOverlapping()
    ->when(fn (): bool => $channelTalkOn() || MattermostClient::isValidWebhook(MattermostClient::webhook())); // Mattermost 또는 채널톡

// 할 일 리마인드 — 마감 D-1·경과 미완료 할 일을 완료 안 한 담당자에게 매일 아침 멘션
Schedule::command('todos:remind')->dailyAt('09:00')->withoutOverlapping()->when($channelTalkOn);

// 마케팅 활용 동의/거부 변경 다이제스트 — 전날 동의·거부로 확정된 프로젝트 목록을 아웃바운드 방으로
Schedule::command('marketing:consent-digest')->dailyAt('09:05')->withoutOverlapping()->when($channelTalkOn);

// 진행중 렌탈·방송룸 계약의 결제 반복 일정 자동 연장 (매월 1일)
Schedule::command('contracts:sync-calendar --force')->monthlyOn(1, '03:00')->withoutOverlapping();

// 서버 디스크 사용률 점검 — 80% 초과 시 관리자 알림 (매일 오전 8시)
Schedule::command('disk:check')->dailyAt('08:00')->withoutOverlapping();

// SSL 인증서 만료 감시 — 만료 7일 전부터 매일 관리자 알림(종) + 만료일 캘린더 등록
Schedule::command('ssl:check-expiry')->dailyAt('08:10')->withoutOverlapping();

// 한국 공휴일(대체공휴일 포함) 자동 동기화 — 주 1회면 충분 (정부 발표 반영 지연 대비)
Schedule::command('holidays:sync')->weeklyOn(1, '03:20')->withoutOverlapping();

// DB 백업 — mysqldump gzip, 14일 보관 (매일 새벽 3시 30분)
Schedule::command('db:backup')->dailyAt('03:30')->withoutOverlapping();

// 고아 첨부파일 정리 — 부모가 영구 삭제된 첨부·미참조 디스크 파일 (매주 월요일 새벽 4시)
Schedule::command('attachments:prune-orphans')->weeklyOn(1, '04:00')->withoutOverlapping();

// 위키 임시저장 정리 — 7일 지난 초안 삭제 (매일 새벽 4시 15분)
Schedule::command('wiki:prune-drafts')->dailyAt('04:15')->withoutOverlapping();

// 기간 지난 제품 할인 자동 해제 — 할인 필드 초기화 + 이력에 '기간 종료' 기록 (30분마다)
Schedule::command('products:expire-discounts')->everyThirtyMinutes()->withoutOverlapping();

// 발행 24시간 가격 보장 — 미결제 발행 건을 현재 가격으로 재갱신 (금액 변경 시 페이앱 재요청 + 채널톡 알림)
Schedule::command('estimates:refresh-stale-issued')->hourly()->withoutOverlapping();

// AI 에이전트 작업 이벤트 정리 — 90일 지난 기록 삭제 (매일 새벽 4시 30분)
Schedule::call(fn () => AgentEvent::where('occurred_at', '<', now()->subDays(90))->delete())->dailyAt('04:30')->name('agent-events:prune');
