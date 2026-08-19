<?php

namespace App\Console\Commands;

use App\Models\Todo;
use App\Services\ChannelTalkClient;
use App\Services\ChannelTalkNotifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('todos:remind')]
#[Description('마감 임박(D-1)·경과한 미완료 할 일을 완료 체크 안 한 담당자에게 채널톡 멘션으로 리마인드 (매일 아침)')]
class SendTodoReminders extends Command
{
    public function handle(ChannelTalkClient $channelTalk, ChannelTalkNotifier $notifier): int
    {
        if (! $channelTalk->isConfigured()) {
            $this->warn('채널톡 연동 정보가 없어 건너뜁니다 (.env CHANNELTALK_*)');

            return self::SUCCESS;
        }

        $today = now()->startOfDay();
        $todos = Todo::with('assignees', 'assignee', 'checklistItems')
            ->whereNull('completed_at')
            ->whereNull('due_held_at') // 보류 중인 할 일은 리마인드 제외
            ->whereNotNull('due_date')
            ->where('due_date', '<=', $today->copy()->addDay()) // D-1(내일 마감) + 경과
            ->orderBy('due_date')
            ->get();

        $sent = 0;
        foreach ($todos as $todo) {
            // 수신자 — 완료 체크를 안 한 담당자만 (복수 담당은 pivot, 단독 담당은 대표)
            $pending = $todo->assignees->filter(fn ($u) => $u->pivot->completed_at === null);
            if ($pending->isEmpty() && $todo->assignee) {
                $pending = collect([$todo->assignee]);
            }
            if ($pending->isEmpty()) {
                continue;
            }

            $mentions = $pending
                ->map(fn ($u) => $channelTalk->managerMention($u->email, $u->display_name ?? $u->username ?? '담당자'))
                ->implode(', ');

            $dueDate = $todo->due_date->startOfDay();
            $when = $dueDate->gt($today)
                ? '마감이 내일입니다'
                : ($dueDate->eq($today) ? '오늘 마감입니다' : '마감이 '.$dueDate->diffInDays($today).'일 지났습니다');

            // 남은 단계 — 체크리스트가 있으면 어떤 마무리가 필요한지 알림에 포함
            $progress = '';
            $items = $todo->checklistItems;
            if ($items->isNotEmpty()) {
                $remaining = $items->filter(fn ($c) => ! $c->done_at)->pluck('title');
                $progress = ' · 진행 '.$items->whereNotNull('done_at')->count().'/'.$items->count();
                if ($remaining->isNotEmpty()) {
                    $progress .= "\n남은 단계: ".$remaining->take(5)->implode(', ').($remaining->count() > 5 ? ' 외 '.($remaining->count() - 5).'건' : '');
                }
            }

            $result = $channelTalk->sendGroupMessage(
                "⏰ {$mentions} — '{$todo->title}' {$when}{$progress}"
            );
            if ($result['ok']) {
                $sent++;
            }
        }

        $this->info("할 일 리마인드 발송 완료 — {$sent}건 (대상 {$todos->count()}건)");

        return self::SUCCESS;
    }
}
