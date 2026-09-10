<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** 채널톡 고객 미러 — drgo:sync-channeltalk-users가 주기 갱신, 의뢰자 등록의 '채널톡 연동' 검색용 */
class ChannelTalkUser extends Model
{
    protected $table = 'channeltalk_users';

    protected $fillable = [
        'ct_id',
        'name',
        'mobile',
        'mobile_digits',
        'email',
        'tags',
        'profile',
        'ct_updated_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'profile' => 'array',
        'ct_updated_at' => 'datetime',
    ];

    /** 전화번호 정규화 — 숫자만, 국가번호 +82는 0으로 (매칭 키) */
    public static function normalizePhone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (str_starts_with($digits, '82') && strlen($digits) >= 11) {
            $digits = '0'.substr($digits, 2);
        }

        return $digits;
    }
}
