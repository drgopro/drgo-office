<?php

namespace App\Http\Controllers;

use App\Models\BankDeposit;
use App\Services\DepositSmsParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankDepositController extends Controller
{
    public function index()
    {
        return view('deposits.index');
    }

    /**
     * SMS 포워딩 앱 웹훅 수신 — 토큰 인증, '입금' 문자만 저장, 재전송 중복 방지.
     * 인증 세션 밖(폰 앱)에서 호출되므로 auth 미들웨어/CSRF 제외 라우트에 연결된다.
     */
    public function ingest(Request $request, DepositSmsParser $parser): JsonResponse
    {
        $expected = (string) config('services.bank_deposit.token');
        $provided = (string) ($request->header('X-Deposit-Token') ?? $request->input('token', ''));
        if ($expected === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => '인증 실패'], 401);
        }

        // SmsForwarder 등 앱마다 본문 필드명이 달라 여러 키 허용
        $text = trim((string) ($request->input('text')
            ?? $request->input('content')
            ?? $request->input('msg')
            ?? $request->input('sms', '')));

        if ($text === '') {
            return response()->json(['message' => '본문이 비어있습니다.'], 422);
        }

        // 출금/잔액조회 등 입금 외 문자는 저장하지 않음
        if (! str_contains($text, '입금')) {
            return response()->json(['ok' => true, 'skipped' => true]);
        }

        $parsed = $parser->parse($text);

        $deposit = BankDeposit::firstOrCreate(
            ['dedup_hash' => hash('sha256', $text)],
            [
                'received_at' => $parsed['received_at'],
                'amount' => $parsed['amount'],
                'depositor_name' => $parsed['depositor_name'],
                'balance_after' => $parsed['balance_after'],
                'raw_text' => $text,
                'source' => 'sms',
            ]
        );

        return response()->json([
            'ok' => true,
            'duplicated' => ! $deposit->wasRecentlyCreated,
            'id' => $deposit->id,
        ], $deposit->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * 입금 내역 목록 — 기간(from/to)·입금자명/금액 검색 + 페이지네이션, 기간 합계 포함.
     */
    public function list(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:1|max:200',
            'page' => 'nullable|integer|min:1',
        ]);

        $query = BankDeposit::query();

        if (! empty($validated['from'])) {
            $query->where('received_at', '>=', $validated['from'].' 00:00:00');
        }
        if (! empty($validated['to'])) {
            $query->where('received_at', '<=', $validated['to'].' 23:59:59');
        }
        if ($search = trim($validated['search'] ?? '')) {
            $query->where(function ($q) use ($search) {
                $q->where('depositor_name', 'like', "%{$search}%")
                    ->orWhere('raw_text', 'like', "%{$search}%");
                $numeric = (int) str_replace(',', '', $search);
                if ($numeric > 0) {
                    $q->orWhere('amount', $numeric);
                }
            });
        }

        $totalAmount = (clone $query)->sum('amount');

        $page = $query->orderByDesc('received_at')->orderByDesc('id')
            ->paginate((int) ($validated['per_page'] ?? 20));

        return response()->json([
            'data' => $page->items(),
            'total' => $page->total(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total_amount' => (int) $totalAmount, // 필터 조건 전체 합계 (페이지 무관)
        ]);
    }
}
