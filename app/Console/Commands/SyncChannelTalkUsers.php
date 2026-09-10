<?php

namespace App\Console\Commands;

use App\Models\ChannelTalkUser;
use App\Models\Client;
use App\Models\Setting;
use App\Services\ChannelTalkClient;
use Illuminate\Console\Command;

/**
 * 채널톡 고객 → 로컬 미러 동기화 + 전화번호 매칭으로 기존 의뢰자 자동 연동.
 *
 * - 커서(next)를 워터마크로 저장해 한 번에 MAX_PAGES 페이지씩 이어서 훑고,
 *   끝까지 돌면 커서를 비워 다음 사이클에 처음부터 다시 훑는다 (프로필 변경 반영).
 * - 의뢰자 연동은 전화번호(숫자 정규화) 완전 일치 + 아직 미연동인 의뢰자만 자동 기록.
 *   의뢰자 정보 자체(이름/연락처)는 절대 덮어쓰지 않는다.
 */
class SyncChannelTalkUsers extends Command
{
    protected $signature = 'drgo:sync-channeltalk-users {--pages=20 : 이번 실행에서 가져올 최대 페이지 수}';

    protected $description = '채널톡 고객 프로필을 로컬 미러로 동기화하고 전화번호로 의뢰자와 자동 연동';

    private const CURSOR_KEY = 'channeltalk.users.cursor';

    public function handle(ChannelTalkClient $channelTalk): int
    {
        if (! $channelTalk->isConfigured()) {
            $this->warn('채널톡 연동 정보가 설정되지 않았습니다 — 건너뜀');

            return self::SUCCESS;
        }

        // 상담 상태별 순회 — 종료(closed)가 대부분이라 먼저, 이어서 진행/보류 중 상담.
        // 워터마크는 {s: 상태 인덱스, c: 커서} JSON — 페이지 한도에 걸리면 다음 실행에서 이어받는다.
        $states = ['closed', 'opened', 'snoozed'];
        $wm = json_decode((string) (Setting::get(self::CURSOR_KEY) ?? ''), true) ?: [];
        $stateIdx = min(max((int) ($wm['s'] ?? 0), 0), count($states) - 1);
        $cursor = (string) ($wm['c'] ?? '');
        $maxPages = max(1, (int) $this->option('pages'));
        $upserted = 0;
        $linked = 0;

        for ($page = 0; $page < $maxPages; $page++) {
            $res = $channelTalk->listUsers($states[$stateIdx], $cursor ?: null);
            if (! ($res['ok'] ?? false)) {
                Setting::set(self::CURSOR_KEY, json_encode(['s' => $stateIdx, 'c' => $cursor]));
                $this->error('채널톡 고객 조회 실패: '.($res['error'] ?? '알 수 없는 오류'));

                return self::FAILURE; // 워터마크 유지 — 다음 실행에서 같은 지점부터 재시도
            }

            foreach ($res['users'] ?? [] as $u) {
                if ($u['ct_id'] === '') {
                    continue;
                }
                $digits = ChannelTalkUser::normalizePhone($u['mobile']);
                ChannelTalkUser::updateOrCreate(['ct_id' => $u['ct_id']], [
                    'name' => $u['name'] ?: null,
                    'mobile' => $u['mobile'] ?: null,
                    'mobile_digits' => strlen($digits) >= 7 ? $digits : null,
                    'email' => $u['email'] ?: null,
                    'tags' => $u['tags'] ?: null,
                    'ct_updated_at' => $u['updated_at'] ? date('Y-m-d H:i:s', intdiv($u['updated_at'], 1000)) : null,
                ]);
                $upserted++;

                // 전화번호 일치 + 미연동 의뢰자 자동 연동 (동번호 다건이면 오연동 방지 위해 건너뜀)
                if (strlen($digits) >= 7) {
                    $linked += $this->linkClientsByPhone($digits, $u['ct_id']);
                }
            }

            $cursor = (string) ($res['next'] ?? '');
            if ($cursor === '' || count($res['users'] ?? []) === 0) {
                // 이 상태는 끝 — 다음 상태로, 마지막 상태였으면 사이클 완료 (다음 사이클은 처음부터)
                $stateIdx++;
                $cursor = '';
                if ($stateIdx >= count($states)) {
                    $stateIdx = 0;

                    break;
                }
            }
        }

        Setting::set(self::CURSOR_KEY, json_encode(['s' => $stateIdx, 'c' => $cursor]));
        $this->info("동기화 완료 — 갱신 {$upserted}건, 의뢰자 자동 연동 {$linked}건"
            .($stateIdx > 0 || $cursor !== '' ? ' (다음 실행에서 이어서)' : ''));

        return self::SUCCESS;
    }

    /** 전화번호가 일치하는 미연동 의뢰자에 채널톡 고객 ID 기록 */
    private function linkClientsByPhone(string $digits, string $ctId): int
    {
        // 끝 4자리 LIKE로 후보를 좁힌 뒤 정규화 완전 일치 확인 (하이픈/공백 차이 무시)
        $candidates = Client::whereNull('channeltalk_user_id')
            ->whereNotNull('phone')
            ->where('phone', 'like', '%'.substr($digits, -4).'%')
            ->get(['id', 'phone'])
            ->filter(fn ($c) => ChannelTalkUser::normalizePhone($c->phone) === $digits);

        // 같은 번호의 의뢰자가 여러 명이면 어느 쪽인지 확정 불가 — 자동 연동하지 않음
        if ($candidates->count() !== 1) {
            return 0;
        }

        Client::where('id', $candidates->first()->id)->update(['channeltalk_user_id' => $ctId]);

        return 1;
    }
}
