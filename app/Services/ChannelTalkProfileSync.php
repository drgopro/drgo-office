<?php

namespace App\Services;

use App\Models\Client;

/**
 * 오피스 → 채널톡 프로필 반영 (오피스가 원본).
 *
 * 연동된 의뢰자를 저장할 때 오피스 값을 채널톡 커스텀 프로필 키로 밀어준다.
 * - 채널톡→오피스 가져오기는 의뢰자 등록의 '채널톡 연동' 버튼에서만 (등록 이후 역유입 없음)
 * - PATCH는 보낸 키만 병합 — 여기서 안 다루는 채널톡 값(중요메모 note, 장비 키 등)은 불변
 * - 빈 오피스 필드는 보내지 않는다 (채널톡 기존 값 삭제 방지)
 * - 실패해도 의뢰자 저장에는 영향 없음 (channeltalk.log에만 기록)
 */
class ChannelTalkProfileSync
{
    /** 저장된 의뢰자를 채널톡에 반영 — 연동 안 된 의뢰자는 무시 */
    public static function push(Client $client): void
    {
        if (! $client->channeltalk_user_id) {
            return;
        }

        $profile = self::profileFromClient($client);
        if ($profile === []) {
            return;
        }

        app(ChannelTalkClient::class)->updateUserProfile($client->channeltalk_user_id, $profile);
    }

    /**
     * 의뢰자 → 채널톡 커스텀 키 역매핑 (가져오기 매핑의 반대 방향).
     * name=닉네임 / truename=이름 / mobileNumber=연락처 / platform=플랫폼(기타는 직접입력 원문으로)
     * / content=방송주제 / history=경력 / chname=방송 아이디 / address=주소(상세 포함).
     *
     * @return array<string, mixed>
     */
    public static function profileFromClient(Client $client): array
    {
        $profile = [];
        if (trim((string) $client->nickname) !== '') {
            $profile['name'] = trim((string) $client->nickname);
        }
        if (trim((string) $client->name) !== '') {
            $profile['truename'] = trim((string) $client->name);
        }
        if (trim((string) $client->phone) !== '') {
            $profile['mobileNumber'] = trim((string) $client->phone);
        }

        $splitEtc = fn ($v) => collect(preg_split('/[,\/]+/', (string) $v))
            ->map(fn ($x) => trim((string) $x))->filter()->values();

        // 플랫폼/주제 — '기타' 자리에는 직접입력 원문을 넣어 채널톡에서도 실제 명칭이 보이게
        $platforms = collect($client->platforms ?? [])->reject(fn ($p) => $p === '기타')
            ->concat($splitEtc($client->platform_etc))->unique()->values();
        if ($platforms->isNotEmpty()) {
            $profile['platform'] = $platforms->all();
        }
        $topics = collect($client->content_types ?? [])->reject(fn ($t) => $t === '기타')
            ->concat($splitEtc($client->topic_etc))->unique()->values();
        if ($topics->isNotEmpty()) {
            $profile['content'] = $topics->all();
        }

        if (trim((string) $client->career) !== '') {
            $profile['history'] = trim((string) $client->career);
        }
        if (trim((string) $client->broadcast_id) !== '') {
            $profile['chname'] = trim((string) $client->broadcast_id);
        }
        $address = trim(trim((string) $client->address).' '.trim((string) $client->address_detail));
        if ($address !== '') {
            $profile['address'] = $address;
        }

        return $profile;
    }
}
