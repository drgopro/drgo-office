<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * 비활성/퇴사 처리된 계정의 접근 차단 — 로그인 차단만으로는 이미 열려 있는
 * 세션이 남으므로, 매 요청 시점에 확인해 즉시 로그아웃시킨다.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && (! $user->is_active || $user->isResigned())) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => '접근 권한이 없는 계정입니다.'], 403);
            }

            return redirect()->route('login')->withErrors(['username' => '접근 권한이 없는 계정입니다. 관리자에게 문의하세요.']);
        }

        return $next($request);
    }
}
