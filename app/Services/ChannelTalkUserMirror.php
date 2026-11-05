<?php

namespace App\Services;

use App\Models\ChannelTalkUser;
use App\Models\Client;

/**
 * 채널톡 고객 → 로컬 미러 반영 + 전화번호 자동 연동.
 * 스케줄 동기화(SyncChannelTalkUsers)와 수동 '지금 동기화' 버튼이 같은 로직을 쓴다.
 */
class ChannelTalkUserMirror
{
    /**
     * listUsers()가 돌려준 고객 1명을 미러에 반영하고, 전화번호가 일치하는
     * 미연동 의뢰자를 자동 연동한다.
     *
     * @param  array{ct_id:string, name:string, mobile:string, email:string, tags:array, profile:array, updated_at:?int}  $u
     * @return array{upserted: bool, linked: int}
     */
    public static function apply(array $u): array
    {
        if (($u['ct_id'] ?? '') === '') {
            return ['upserted' => false, 'linked' => 0];
        }
        $digits = ChannelTalkUser::normalizePhone($u['mobile'] ?? '');
        ChannelTalkUser::updateOrCreate(['ct_id' => $u['ct_id']], [
            'name' => ($u['name'] ?? '') ?: null,
            'mobile' => ($u['mobile'] ?? '') ?: null,
            'mobile_digits' => strlen($digits) >= 7 ? $digits : null,
            'email' => ($u['email'] ?? '') ?: null,
            'tags' => ($u['tags'] ?? []) ?: null,
            'profile' => ($u['profile'] ?? []) ?: null,
            'ct_updated_at' => ! empty($u['updated_at']) ? date('Y-m-d H:i:s', intdiv((int) $u['updated_at'], 1000)) : null,
        ]);

        $linked = strlen($digits) >= 7 ? self::linkClientsByPhone($digits, (string) $u['ct_id']) : 0;

        return ['upserted' => true, 'linked' => $linked];
    }

    /** 전화번호가 일치하는 미연동 의뢰자에 채널톡 고객 ID 기록 (동번호 다건이면 오연동 방지 위해 건너뜀) */
    public static function linkClientsByPhone(string $digits, string $ctId): int
    {
        $candidates = Client::whereNull('channeltalk_user_id')
            ->whereNotNull('phone')
            ->where('phone', 'like', '%'.substr($digits, -4).'%')
            ->get(['id', 'phone'])
            ->filter(fn ($c) => ChannelTalkUser::normalizePhone($c->phone) === $digits);

        if ($candidates->count() !== 1) {
            return 0;
        }

        Client::where('id', $candidates->first()->id)->update(['channeltalk_user_id' => $ctId]);

        return 1;
    }
}
