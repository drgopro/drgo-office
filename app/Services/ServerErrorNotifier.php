<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\ServerErrorAlert;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * 미처리 예외 발생 시 관리자에게 알림 (외부 모니터링 서비스 없이 자체 인프라 사용).
 *
 * - 프레임워크가 report 대상으로 판단한 예외만 들어옴 (404/검증 실패 등 노이즈는 이미 걸러짐)
 * - 동일 에러(클래스+파일+라인)는 1시간에 1회만, 전체로는 시간당 최대 5회만 발송 (알림 폭주 방지)
 * - 알림 발송 자체가 실패해도 원 요청에 영향을 주지 않도록 모든 예외를 삼킴
 */
class ServerErrorNotifier
{
    private const THROTTLE_MINUTES = 60;

    private const MAX_ALERTS_PER_HOUR = 5;

    public static function report(Throwable $e): void
    {
        try {
            if (! config('app.error_alerts', true)) {
                return;
            }

            $location = basename($e->getFile()).':'.$e->getLine();
            $dedupeKey = 'error-alert:'.md5(get_class($e).$e->getFile().$e->getLine());

            // 동일 에러 스로틀 — add()는 키가 없을 때만 저장하고 true 반환
            if (! Cache::add($dedupeKey, 1, now()->addMinutes(self::THROTTLE_MINUTES))) {
                return;
            }

            // 전체 발송량 상한 (서로 다른 에러가 연쇄 폭주하는 경우)
            $hourKey = 'error-alert-count:'.now()->format('YmdH');
            Cache::add($hourKey, 0, now()->addHour()); // 최초 생성 시에만 TTL 설정
            if ((int) Cache::increment($hourKey) > self::MAX_ALERTS_PER_HOUR) {
                return;
            }

            $admins = User::whereIn('role', ['master', 'admin'])->get();
            foreach ($admins as $admin) {
                try {
                    $admin->notify(new ServerErrorAlert(get_class($e), $e->getMessage(), $location));
                } catch (Throwable) {
                    // 개별 발송 실패 무시 (웹푸시 구독 만료 등)
                }
            }
        } catch (Throwable) {
            // 알림 로직의 실패가 원래 예외 처리를 방해하면 안 됨 (DB 다운 등)
        }
    }
}
