<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\ChannelTalkClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

#[Signature('drgo:watch-boards')]
#[Description('drgo.pro 게시판 새 글/답변/댓글을 폴링해 채널톡 팀챗으로 알림 (5분 주기)')]
class WatchDrgoBoards extends Command
{
    /** 게시판 ID → 표시 이름 */
    private const BOARD_LABELS = ['free' => '자유게시판'];

    private const TYPE_ICONS = ['post' => '🆕 새 글', 'reply' => '↩ 답변글', 'comment' => '💬 댓글'];

    /** 한 번에 나열할 최대 건수 (초과분은 "외 n건") */
    private const MAX_LINES = 10;

    public function handle(ChannelTalkClient $channelTalk): int
    {
        $token = (string) config('services.drgo_board.token');
        if ($token === '') {
            $this->warn('DRGO_BOARD_FEED_TOKEN이 없어 건너뜁니다.');

            return self::SUCCESS;
        }
        if (! $channelTalk->isConfigured()) {
            $this->warn('채널톡 연동 정보가 없어 건너뜁니다 (.env CHANNELTALK_*)');

            return self::SUCCESS;
        }

        $boards = array_filter(array_map('trim', explode(',', (string) config('services.drgo_board.boards'))));
        foreach ($boards as $board) {
            $this->watchBoard($board, $token, $channelTalk);
        }

        return self::SUCCESS;
    }

    private function watchBoard(string $board, string $token, ChannelTalkClient $channelTalk): void
    {
        $watermarkKey = "drgo_board.{$board}.last_id";
        $since = (int) Setting::get($watermarkKey, 0);

        try {
            $res = Http::timeout(15)->connectTimeout(5)->get((string) config('services.drgo_board.feed_url'), [
                'token' => $token,
                'board' => $board,
                'since' => $since,
                'limit' => 50,
            ]);
        } catch (\Throwable $e) {
            Log::warning("drgo.pro 게시판 피드 실패 ({$board}): ".$e->getMessage());
            $this->warn("피드 통신 실패 ({$board}) — 다음 주기에 재시도");

            return;
        }

        if (! $res->successful() || ! $res->json('ok')) {
            Log::warning("drgo.pro 게시판 피드 오류 ({$board}): HTTP ".$res->status().' '.mb_substr($res->body(), 0, 200));
            $this->warn("피드 응답 오류 ({$board}) — 다음 주기에 재시도");

            return;
        }

        $maxId = (int) $res->json('max_id');

        // 첫 실행 — 현재 최신 번호만 기록 (과거 글 알림 폭탄 방지)
        if ($since === 0) {
            Setting::set($watermarkKey, $maxId);
            $this->info("{$board}: 워터마크 초기화 (last_id={$maxId})");

            return;
        }

        $items = collect($res->json('items') ?? [])->filter(fn ($i) => is_array($i) && ! empty($i['id']));
        if ($items->isEmpty()) {
            return;
        }

        $message = $this->buildMessage($board, $items->all());
        $result = $channelTalk->sendGroupMessage($message);
        if (! ($result['ok'] ?? false)) {
            // 전송 실패 시 워터마크를 올리지 않아 다음 주기에 재알림된다
            Log::warning("drgo.pro 게시판 채널톡 전송 실패 ({$board}): ".($result['error'] ?? ''));
            $this->warn("채널톡 전송 실패 ({$board}) — 다음 주기에 재시도");

            return;
        }

        $newMax = max($maxId, (int) $items->max('id'));
        Setting::set($watermarkKey, $newMax);
        $this->info("{$board}: {$items->count()}건 알림 (last_id={$newMax})");
    }

    /** @param  array<int, array<string, mixed>>  $items */
    private function buildMessage(string $board, array $items): string
    {
        $label = self::BOARD_LABELS[$board] ?? $board;
        $lines = ['🔔 drgo.pro '.$label.' 새 소식 '.count($items).'건'];

        foreach (array_slice($items, 0, self::MAX_LINES) as $item) {
            $type = (string) ($item['type'] ?? 'post');
            $icon = self::TYPE_ICONS[$type] ?? self::TYPE_ICONS['post'];
            $name = (string) ($item['name'] ?? '');
            $time = mb_substr((string) ($item['datetime'] ?? ''), 5, 11); // "MM-DD HH:MM"
            $secret = ! empty($item['secret']);

            if ($type === 'comment') {
                $parentSubject = $secret ? '🔒 비밀글' : ((string) ($item['parent_subject'] ?? '') ?: '(원글)');
                $url = 'https://drgo.pro/'.$board.'/'.(int) ($item['parent_id'] ?? 0).'#c_'.(int) $item['id'];
                $lines[] = "{$icon} · {$name} → \"{$parentSubject}\" · {$time}\n{$url}";
            } else {
                $subject = $secret ? '🔒 비밀글' : (string) ($item['subject'] ?? '');
                $url = 'https://drgo.pro/'.$board.'/'.(int) $item['id'];
                $lines[] = "{$icon} \"{$subject}\" · {$name} · {$time}\n{$url}";
            }
        }

        if (count($items) > self::MAX_LINES) {
            $lines[] = '… 외 '.(count($items) - self::MAX_LINES).'건 — 게시판에서 확인해주세요';
        }

        return implode("\n\n", $lines);
    }
}
