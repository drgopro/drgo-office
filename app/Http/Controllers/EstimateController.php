<?php

namespace App\Http\Controllers;

use App\Models\Estimate;
use App\Models\Setting;
use App\Services\PayAppClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class EstimateController extends Controller
{
    public function index()
    {
        return view('estimates.index');
    }

    public function estimates(Request $request)
    {
        $query = Estimate::with('creator')
            ->where('status', '!=', 'temp')
            ->orderBy('created_at', 'desc');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('client_name', 'like', "%{$search}%")
                    ->orWhere('client_nickname', 'like', "%{$search}%")
                    ->orWhere('id', $search);
            });
        }

        return response()->json($query->limit(100)->get());
    }

    public function store()
    {
        $estimate = Estimate::create([
            'status' => 'temp',
            'product_items' => [],
            'service_items' => [],
            'product_total' => 0,
            'service_total' => 0,
            'total_amount' => 0,
            'validity_days' => 3,
            'created_by' => Auth::id(),
        ]);

        return response()->json($estimate, 201);
    }

    public function edit(Estimate $estimate)
    {
        $estimate->load('client', 'creator');
        $settings = Setting::getMany([
            'seller_name', 'seller_biz_no', 'seller_address',
            'seller_biz_type', 'seller_biz_item', 'seller_phone',
        ]);

        return view('estimates.edit', compact('estimate', 'settings'));
    }

    public function update(Request $request, Estimate $estimate, PayAppClient $payapp)
    {
        $validated = $request->validate([
            'client_id' => 'nullable|exists:clients,id',
            'client_name' => 'nullable|string|max:100',
            'client_nickname' => 'nullable|string|max:100',
            'client_phone' => 'nullable|string|max:50',
            'product_items' => 'nullable|array',
            'service_items' => 'nullable|array',
            // 'temp'도 허용 — 신규 견적서 작성 직후 status가 'temp'로 남아있을 수 있음
            'status' => 'nullable|in:temp,created,editing,completed,issued,paid,hold',
            'memo' => 'nullable|string',
        ]);

        try {
            $productTotal = (int) collect($validated['product_items'] ?? [])->sum('subtotal');
            $serviceTotal = (int) collect($validated['service_items'] ?? [])->sum('amount');

            // temp → created로 자동 전환 (첫 저장 시)
            if ($estimate->status === 'temp' && (! isset($validated['status']) || $validated['status'] === 'temp')) {
                $validated['status'] = 'created';
            }

            $becameIssued = ($validated['status'] ?? null) === 'issued' && $estimate->status !== 'issued';

            $estimate->update([
                ...$validated,
                'product_total' => $productTotal,
                'service_total' => $serviceTotal,
                'total_amount' => $productTotal + $serviceTotal,
                'issued_at' => $becameIssued ? now() : $estimate->issued_at,
            ]);

            // 발행완료로 전환 시 페이앱 결제요청 자동 생성 (실패해도 저장은 유지)
            $warning = $becameIssued ? $this->ensurePayappRequest($estimate->fresh(), $payapp) : null;

            return response()->json([
                ...$estimate->fresh()->toArray(),
                'payapp_warning' => $warning,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => '견적서 저장 실패: '.$e->getMessage(),
                'exception' => class_basename($e),
                'file' => basename($e->getFile()).':'.$e->getLine(),
            ], 500);
        }
    }

    /**
     * 발행완료 처리 — 의뢰자 페이지의 결제 버튼 활성화를 위해
     * 페이앱 결제요청도 자동 생성한다 (실패해도 발행은 유지, 경고만 반환).
     */
    public function issue(Estimate $estimate, PayAppClient $payapp)
    {
        $estimate->update([
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        $warning = $this->ensurePayappRequest($estimate, $payapp);

        return response()->json([
            ...$estimate->fresh()->toArray(),
            'payapp_warning' => $warning,
            'public_url' => $estimate->publicUrl(),
        ]);
    }

    /**
     * 페이앱 결제요청이 없으면 생성 시도. 실패 사유를 경고 문자열로 반환 (성공 시 null).
     */
    private function ensurePayappRequest(Estimate $estimate, PayAppClient $payapp): ?string
    {
        if ($estimate->payapp_payurl) {
            return null; // 이미 결제요청 있음
        }
        if (! $payapp->isConfigured()) {
            return '페이앱 연동 정보가 설정되지 않아 결제 버튼 없이 발행됩니다.';
        }

        $result = $payapp->requestPayment($estimate);
        if (! $result['ok']) {
            return '결제요청 생성 실패: '.$result['error'];
        }

        $estimate->update([
            'payapp_mul_no' => $result['mul_no'],
            'payapp_payurl' => $result['payurl'],
            'payapp_state' => 1,
            'payapp_requested_at' => now(),
            'payapp_paid_at' => null,
        ]);

        return null;
    }

    public function print(Estimate $estimate)
    {
        $settings = Setting::getMany([
            'seller_name', 'seller_biz_no', 'seller_address',
            'seller_biz_type', 'seller_biz_item', 'seller_phone',
        ]);

        return view('estimates.print', compact('estimate', 'settings'));
    }

    /**
     * 의뢰자용 공개 견적서 — 난수 토큰으로만 접근 (로그인 불필요, ID 추측 불가).
     * 결제요청이 생성돼 있으면 하단에 페이앱 결제 버튼 노출.
     */
    public function publicView(string $token)
    {
        abort_if(strlen($token) < 32, 404);
        $estimate = Estimate::where('share_token', $token)->firstOrFail();

        $settings = Setting::getMany([
            'seller_name', 'seller_biz_no', 'seller_address',
            'seller_biz_type', 'seller_biz_item', 'seller_phone',
        ]);

        return view('estimates.print', [
            'estimate' => $estimate,
            'settings' => $settings,
            'publicMode' => true,
        ]);
    }

    /**
     * 페이앱 결제요청 생성 — 공개 견적서의 결제 버튼 활성화.
     * 금액/연락처 변경 후 다시 누르면 새 결제요청으로 교체된다.
     */
    public function payappRequest(Estimate $estimate, PayAppClient $payapp)
    {
        if ($estimate->status === 'paid') {
            return response()->json(['message' => '이미 결제 완료된 견적서입니다.'], 422);
        }

        $result = $payapp->requestPayment($estimate);
        if (! $result['ok']) {
            return response()->json(['message' => $result['error']], 422);
        }

        $estimate->update([
            'payapp_mul_no' => $result['mul_no'],
            'payapp_payurl' => $result['payurl'],
            'payapp_state' => 1,
            'payapp_requested_at' => now(),
            'payapp_paid_at' => null,
        ]);

        return response()->json([
            'message' => '결제요청이 생성되었습니다.',
            'payurl' => $result['payurl'],
            'public_url' => $estimate->publicUrl(),
        ]);
    }

    /** 페이앱 결제요청 취소 — 공개 견적서의 결제 버튼 비활성화 */
    public function payappCancel(Estimate $estimate, PayAppClient $payapp)
    {
        if ($estimate->status === 'paid') {
            return response()->json(['message' => '이미 결제 완료된 건은 페이앱 관리자에서 승인취소해주세요.'], 422);
        }

        $result = $payapp->cancelRequest($estimate);
        if (! $result['ok']) {
            return response()->json(['message' => $result['error']], 422);
        }

        $estimate->update([
            'payapp_mul_no' => null,
            'payapp_payurl' => null,
            'payapp_state' => 16,
        ]);

        return response()->json(['message' => '결제요청이 취소되었습니다.']);
    }

    /**
     * 페이앱 결제 결과 통지(feedbackurl) 수신 — 인증 세션 밖에서 호출됨.
     * 검증 실패 시에도 200 외 응답으로 페이앱이 재시도하도록 둔다.
     */
    public function payappFeedback(Request $request, PayAppClient $payapp)
    {
        // 페이앱의 URL 사전 점검(GET/빈 요청)은 처리 없이 통과 — 단 진단을 위해 기록
        // (http→https 리다이렉트로 POST가 GET으로 강등되면 여기로 들어와 결제 데이터가 유실됨)
        if (! $request->isMethod('post') || ! $request->has('pay_state')) {
            $payapp->log('feedback-사전점검', [], sprintf(
                'method=%s ip=%s pay_state=%s query=%s',
                $request->method(),
                $request->ip(),
                $request->input('pay_state', '(없음)'),
                json_encode($request->except(['linkkey', 'linkval']), JSON_UNESCAPED_UNICODE)
            ));

            return response('OK');
        }

        $payload = $request->all();
        $estimate = null;
        if ($id = (int) ($payload['var1'] ?? 0)) {
            $estimate = Estimate::find($id);
        }
        if (! $estimate && ! empty($payload['mul_no'])) {
            $estimate = Estimate::where('payapp_mul_no', $payload['mul_no'])->first();
        }

        $safePayload = collect($payload)->except(['linkkey', 'linkval'])->all();
        if (! $estimate || ! $payapp->verifyFeedback($payload, $estimate)) {
            $payapp->log('feedback-거부', [], json_encode($safePayload, JSON_UNESCAPED_UNICODE));

            return response('FAIL', 400);
        }

        $state = (int) ($payload['pay_state'] ?? 0);
        $estimate->payapp_state = $state;

        if ($state === PayAppClient::STATE_PAID) {
            // 금액 대조 — 요청 후 견적이 수정된 경우 결제완료 처리 보류 (로그로 확인)
            $paidPrice = (int) ($payload['price'] ?? 0);
            if ($paidPrice === (int) $estimate->total_amount) {
                $estimate->status = 'paid';
                $estimate->payapp_paid_at = now();
            } else {
                Log::warning("페이앱 결제금액 불일치: 견적서 #{$estimate->id} 견적 {$estimate->total_amount}원 vs 결제 {$paidPrice}원");
            }
        } elseif (in_array($state, PayAppClient::STATES_REFUNDED, true)) {
            if ($estimate->status === 'paid') {
                $estimate->status = 'issued'; // 환불 → 발행완료로 복귀 (결제 버튼 다시 노출)
            }
            $estimate->payapp_paid_at = null;
        } elseif (in_array($state, PayAppClient::STATES_REQUEST_CANCELLED, true)) {
            $estimate->payapp_payurl = null;
        }

        $estimate->save();
        $payapp->log('feedback-처리', [], json_encode($safePayload, JSON_UNESCAPED_UNICODE));

        return response('SUCCESS');
    }

    public function destroy(Estimate $estimate)
    {
        $estimate->delete();

        return response()->json(['message' => '삭제되었습니다.']);
    }
}
