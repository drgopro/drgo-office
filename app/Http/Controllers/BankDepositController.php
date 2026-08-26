<?php

namespace App\Http\Controllers;

use App\Models\BankDeposit;
use App\Models\Estimate;
use App\Models\PayappPayment;
use App\Services\DepositSmsParser;
use App\Services\PayAppClient;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

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
            ->map(function (Estimate $e) {
                $status = $this->payappStatus($e);

                return [
                    'source' => 'estimate',
                    'id' => $e->id,
                    'no' => $e->display_no, // 화면 표시용 견적서 번호 — 목록과 동일

                    'client_name' => $e->client_name ?: $e->client_nickname,
                    'client_nickname' => $e->client_nickname,
                    'client_phone' => $e->client_phone,
                    'goodname' => null,
                    'amount' => (int) $e->total_amount,
                    'status' => $status,
                    'sort_at' => $e->payapp_requested_at,
                    'paid_at' => $e->payapp_paid_at?->format('Y-m-d H:i'),
                    'payurl' => $e->payapp_payurl,
                    'estimate_url' => $e->publicUrl(),
                    'receipt_url' => null,
                    'mul_no' => $e->payapp_mul_no,
                    'can_cancel' => $e->payapp_mul_no && in_array($status['key'], ['waiting', 'paid'], true),
                ];
            });

        // ② 페이앱 자체(외부) 결제 — 실제 요청 시각(requested_at, 없으면 수신 시각) 기준.
        // 견적서 결제요청과 같은 결제번호는 제외 (통보가 늦게 재시도돼도 중복 표시 방지)
        $externalRows = PayappPayment::query()
            ->whereNotIn('mul_no', Estimate::whereNotNull('payapp_mul_no')->select('payapp_mul_no'))
            ->when($from, fn ($q) => $q->whereRaw('COALESCE(requested_at, created_at) >= ?', [$from]))
            ->when($to, fn ($q) => $q->whereRaw('COALESCE(requested_at, created_at) <= ?', [$to]))
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
            ->map(function (PayappPayment $p) {
                $status = $this->externalPayappStatus($p);

                return [
                    'source' => 'payapp',
                    'id' => null,
                    'client_name' => $p->buyer,
                    'client_nickname' => null,
                    'client_phone' => $p->recvphone,
                    'goodname' => $p->goodname,
                    'amount' => (int) $p->price,
                    'status' => $status,
                    'sort_at' => $p->requested_at ?? $p->created_at,
                    'paid_at' => $p->paid_at?->format('Y-m-d H:i'),
                    'payurl' => null,
                    'estimate_url' => null,
                    'receipt_url' => $p->csturl,
                    'mul_no' => $p->mul_no,
                    // 엑셀 백필 건(IMP-)은 페이앱 요청번호가 없어 API 취소 불가
                    'can_cancel' => ! str_starts_with((string) $p->mul_no, 'IMP-')
                        && in_array($status['key'], ['waiting', 'paid'], true),
                ];
            });

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

    /**
     * 결제현황에서 취소 — 결제 대기는 요청 철회, 결제완료는 승인취소(paycancel).
     * 정산이 끝난 결제는 paycancel이 거부되므로 환불 요청(paycancelreq)으로 자동 폴백.
     */
    public function payappCancel(Request $request, PayAppClient $payapp): JsonResponse
    {
        $validated = $request->validate([
            'source' => 'required|in:estimate,payapp',
            'id' => 'required_if:source,estimate|nullable|integer',
            'mul_no' => 'required_if:source,payapp|nullable|string|max:100',
        ]);

        if ($validated['source'] === 'estimate') {
            $estimate = Estimate::findOrFail($validated['id']);
            $statusKey = $this->payappStatus($estimate)['key'];
            if (! in_array($statusKey, ['waiting', 'paid'], true)) {
                return response()->json(['message' => '이미 취소/환불 처리된 건입니다.'], 422);
            }
            if (! $estimate->payapp_mul_no) {
                return response()->json(['message' => '취소할 결제요청 번호가 없습니다.'], 422);
            }

            $result = $payapp->cancelByMulNo(
                $estimate->payapp_mul_no,
                '견적서 #'.$estimate->id.' '.($statusKey === 'paid' ? '결제 취소' : '결제요청 취소'),
                allowRefundRequest: $statusKey === 'paid',
            );
            if (! $result['ok']) {
                return response()->json(['message' => $result['error']], 422);
            }
            if (! empty($result['refund_requested'])) {
                // 정산 완료 건 — 페이앱이 환불을 처리하면 통지(feedback)로 상태가 갱신됨
                return response()->json(['message' => '정산이 완료된 결제라 페이앱에 환불 요청을 접수했습니다. 페이앱에서 처리되면 상태가 자동 갱신됩니다.']);
            }

            // mul_no는 남겨 페이앱의 취소 통보가 외부 결제로 중복 기록되지 않게 함
            if ($statusKey === 'paid') {
                $estimate->update([
                    'status' => 'cancelled',
                    'payapp_state' => 9,
                    'payapp_paid_at' => null,
                    'payapp_payurl' => null,
                ]);

                return response()->json(['message' => '결제가 취소되었습니다.']);
            }

            $estimate->update(['payapp_payurl' => null, 'payapp_state' => 16]);

            return response()->json(['message' => '결제요청이 취소되었습니다.']);
        }

        // 페이앱 자체(외부) 결제
        $payment = PayappPayment::where('mul_no', $validated['mul_no'])->firstOrFail();
        if (str_starts_with((string) $payment->mul_no, 'IMP-')) {
            return response()->json(['message' => '엑셀로 가져온 건은 결제요청 번호가 없어 여기서 취소할 수 없습니다. 페이앱 판매자 페이지에서 취소해주세요.'], 422);
        }
        $statusKey = $this->externalPayappStatus($payment)['key'];
        if (! in_array($statusKey, ['waiting', 'paid'], true)) {
            return response()->json(['message' => '이미 취소/환불 처리된 건입니다.'], 422);
        }

        $result = $payapp->cancelByMulNo(
            $payment->mul_no,
            '오피스 결제현황에서 '.($statusKey === 'paid' ? '결제 취소' : '결제요청 취소'),
            allowRefundRequest: $statusKey === 'paid',
        );
        if (! $result['ok']) {
            return response()->json(['message' => $result['error']], 422);
        }
        if (! empty($result['refund_requested'])) {
            return response()->json(['message' => '정산이 완료된 결제라 페이앱에 환불 요청을 접수했습니다. 페이앱에서 처리되면 상태가 자동 갱신됩니다.']);
        }

        $payment->update(['pay_state' => $statusKey === 'paid' ? 9 : 8]);

        return response()->json(['message' => $statusKey === 'paid' ? '결제가 취소되었습니다.' : '결제요청이 취소되었습니다.']);
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

    /**
     * 페이앱 결제내역 엑셀 가져오기 — 판매자 페이지에서 다운로드한 파일 업로드.
     * 페이앱은 결제내역 조회 API가 없어 과거 내역(취소건 포함)은 이 방식으로 백필.
     * 헤더 키워드로 컬럼을 유연하게 인식하고, 요청번호(없으면 내용 해시) 기준 upsert.
     */
    public function payappImport(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file']);
        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
            return response()->json(['message' => 'xlsx, xls, csv 파일만 지원합니다.'], 422);
        }

        try {
            if ($ext === 'csv') {
                $reader = IOFactory::createReader('Csv');
                $reader->setInputEncoding('UTF-8');
            } else {
                $reader = IOFactory::createReaderForFile($file->getRealPath());
            }
            $rows = $reader->load($file->getRealPath())->getActiveSheet()->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            return response()->json(['message' => '파일을 읽을 수 없습니다: '.$e->getMessage()], 422);
        }

        // 헤더 행 탐지 — '상태'와 금액류 키워드가 함께 있는 첫 행
        $headerIdx = null;
        $cols = [];
        $find = function (array $header, array $keywords): ?int {
            foreach ($keywords as $kw) {
                foreach ($header as $i => $h) {
                    if ($h !== null && str_contains((string) $h, $kw)) {
                        return $i;
                    }
                }
            }

            return null;
        };
        foreach (array_slice($rows, 0, 10, true) as $i => $row) {
            $joined = implode(' ', array_map(strval(...), array_filter($row, fn ($v) => $v !== null)));
            if (str_contains($joined, '상태') && (str_contains($joined, '금액') || str_contains($joined, '상품'))) {
                $headerIdx = $i;
                $cols = [
                    'mul_no' => $find($row, ['결제요청번호', '요청번호', '승인번호', '거래번호']),
                    'status' => $find($row, ['결제상태', '상태']),
                    'price' => $find($row, ['결제금액', '요청금액', '금액']),
                    'goodname' => $find($row, ['상품명', '상품정보', '내용']),
                    'buyer' => $find($row, ['구매자', '고객명', '이름', '성명']),
                    'phone' => $find($row, ['휴대폰', '휴대전화', '연락처', '전화']),
                    'pay_type' => $find($row, ['결제수단', '결제방법']),
                    'date' => $find($row, ['결제일', '승인일', '요청일', '일시', '날짜']),
                ];
                break;
            }
        }
        if ($headerIdx === null || $cols['status'] === null || $cols['price'] === null) {
            return response()->json(['message' => "헤더를 인식하지 못했습니다. 페이앱 결제내역 엑셀 파일인지 확인해주세요.\n(필요 컬럼: 상태·금액, 권장: 요청번호·구매자·상품명·결제일)"], 422);
        }

        $cell = fn (array $row, ?int $idx) => $idx !== null ? trim((string) ($row[$idx] ?? '')) : '';
        $created = 0;
        $updated = 0;
        $skipped = 0;
        foreach (array_slice($rows, $headerIdx + 1, null, true) as $row) {
            $statusText = $cell($row, $cols['status']);
            $price = (int) preg_replace('/\D+/', '', $cell($row, $cols['price']));
            if ($statusText === '' && $price === 0) {
                continue; // 빈 행
            }

            // 상태 텍스트 → pay_state (승인취소/환불 판정을 승인보다 먼저)
            $state = match (true) {
                str_contains($statusText, '승인취소') || str_contains($statusText, '환불') => 9,
                str_contains($statusText, '취소') => 8,
                str_contains($statusText, '승인') || str_contains($statusText, '완료') => 4,
                default => 1,
            };

            // 결제일 파싱 — 엑셀 날짜 셀(숫자) 또는 문자열
            $rawDate = $cols['date'] !== null ? ($row[$cols['date']] ?? null) : null;
            $when = null;
            if (is_numeric($rawDate) && (float) $rawDate > 20000) {
                $when = Carbon::instance(ExcelDate::excelToDateTimeObject((float) $rawDate));
            } elseif (is_string($rawDate) && trim($rawDate) !== '') {
                try {
                    $when = Carbon::parse(str_replace(['.', '/'], '-', trim($rawDate)));
                } catch (\Throwable) {
                    $when = null;
                }
            }

            $mulNo = $cell($row, $cols['mul_no']);
            if ($mulNo === '') {
                // 요청번호가 없는 양식 — 내용 기반 해시로 중복 방지 키 생성
                $mulNo = 'IMP-'.substr(hash('sha256', implode('|', [
                    $when?->format('Y-m-d H:i') ?? '', $price,
                    $cell($row, $cols['buyer']), $cell($row, $cols['goodname']),
                ])), 0, 20);
            }

            $payment = PayappPayment::firstOrNew(['mul_no' => $mulNo]);
            $isNew = ! $payment->exists;
            $payment->fill([
                'pay_state' => $state,
                'price' => $price ?: $payment->price,
                'goodname' => mb_substr($cell($row, $cols['goodname']), 0, 200) ?: $payment->goodname,
                'buyer' => mb_substr($cell($row, $cols['buyer']), 0, 100) ?: $payment->buyer,
                'recvphone' => mb_substr($cell($row, $cols['phone']), 0, 30) ?: $payment->recvphone,
                'pay_type' => mb_substr($cell($row, $cols['pay_type']), 0, 30) ?: $payment->pay_type,
            ]);
            if ($state === 4 && ! $payment->paid_at) {
                $payment->paid_at = $when ?? now();
            }
            if ($when && ! $payment->requested_at) {
                $payment->requested_at = $when;
            }
            $payment->save();
            // 목록 정렬/기간 필터가 결제일 기준이 되도록 수신 시각을 결제일로 지정
            if ($when && $isNew) {
                $payment->forceFill(['created_at' => $when])->saveQuietly();
            }
            $isNew ? $created++ : $updated++;
        }

        return response()->json([
            'message' => "가져오기 완료 — 신규 {$created}건, 갱신 {$updated}건".($skipped ? ", 건너뜀 {$skipped}건" : ''),
            'created' => $created,
            'updated' => $updated,
        ]);
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
