<?php

namespace App\Services;

use App\Models\Estimate;
use Illuminate\Support\Facades\Http;

/**
 * 페이앱(PayApp) 결제 연동 클라이언트.
 *
 * REST 방식: https://api.payapp.kr/oapi/apiLoad.html 에 FORM POST,
 * 응답은 URL 인코딩된 key=value 쿼리 문자열.
 * 결제 완료/취소는 feedbackurl로 페이앱이 POST 통지 (연동 KEY/VALUE로 검증).
 * 모든 송수신은 storage/logs/payapp.log 에 기록해 서버에서 진단 가능.
 */
class PayAppClient
{
    private const MAX_LOG_BYTES = 5 * 1024 * 1024;

    /** 페이앱 pay_state — 결제완료 */
    public const STATE_PAID = 4;

    /** 승인취소(환불) 상태 값들 */
    public const STATES_REFUNDED = [9, 64];

    /** 요청취소 상태 값들 (구매자/판매자/페이앱) */
    public const STATES_REQUEST_CANCELLED = [8, 16, 32];

    public function isConfigured(): bool
    {
        return (string) config('services.payapp.userid') !== ''
            && (string) config('services.payapp.linkkey') !== ''
            && (string) config('services.payapp.linkval') !== '';
    }

    /**
     * 견적서 결제요청 생성 — 성공 시 결제 URL(payurl)을 받아 견적서에 저장.
     *
     * @param  bool  $sendSms  true면 페이앱이 의뢰자 휴대폰으로 결제요청 문자를 발송
     * @return array{ok:bool, error?:string, mul_no?:string, payurl?:string}
     */
    public function requestPayment(Estimate $estimate, bool $sendSms = false): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'error' => '페이앱 연동 정보가 설정되지 않았습니다 (.env PAYAPP_USERID/LINKKEY/LINKVAL).'];
        }
        if ((int) $estimate->total_amount < 1000) {
            return ['ok' => false, 'error' => '결제 금액이 너무 적습니다 (총 견적 금액 확인).'];
        }
        $phone = preg_replace('/\D+/', '', (string) $estimate->client_phone);
        if ($phone === '') {
            return ['ok' => false, 'error' => '의뢰자 연락처가 없습니다. 주문 정보의 연락처를 먼저 입력해주세요.'];
        }

        $firstItem = collect($estimate->product_items ?? [])->first()['name']
            ?? collect($estimate->service_items ?? [])->first()['name']
            ?? null;
        $count = count($estimate->product_items ?? []) + count($estimate->service_items ?? []);
        $goodname = $firstItem
            ? mb_substr($firstItem, 0, 50).($count > 1 ? ' 외 '.($count - 1).'건' : '')
            : '견적서 #'.$estimate->id;

        $result = $this->call([
            'cmd' => 'payrequest',
            'userid' => config('services.payapp.userid'),
            'goodname' => $goodname,
            'price' => (int) $estimate->total_amount,
            'recvphone' => $phone,
            'smsuse' => $sendSms ? 'y' : 'n',
            'feedbackurl' => route('payapp.feedback'),
            'returnurl' => $estimate->publicUrl(),
            'var1' => (string) $estimate->id,
            'var2' => $this->feedbackToken($estimate),
        ]);

        if (($result['state'] ?? null) !== '1') {
            return ['ok' => false, 'error' => '페이앱 오류: '.($result['errorMessage'] ?? $result['errormessage'] ?? '알 수 없는 응답')];
        }

        return ['ok' => true, 'mul_no' => (string) $result['mul_no'], 'payurl' => (string) $result['payurl']];
    }

    /**
     * 결제요청 취소 (아직 결제 전인 요청 철회).
     *
     * @return array{ok:bool, error?:string}
     */
    public function cancelRequest(Estimate $estimate): array
    {
        if (! $estimate->payapp_mul_no) {
            return ['ok' => false, 'error' => '취소할 결제요청이 없습니다.'];
        }

        $result = $this->call([
            'cmd' => 'paycancel',
            'userid' => config('services.payapp.userid'),
            'linkkey' => config('services.payapp.linkkey'),
            'mul_no' => $estimate->payapp_mul_no,
            'cancelmemo' => '견적서 #'.$estimate->id.' 결제요청 취소',
        ]);

        if (($result['state'] ?? null) !== '1') {
            return ['ok' => false, 'error' => '페이앱 오류: '.($result['errorMessage'] ?? $result['errormessage'] ?? '알 수 없는 응답')];
        }

        return ['ok' => true];
    }

    /**
     * feedbackurl 통지 검증 — 판매자 ID + 연동 KEY/VALUE + 견적서별 토큰 대조.
     */
    public function verifyFeedback(array $payload, ?Estimate $estimate): bool
    {
        if (($payload['userid'] ?? null) !== config('services.payapp.userid')) {
            return false;
        }
        // 페이앱은 통지에 연동 KEY/VALUE를 함께 보냄 — 둘 중 오는 값으로 대조
        $linkOk = (isset($payload['linkkey']) && hash_equals((string) config('services.payapp.linkkey'), (string) $payload['linkkey']))
            || (isset($payload['linkval']) && hash_equals((string) config('services.payapp.linkval'), (string) $payload['linkval']));
        if (! $linkOk) {
            return false;
        }
        // var2 = 견적서별 위조 방지 토큰
        if ($estimate && isset($payload['var2']) && ! hash_equals($this->feedbackToken($estimate), (string) $payload['var2'])) {
            return false;
        }

        return true;
    }

    /** 견적서별 통지 위조 방지 토큰 (var2) */
    public function feedbackToken(Estimate $estimate): string
    {
        return hash('sha256', config('app.key').'|payapp|'.$estimate->id);
    }

    /**
     * 페이앱 API 호출 — 응답(key=value 쿼리 문자열)을 배열로 파싱.
     *
     * @return array<string, string>
     */
    private function call(array $params): array
    {
        try {
            $res = Http::asForm()->timeout(15)->connectTimeout(7)
                ->retry(2, 700, throw: false)
                ->post((string) config('services.payapp.api_url'), $params);
            $body = $res->body();
            parse_str($body, $parsed);
            $this->log($params['cmd'] ?? '?', $params, $body);

            return array_map(fn ($v) => is_string($v) ? $v : json_encode($v), $parsed);
        } catch (\Throwable $e) {
            $this->log($params['cmd'] ?? '?', $params, '통신 실패: '.$e->getMessage());

            return ['state' => '0', 'errorMessage' => '페이앱 서버 통신 실패 (네트워크)'];
        }
    }

    /** payapp.log 기록 — 요청 파라미터(전화번호 마스킹) + 원본 응답 */
    public function log(string $tag, array $params, string $detail): void
    {
        try {
            $path = storage_path('logs/payapp.log');
            if (file_exists($path) && filesize($path) > self::MAX_LOG_BYTES) {
                @unlink($path);
            }
            if (isset($params['recvphone'])) {
                $params['recvphone'] = substr((string) $params['recvphone'], 0, 3).'****';
            }
            unset($params['linkkey'], $params['linkval']);
            @file_put_contents($path, sprintf(
                "[%s] %s\nreq=%s\nres=%s\n\n",
                now()->format('Y-m-d H:i:s'),
                $tag,
                json_encode($params, JSON_UNESCAPED_UNICODE),
                $detail
            ), FILE_APPEND);
        } catch (\Throwable) {
            // 진단 로그 실패는 무시
        }
    }
}
