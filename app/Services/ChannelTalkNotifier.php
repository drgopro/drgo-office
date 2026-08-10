<?php

namespace App\Services;

use App\Models\Assignee;
use App\Models\Schedule;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * 채널톡 팀챗 개인 알림 — 그룹 메시지에 담당자 @멘션을 실어
 * 멘션 대상에게 채널톡 개인 알림이 가도록 한다.
 * 오피스 계정 이메일 = 채널톡 매니저 이메일로 매칭 (불일치 시 이름만 표시).
 * 알림 실패가 본 동작(일정/할 일 저장)을 깨지 않도록 항상 무해하게 처리.
 */
class ChannelTalkNotifier
{
    public function __construct(private ChannelTalkClient $client) {}

    /** 일정 담당자 추가/제거 알림 (assignee id 배열) */
    public function scheduleAssigneesChanged(Schedule $schedule, array $addedIds, array $removedIds): void
    {
        if (! $this->client->isConfigured() || (! $addedIds && ! $removedIds)) {
            return;
        }

        try {
            $date = $schedule->start_date?->format('m/d');
            $label = "'{$schedule->title}'".($date ? " ({$date})" : '');

            if ($addedIds) {
                $this->client->sendGroupMessage("🔔 {$this->mentionsByIds($addedIds)} — {$label} 일정의 담당자로 지정되었습니다");
            }
            if ($removedIds) {
                $this->client->sendGroupMessage("🔕 {$this->mentionsByIds($removedIds)} — {$label} 일정의 담당에서 제외되었습니다");
            }
        } catch (\Throwable $e) {
            Log::warning('채널톡 담당자 알림 실패: '.$e->getMessage());
        }
    }

    /** 할 일 등록 알림 — 담당자들에게 멘션 */
    public function todoCreated(Todo $todo): void
    {
        if (! $this->client->isConfigured()) {
            return;
        }

        try {
            $assignees = $todo->assignees->isNotEmpty()
                ? $todo->assignees
                : collect([$todo->assignee])->filter();
            if ($assignees->isEmpty()) {
                return;
            }

            $due = $todo->due_date ? ' (마감 '.$todo->due_date->format('m/d').')' : '';
            $this->client->sendGroupMessage("📌 {$this->mentionList($assignees)} — 새 할 일: '{$todo->title}'{$due}");
        } catch (\Throwable $e) {
            Log::warning('채널톡 할 일 알림 실패: '.$e->getMessage());
        }
    }

    /**
     * 담당자 컬렉션 → 멘션 나열 ("멘션1, 멘션2").
     * 캘린더 담당자(Assignee — 연결 계정 이메일)와 할 일 담당자(User — 본인 이메일) 모두 지원.
     */
    public function mentionList($people): string
    {
        return collect($people)->map(function ($p) {
            if ($p instanceof User) {
                return $this->client->managerMentionByEmail($p->email, $p->display_name ?? $p->username ?? '담당자');
            }

            return $this->client->managerMentionByEmail($p->user?->email, $p->name);
        })->implode(', ');
    }

    private function mentionsByIds(array $assigneeIds): string
    {
        return $this->mentionList(Assignee::with('user')->whereIn('id', $assigneeIds)->get());
    }
}
