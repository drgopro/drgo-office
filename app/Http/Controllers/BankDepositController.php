<?php

namespace App\Http\Controllers;

use App\Models\BankDeposit;
use App\Models\Estimate;
use App\Models\PayappPayment;
use App\Services\DepositSmsParser;
use App\Services\PayAppClient;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankDepositController extends Controller
{
    public function index()
    {
        return view('deposits.index');
    }

    /** 토큰을 실어 보낼 수 있는 헤더 이름 변형들 (포워딩 앱 설정 실수 방어) */
    private const TOKEN_HEADERS = ['X-Deposit-Token', 'X-Deposits-Token', 'Deposit-Token', 'Deposits-Token', 'Bank-Deposits-Token', 'X-Bank-Deposits-Token'];

    private const MAX_LOG_BYTES = 5 * 1024 * 1024;

    /**
     * SMS 포워딩 앱 웹훅 수신 — 토큰 인증, '입금' 문자만 저장, 재전송 중복 방지.
     * 인증 세션 밖(폰 앱)에서 호출되므로 auth 미들웨어/CSRF 제외 라우트에 연결된다.
     * GET은 연결 테스트용(저장 안 함) — 브라우저에서 URL·토큰이 맞는지 확인할 수 있다.
     */
    public function ingest(Request $request, DepositSmsParser $parser): JsonResponse
    {
        $expected = (string) config('services.bank_deposit.token');
        $provided = $this->providedToken($request);
        $tokenOk = $expected !== '' && hash_equals($expected, $provided);

        if ($request->isMethod('get')) {
            $this->log($request, $tokenOk ? '연결 테스트 성공' : '연결 테스트 - 토큰 불일치', '');

            return response()->json([
                'ok' => true,
                'token_configured' => $expected !== '',
                'token_valid' => $tokenOk,
                'hint' => 'POST로 문자 본문(text/content/msg/sms 필드)을 보내면 저장됩니다.',
            ]);
        }

        if (! $tokenOk) {
            $this->log($request, $expected === '' ? '거부 - 서버 토큰 미설정(.env BANK_DEPOSIT_TOKEN)' : '거부 - 토큰 불일치', '');

            return response()->json(['message' => '인증 실패'], 401);
        }

        $text = $this->extractText($request);
        if ($text === '') {
            $this->log($request, '거부 - 본문 없음', '');

            return response()->json(['message' => '본문이 비어있습니다.'], 422);
        }

        // 출금/잔액조회 등 입금 외 문자는 저장하지 않음
        if (! str_contains($text, '입금')) {
            $this->log($request, '스킵 - 입금 문자가 아님', $text);

            return response()->json(['ok' => true, 'skipped' => true]);
        }

        $parsed = $parser->parse($text);

        $deposit = BankDeposit::firstOrCreate(
            ['dedup_hash' => hash('sha256', $text)],
            [
                'received_at' => $parsed['received_at'],
                'amount' => $parsed['amount'],
                'depositor_name' => $parsed['depositor_name'],
                'bank' => $parsed['bank'],
                'balance_after' => $parsed['balance_after'],
                'raw_text' => $text,
                'source' => 'sms',
            ]
        );

        $this->log($request, $deposit->wasRecentlyCreated
            ? "저장 완료 #{$deposit->id} (금액 ".number_format((int) $deposit->amount).' / '.($deposit->depositor_name ?? '이름 미인식').')'
            : "중복 - 기존 #{$deposit->id} 유지", $text);

        return response()->json([
            'ok' => true,
            'duplicated' => ! $deposit->wasRecentlyCreated,
            'id' => $deposit->id,
        ], $deposit->wasRecentlyCreated ? 201 : 200);
    }

    /** 헤더(이름 변형 허용) → token 파라미터 순으로 토큰 추출 */
    private function providedToken(Request $request): string
    {
        foreach (self::TOKEN_HEADERS as $header) {
            $v = (string) $request->header($header, '');
            if ($v !== '') {
                return trim($v);
            }
        }

        return trim((string) $request->input('token', ''));
    }

    /** 앱마다 본문 필드명이 달라 여러 키 허용, 없으면 가장 긴 문자열 값/원시 본문 폴백 */
    private function extractText(Request $request): string
    {
        foreach (['text', 'content', 'msg', 'sms', 'message', 'body'] as $key) {
            $v = $request->input($key);
            if (is_string($v) && trim($v) !== '') {
                return trim($v);
            }
        }

        // 알려진 키가 없으면 전송된 필드 중 가장 긴 문자열을 본문으로 간주
        $longest = collect($request->except(['token']))
            ->filter(fn ($v) => is_string($v) && trim($v) !== '')
            ->sortByDesc(fn ($v) => mb_strlen($v))
            ->first();
        if (is_string($longest)) {
            return trim($longest);
        }

        // 폼/JSON이 아닌 순수 텍스트 본문 (Content-Type: text/plain 등)
        $raw = trim((string) $request->getContent());

        return str_starts_with($raw, '{') ? '' : $raw;
    }

    /** deposit.log 기록 — 토큰 원문은 남기지 않고 길이/앞 4자만 (진단용, /admin/deposit-log 열람) */
    private function log(Request $request, string $tag, string $text): void
    {
        try {
            $path = storage_path('logs/deposit.log');
            if (file_exists($path) && filesize($path) > self::MAX_LOG_BYTES) {
                @unlink($path);
            }
            $provided = $this->providedToken($request);
            @file_put_contents($path, sprintf(
                "[%s] %s\nmethod=%s ip=%s content-type=%s\nfields=%s token=%s\ntext=%s\n\n",
                now()->format('Y-m-d H:i:s'),
                $tag,
                $request->method(),
                $request->ip(),
                (string) $request->header('Content-Type', '-'),
                implode(',', array_keys($request->all())) ?: '-',
                $provided === '' ? '(없음)' : mb_substr($provided, 0, 4).'…(len '.mb_strlen($provided).')',
                mb_substr($text, 0, 200)
            ), FILE_APPEND);
        } catch (\Throwable) {
            // 진단 로그 실패는 무시
        }
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

    /**
     * 페이앱 결제현황 — 두 소스 병합.
     * ① 견적서 결제요청(estimates.payapp_*, feedback으로 갱신)
     * ② 페이앱 자체(외부) 결제(payapp_payments, 판매자 설정의 기본 FEEDBACK URL 웹훅으로 수집)
     * 페이앱은 결제내역 조회 API가 없어 웹훅 수집분만 표시 가능.
     */
    public function payappList(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'search' => 'nullable|string|max:100',
            'status' => 'nullable|in:paid,waiting,cancelled',
            'per_page' => 'nullable|integer|min:1|max:200',
            'page' => 'nullable|integer|min:1',
        ]);
        $from = ! empty($validated['from']) ? $validated['from'].' 00:00:00' : null;
        $to = ! empty($validated['to']) ? $validated['to'].' 23:59:59' : null;
        $search = trim($validated['search'] ?? '');
        $numeric = (int) str_replace(',', '', $search);

        // ① 견적서 결제요청
        $estimateRows = Estimate::whereNotNull('payapp_requested_at')
            ->when($from, fn ($q) => $q->where('payapp_requested_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('payapp_requested_at', '<=', $to))
            ->when($search !== '', function ($q) use ($search, $numeric) {
                $q->where(function ($q) use ($search, $numeric) {
                    $q->where('client_name', 'like', "%{$search}%")
                        ->orWhere('client_nickname', 'like', "%{$search}%")
                        ->orWhere('client_phone', 'like', "%{$search}%");
                    if ($numeric > 0) {
                        $q->orWhere('total_amount', $numeric)->orWhere('id', $numeric);
                    }
                });
            })
            ->get()
            ->map(fn (Estimate $e) => [
                'source' => 'estimate',
                'id' => $e->id,
                'client_name' => $e->client_name ?: $e->client_nickname,
                'client_nickname' => $e->client_nickname,
                'client_phone' => $e->client_phone,
                'goodname' => null,
                'amount' => (int) $e->total_amount,
                'status' => $this->payappStatus($e),
                'sort_at' => $e->payapp_requested_at,
                'paid_at' => $e->payapp_paid_at?->format('Y-m-d H:i'),
                'payurl' => $e->payapp_payurl,
                'estimate_url' => $e->publicUrl(),
                'receipt_url' => null,
            ]);

        // ② 페이앱 자체(외부) 결제 — 수신 시각 기준
        $externalRows = PayappPayment::query()
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->when($search !== '', function ($q) use ($search, $numeric) {
                $q->where(function ($q) use ($search, $numeric) {
                    $q->where('buyer', 'like', "%{$search}%")
                        ->orWhere('goodname', 'like', "%{$search}%")
                        ->orWhere('recvphone', 'like', "%{$search}%");
                    if ($numeric > 0) {
                        $q->orWhere('price', $numeric);
                    }
                });
            })
            ->get()
            ->map(fn (PayappPayment $p) => [
                'source' => 'payapp',
                'id' => null,
                'client_name' => $p->buyer,
                'client_nickname' => null,
                'client_phone' => $p->recvphone,
                'goodname' => $p->goodname,
                'amount' => (int) $p->price,
                'status' => $this->externalPayappStatus($p),
                'sort_at' => $p->created_at,
                'paid_at' => $p->paid_at?->format('Y-m-d H:i'),
                'payurl' => null,
                'estimate_url' => null,
                'receipt_url' => $p->csturl,
            ]);

        $rows = $estimateRows->concat($externalRows)->sortByDesc('sort_at')->values();

        if (! empty($validated['status'])) {
            $keys = match ($validated['status']) {
                'paid' => ['paid'],
                'waiting' => ['waiting'],
                'cancelled' => ['refunded', 'req_cancelled'],
            };
            $rows = $rows->filter(fn ($r) => in_array($r['status']['key'], $keys, true))->values();
        }

        $paidRows = $rows->filter(fn ($r) => $r['status']['key'] === 'paid');
        $perPage = (int) ($validated['per_page'] ?? 20);
        $page = max(1, (int) ($validated['page'] ?? 1));
        $total = $rows->count();
        $items = $rows->slice(($page - 1) * $perPage, $perPage)->values()
            ->map(function ($r) {
                $r['requested_at'] = $r['sort_at'] instanceof CarbonInterface ? $r['sort_at']->format('Y-m-d H:i') : (string) $r['sort_at'];
                unset($r['sort_at']);

                return $r;
            });

        return response()->json([
            'data' => $items->all(),
            'total' => $total,
            'current_page' => $page,
            'last_page' => max(1, (int) ceil($total / $perPage)),
            'paid_count' => $paidRows->count(),
            'paid_amount' => (int) $paidRows->sum('amount'), // 필터 조건 내 결제완료 합계 (페이지 무관)
        ]);
    }

    /** @return array{key:string, label:string} 페이앱 결제 상태 (통지 pay_state + 우리 상태 종합) */
    private function payappStatus(Estimate $e): array
    {
        if ($e->payapp_paid_at) {
            return ['key' => 'paid', 'label' => '결제완료'];
        }
        if (in_array((int) $e->payapp_state, PayAppClient::STATES_REFUNDED, true) || $e->status === 'cancelled') {
            return ['key' => 'refunded', 'label' => '환불/취소'];
        }
        if (in_array((int) $e->payapp_state, PayAppClient::STATES_REQUEST_CANCELLED, true)) {
            return ['key' => 'req_cancelled', 'label' => '요청취소'];
        }

        return ['key' => 'waiting', 'label' => '결제 대기'];
    }

    /** @return array{key:string, label:string} 페이앱 자체(외부) 결제 상태 */
    private function externalPayappStatus(PayappPayment $p): array
    {
        if (in_array($p->pay_state, PayAppClient::STATES_REFUNDED, true)) {
            return ['key' => 'refunded', 'label' => '환불/취소'];
        }
        if (in_array($p->pay_state, PayAppClient::STATES_REQUEST_CANCELLED, true)) {
            return ['key' => 'req_cancelled', 'label' => '요청취소'];
        }
        if ($p->paid_at || $p->pay_state === PayAppClient::STATE_PAID) {
            return ['key' => 'paid', 'label' => '결제완료'];
        }

        return ['key' => 'waiting', 'label' => '결제 대기'];
    }

    /** 선택 삭제 — 입금 내역 페이지 접근 권한(deposits.view)과 동일 라우트 그룹에서 보호 */
    public function destroyMany(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'integer|exists:bank_deposits,id',
        ]);

        $deleted = BankDeposit::whereIn('id', $validated['ids'])->delete();

        return response()->json(['ok' => true, 'deleted' => $deleted]);
    }
}
