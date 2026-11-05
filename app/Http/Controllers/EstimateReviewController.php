<?php

namespace App\Http\Controllers;

use App\Models\Estimate;
use App\Services\EstimateReview\EstimateReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 견적서 검토 API — 판정 실행과 답변 기록.
 *
 * 상태는 estimates.review_state(JSON)에 저장:
 * {fingerprint, keys, answers:{key:{type,choice|reason,note,by,at}}, done, reviewer, reviewed_at}
 * 품목이 바뀌면(지문 불일치) done이 풀리고, 같은 키의 답변은 유지된다(명세 7.4).
 */
class EstimateReviewController extends Controller
{
    public function __construct(private EstimateReviewService $service) {}

    /** 검토 실행 — 저장 전 품목도 받을 수 있게 품목 배열을 직접 받는다 */
    public function run(Request $request, Estimate $estimate): JsonResponse
    {
        $data = $request->validate([
            'product_items' => 'nullable|array|max:300',
            'product_items.*.product_id' => 'nullable|integer',
            'product_items.*.name' => 'required_with:product_items|string|max:200',
            'product_items.*.qty' => 'nullable|integer|min:1|max:9999',
            'product_items.*.sale_price' => 'nullable|numeric',
            'product_items.*.is_service' => 'nullable|boolean',
            'product_items.*.manual' => 'nullable|boolean',
            'product_items.*.replaced' => 'nullable|boolean',
        ]);

        $result = $this->service->reviewFor($estimate, $data['product_items'] ?? null);

        $state = $estimate->review_state ?? [];
        $keys = array_column($result['findings'], 'key');

        // 같은 키의 답변 유지, 사라진 발견의 답변은 정리 (7.4)
        $answers = array_intersect_key($state['answers'] ?? [], array_flip($keys));

        $done = ! empty($state['done'])
            && ($state['fingerprint'] ?? null) === $result['fingerprint']
            && count($answers) === count($keys);

        $estimate->forceFill(['review_state' => [
            'fingerprint' => $result['fingerprint'],
            'context' => $result['context'] ?? 'single',
            'keys' => $keys,
            'answers' => $answers,
            'done' => $done,
            'reviewer' => $state['reviewer'] ?? null,
            'reviewed_at' => $done ? ($state['reviewed_at'] ?? null) : null,
        ]])->save();

        return response()->json([
            'findings' => $result['findings'],
            'passed' => $result['passed'],
            'paths' => $result['paths'],
            'counts' => $result['counts'],
            'fingerprint' => $result['fingerprint'],
            'context' => $result['context'] ?? 'single',
            'answers' => (object) $answers,
            'done' => $done,
        ]);
    }

    /** 발견 답변 — ask는 선택지(ans), 누락류는 제외 사유(ex) */
    public function answer(Request $request, Estimate $estimate): JsonResponse
    {
        $data = $request->validate([
            'key' => 'required|string|max:300',
            'type' => 'required|in:ans,ex',
            'choice' => 'nullable|string|max:120',
            'reason' => 'nullable|string|max:120',
            'note' => 'nullable|string|max:300',
        ]);

        $state = $estimate->review_state ?? [];
        if (! in_array($data['key'], $state['keys'] ?? [], true)) {
            return response()->json(['message' => '검토를 먼저 실행하세요. 이 항목은 최근 검토 결과에 없습니다.'], 422);
        }

        $state['answers'][$data['key']] = [
            'type' => $data['type'],
            'choice' => $data['choice'] ?? null,
            'reason' => $data['reason'] ?? null,
            'note' => $data['note'] ?? null,
            'by' => $request->user()?->display_name,
            'at' => now()->format('Y-m-d H:i'),
        ];
        $state['done'] = false;
        $estimate->forceFill(['review_state' => $state])->save();

        return response()->json(['ok' => true, 'answers' => (object) $state['answers']]);
    }

    /** 답변 되돌리기 */
    public function undo(Request $request, Estimate $estimate): JsonResponse
    {
        $data = $request->validate(['key' => 'required|string|max:300']);
        $state = $estimate->review_state ?? [];
        unset($state['answers'][$data['key']]);
        $state['done'] = false;
        $estimate->forceFill(['review_state' => $state])->save();

        return response()->json(['ok' => true, 'answers' => (object) ($state['answers'] ?? [])]);
    }

    /** 검토 완료 — 모든 발견에 답이 있어야 통과 */
    public function finish(Request $request, Estimate $estimate): JsonResponse
    {
        $state = $estimate->review_state ?? [];
        $keys = $state['keys'] ?? null;
        if ($keys === null) {
            return response()->json(['message' => '검토를 먼저 실행하세요.'], 422);
        }
        $missing = array_diff($keys, array_keys($state['answers'] ?? []));
        if ($missing !== []) {
            return response()->json(['message' => '아직 확인하지 않은 항목이 '.count($missing).'건 있습니다.'], 422);
        }

        // 저장된 품목 기준 지문과 일치해야 완료 인정 — 저장 없이 품목만 바꾼 검토 방지 (차수는 트리 기준)
        $current = $this->service->fingerprintFor($estimate);
        if (($state['fingerprint'] ?? null) !== $current) {
            return response()->json(['message' => '검토 후 품목이 바뀌었습니다. 저장하고 다시 검토해 주세요.'], 422);
        }

        $state['done'] = true;
        $state['reviewer'] = $request->user()?->display_name;
        $state['reviewed_at'] = now()->format('Y-m-d H:i');
        $estimate->forceFill(['review_state' => $state])->save();

        return response()->json(['ok' => true, 'reviewer' => $state['reviewer'], 'reviewed_at' => $state['reviewed_at']]);
    }
}
