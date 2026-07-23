<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class MentionService
{
    /**
     * 본문에서 @이름 멘션을 파싱해 해당 활성 사용자를 반환.
     * '@김웅님이'처럼 조사가 붙어도 이름이 접두어로 일치하면 매칭하며,
     * 한 토큰에 여러 이름이 걸리면 가장 긴 이름만 채택 (김웅 vs 김웅수).
     *
     * @return Collection<int, User>
     */
    public static function users(string $body): Collection
    {
        preg_match_all('/@([\p{L}\p{N}_.\-]+)/u', $body, $m);
        $tokens = array_values(array_unique($m[1] ?? []));
        if ($tokens === []) {
            return collect();
        }

        $users = User::where('is_active', true)->get();
        $nameLen = fn (User $u) => max(mb_strlen((string) $u->display_name), mb_strlen((string) $u->username));
        $matched = collect();
        foreach ($tokens as $token) {
            $hits = $users->filter(function (User $u) use ($token) {
                foreach ([$u->display_name, $u->username] as $name) {
                    if ($name !== null && $name !== '' && str_starts_with($token, $name)) {
                        return true;
                    }
                }

                return false;
            });
            if ($hits->isEmpty()) {
                continue;
            }
            $maxLen = $hits->max($nameLen);
            $matched = $matched->merge($hits->filter(fn (User $u) => $nameLen($u) === $maxLen));
        }

        return $matched->unique('id')->values();
    }
}
