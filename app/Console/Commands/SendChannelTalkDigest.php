<?php

namespace App\Console\Commands;

use App\Models\Schedule;
use App\Services\ChannelTalkClient;
use App\Services\ChannelTalkNotifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('schedules:channeltalk-digest {--days=} {--test}')]
#[Description('N일 뒤(기본 2일) 일정 목록을 채널톡 팀챗으로 발송 (매일 아침)')]
class SendChannelTalkDigest extends Command
{
    /** 캘린더 카테고리 색상 → 라벨 (calendar와 동일 매핑) */
    private const COLOR_LABELS = [
        'gold' => '방문의뢰', 'teal' => '원격/방송룸', 'blue' => '사내업무',
        'red' => '휴가/개인', 'green' => '촬영/스튜디오', 'purple' => '미팅/내방',
    ];

    /** 다이제스트 대상 카테고리 — 방문의뢰(gold)·원격/방송룸(teal)만 */
    private const DIGEST_COLORS = ['gold', 'teal'];

    public function handle(ChannelTalkClient $channelTalk, ChannelTalkNotifier $notifier): int
    {
        if (! $channelTalk->isConfigured()) {
            $this->warn('채널톡 연동 정보가 없어 건너뜁니다 (.env CHANNELTALK_*)');

            return self::SUCCESS;
        }

        if ($this->option('test')) {
            $result = $channelTalk->sendGroupMessage('✅ 닥터고블린 오피스 ↔ 채널톡 연동 테스트 메시지입니다.');
            $result['ok'] ? $this->info('테스트 전송 성공') : $this->error($result['error']);

            return $result['ok'] ? self::SUCCESS : self::FAILURE;
        }

        $days = (int) ($this->option('days') ?? config('services.channeltalk.remind_days', 2));
        $target = now()->addDays($days);

        $schedules = Schedule::with('assignees.user')
            ->whereDate('start_date', $target->format('Y-m-d'))
            ->where('is_private', false)
            ->whereIn('color', self::DIGEST_COLORS) // 방문/원격만 아웃바운드
            ->orderBy('start_time')
            ->get();

        if ($schedules->isEmpty()) {
            $this->info("D-{$days} ({$target->format('m/d')}) 일정 없음 — 발송 생략");

            return self::SUCCESS;
        }

        $weekday = ['일', '월', '화', '수', '목', '금', '토'][$target->dayOfWeek];
        $lines = ["📅 D-{$days} 일정 알림 — {$target->format('m/d')} ({$weekday}) {$schedules->count()}건\n"];
        foreach ($schedules as $s) {
            $time = $s->start_time ? substr($s->start_time, 0, 5) : '종일';
            $category = self::COLOR_LABELS[$s->color] ?? '';
            $who = $s->assignees->isNotEmpty() ? $notifier->mentionList($s->assignees) : ''; // 담당자 멘션 → 개인 알림
            $line = "• {$time}".($category ? " [{$category}]" : '')." {$s->title}";
            if ($s->client_name) {
                $line .= " — {$s->client_name}";
            }
            if ($who) {
                $line .= " (담당: {$who})";
            }
            $lines[] = $line;
        }

        $result = $channelTalk->sendGroupMessage(implode("\n", $lines));
        if (! $result['ok']) {
            $this->error($result['error']);

            return self::FAILURE;
        }

        $this->info("발송 완료 — {$schedules->count()}건");

        return self::SUCCESS;
    }
}
