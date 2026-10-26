<?php

namespace App\Services;

use App\Console\Commands\RefreshStaleIssuedEstimates;
use App\Jobs\SendChannelTalkGroupMessage;
use App\Models\Assignee;
use App\Models\Estimate;
use App\Models\Notice;
use App\Models\Schedule;
use App\Models\Setting;
use App\Models\Todo;
use App\Models\User;
use App\Models\Wiki;
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

    /** 일정 담당자 추가/제거 알림 (assignee id 배열) — 캘린더 알림 톡방(설정) 또는 기본 그룹 */
    public function scheduleAssigneesChanged(Schedule $schedule, array $addedIds, array $removedIds): void
    {
        if (! $this->client->isConfigured() || (! $addedIds && ! $removedIds)) {
            return;
        }

        try {
            $group = self::calendarGroup();
            $date = $schedule->start_date?->format('m/d');
            $label = "'{$schedule->title}'".($date ? " ({$date})" : '');

            if ($addedIds) {
                $this->send("🔔 {$this->mentionsByIds($addedIds)} — {$label} 일정의 담당자로 지정되었습니다", $group);
            }
            if ($removedIds) {
                $this->send("🔕 {$this->mentionsByIds($removedIds)} — {$label} 일정의 담당에서 제외되었습니다", $group);
            }
        } catch (\Throwable $e) {
            Log::warning('채널톡 담당자 알림 실패: '.$e->getMessage());
        }
    }

    /** 캘린더 알림 톡방(관리 > 설정 > 캘린더 > 알림 톡방) — 비어 있으면 null(기본 그룹) */
    public static function calendarGroup(): ?string
    {
        return trim((string) Setting::get('calendar_alert_group', '')) ?: null;
    }

    /** 위키 새 글 발행 알림 — 위키 전용 톡방(설정, 비우면 게시물 알림 톡방) + 위키 담당자 멘션. 공지는 별도 알림 유지 */
    public function wikiPostPublished(Wiki $wiki): void
    {
        $group = trim((string) Setting::get('wiki_post_alert_group', ''))
            ?: trim((string) Setting::get('post_alert_group', ''));
        if ($group === '' || ! $this->client->isConfigured()) {
            return; // 톡방 미설정 — 기능 꺼짐
        }

        try {
            $category = $wiki->categoryNode?->name ?? $wiki->category;
            $message = "📄 위키 새 글: '{$wiki->title}'".($category ? " · {$category}" : '')."\n".route('wiki.show', $wiki);

            // 위키 새 글 담당자 — drgo.pro Free 게시판 담당자와 별도 설정 (관리 > 설정 > 게시판/위키)
            $managerIds = json_decode((string) Setting::get('wiki_post_alert_managers', '[]'), true);
            $mentions = User::whereIn('id', is_array($managerIds) ? $managerIds : [])
                ->get()
                ->map(fn (User $u) => ChannelTalkClient::deferredMention($u->email, $u->display_name))
                ->implode(' ');
            if ($mentions !== '') {
                $message .= "\n".$mentions;
            }

            $this->send($message, $group);
        } catch (\Throwable $e) {
            Log::warning('채널톡 위키 새 글 알림 실패: '.$e->getMessage());
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
            $this->send("📌 {$this->mentionList($assignees)} — 새 할 일: '{$todo->title}'{$due}");
        } catch (\Throwable $e) {
            Log::warning('채널톡 할 일 알림 실패: '.$e->getMessage());
        }
    }

    /** 할 일 담당자 지정/제외 알림 (user id 배열) */
    public function todoAssigneesChanged(Todo $todo, array $addedUserIds, array $removedUserIds): void
    {
        if (! $this->client->isConfigured() || (! $addedUserIds && ! $removedUserIds)) {
            return;
        }

        try {
            $due = $todo->due_date ? ' (마감 '.$todo->due_date->format('m/d').')' : '';

            if ($addedUserIds) {
                $users = User::whereIn('id', $addedUserIds)->get();
                $this->send("📌 {$this->mentionList($users)} — '{$todo->title}' 할 일의 담당자로 지정되었습니다{$due}");
            }
            if ($removedUserIds) {
                $users = User::whereIn('id', $removedUserIds)->get();
                $this->send("🔕 {$this->mentionList($users)} — '{$todo->title}' 할 일의 담당에서 제외되었습니다");
            }
        } catch (\Throwable $e) {
            Log::warning('채널톡 할 일 담당자 알림 실패: '.$e->getMessage());
        }
    }

    /** 공지사항 게시판 발행 알림 — 매니저 전원 멘션(@all 효과) */
    public function noticePublished(Notice $notice): void
    {
        if (! $this->client->isConfigured()) {
            return;
        }

        try {
            $this->send(
                '📢 '.ChannelTalkClient::deferredMentionAll()."\n새 공지사항이 등록되었습니다: '{$notice->title}'\n".route('notices.show', $notice)
            );
        } catch (\Throwable $e) {
            Log::warning('채널톡 공지 알림 실패: '.$e->getMessage());
        }
    }

    /** 위키 공지사항 발행 알림 — 매니저 전원 멘션(@all 효과) */
    public function wikiNoticePublished(Wiki $wiki): void
    {
        if (! $this->client->isConfigured()) {
            return;
        }

        try {
            $this->send(
                '📢 '.ChannelTalkClient::deferredMentionAll()."\n새 공지사항이 등록되었습니다: '{$wiki->title}'\n".route('wiki.show', $wiki)
            );
        } catch (\Throwable $e) {
            Log::warning('채널톡 공지 알림 실패: '.$e->getMessage());
        }
    }

    /** 발행 24시간 재갱신 알림 — 금액이 바뀐 견적서를 팀챗에 공유 (수신 확인용 전원 멘션) */
    public function estimateReissued(Estimate $estimate, int $oldTotal, int $newTotal, string $note): void
    {
        if (! $this->client->isConfigured()) {
            return;
        }

        try {
            $who = trim((string) ($estimate->client_nickname ?: $estimate->client_name));
            $this->send(
                ChannelTalkClient::deferredMentionAll()."\n"
                ."견적서 #{$estimate->display_no}".($who !== '' ? " ({$who})" : '')
                .' — 발행 '.RefreshStaleIssuedEstimates::GUARANTEE_HOURS.'시간 경과 재갱신: '
                .number_format($oldTotal).'원 → '.number_format($newTotal)."원\n{$note}\n"
                .url("/estimates/{$estimate->id}/edit")
            );
        } catch (\Throwable $e) {
            Log::warning('채널톡 견적 재갱신 알림 실패: '.$e->getMessage());
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
                return ChannelTalkClient::deferredMention($p->email, $p->display_name ?? $p->username ?? '담당자');
            }

            return ChannelTalkClient::deferredMention($p->user?->email, $p->name);
        })->implode(', ');
    }

    /** 그룹 메시지 발송 — 본문만 여기서 완성하고 외부 전송은 큐로 넘긴다 (저장 요청이 채널톡 응답을 기다리지 않도록) */
    private function send(string $text, ?string $group = null): void
    {
        SendChannelTalkGroupMessage::dispatch($text, $group);
    }

    private function mentionsByIds(array $assigneeIds): string
    {
        return $this->mentionList(Assignee::with('user')->whereIn('id', $assigneeIds)->get());
    }
}
