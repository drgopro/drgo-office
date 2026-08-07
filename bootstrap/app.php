<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\TabLayout;
use App\Services\ServerErrorNotifier;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->web(append: [TabLayout::class]);
        // 외부에서 호출되는 웹훅 — 각자 자체 검증 사용 (SMS 포워딩 토큰 / 페이앱 연동 KEY)
        $middleware->validateCsrfTokens(except: [
            'api/bank-deposits/ingest',
            'api/payapp/feedback',
            'estimate-view/*', // 페이앱 returnurl POST 리다이렉트 수신
        ]);
        $middleware->alias([
            'role' => CheckRole::class,
            'permission' => CheckPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // 미처리 예외 → 관리자 알림 (동일 에러 1시간 스로틀, 시간당 최대 5회)
        $exceptions->report(function (Throwable $e): void {
            ServerErrorNotifier::report($e);
        });
    })->create();
