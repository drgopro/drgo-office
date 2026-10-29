<?php

namespace App\Http\Controllers;

use App\Services\AppVersion;
use Illuminate\Http\JsonResponse;

/** 셸 폴링용 — 현재 배포 버전. 페이지 로드 시 심어둔 값과 다르면 '새로운 업데이트' 띠 표시 */
class AppVersionController extends Controller
{
    public function __invoke(AppVersion $version): JsonResponse
    {
        return response()->json(['version' => $version->current()])
            ->header('Cache-Control', 'no-store, private');
    }
}
