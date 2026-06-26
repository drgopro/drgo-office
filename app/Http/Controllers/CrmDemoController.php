<?php

namespace App\Http\Controllers;

use App\Models\DemoPayment;
use App\Models\DemoProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CrmDemoController extends Controller
{
    public function index()
    {
        return view('crm-demo.index', ['crm' => config('crm')]);
    }

    public function show(DemoProject $project)
    {
        $project->load('consultations', 'feedbacks');

        return view('crm-demo.show', ['crm' => config('crm'), 'p' => $project]);
    }

    public function saveOverview(Request $request, DemoProject $project): JsonResponse
    {
        $v = $request->validate(['overview' => 'nullable|string|max:2000']);
        $project->update(['overview' => $v['overview'] ?? null]);

        return response()->json(['ok' => true]);
    }

    public function addConsultation(Request $request, DemoProject $project): JsonResponse
    {
        $v = $request->validate([
            'content' => 'required|string|max:2000',
            'consult_type' => 'nullable|string|max:30',
            'result' => 'nullable|string|max:30',
            'consulted_at' => 'nullable|date',
        ]);
        $c = $project->consultations()->create([
            'content' => $v['content'],
            'consult_type' => $v['consult_type'] ?? null,
            'result' => $v['result'] ?? null,
            'consulted_at' => $v['consulted_at'] ?? now()->format('Y-m-d'),
            'created_by' => Auth::id(),
        ]);

        return response()->json(['ok' => true, 'id' => $c->id], 201);
    }

    public function deleteConsultation(DemoProject $project, $cid): JsonResponse
    {
        $project->consultations()->where('id', $cid)->delete();

        return response()->json(['ok' => true]);
    }

    public function addFeedback(Request $request, DemoProject $project): JsonResponse
    {
        $v = $request->validate(['content' => 'required|string|max:2000']);
        $f = $project->feedbacks()->create(['content' => $v['content'], 'created_by' => Auth::id()]);

        return response()->json(['ok' => true, 'id' => $f->id], 201);
    }

    public function deleteFeedback(DemoProject $project, $fid): JsonResponse
    {
        $project->feedbacks()->where('id', $fid)->delete();

        return response()->json(['ok' => true]);
    }

    /** 프로젝트 목록 */
    public function projects(Request $request): JsonResponse
    {
        $q = DemoProject::query()
            ->withSum('payments as payments_net', 'amount')
            ->orderByDesc('id');
        if ($type = $request->query('project_type')) {
            $q->where('project_type', $type);
        }

        $rows = $q->limit(200)->get()->map(fn ($p) => $this->serialize($p));

        // 잔금 필터: 미수 잔금(has_balance)이 남은 건만
        if ($request->boolean('billing_only')) {
            $rows = $rows->filter(fn ($r) => $r['balance_outstanding'] > 0)->values();
        }

        return response()->json($rows);
    }

    public function store(Request $request): JsonResponse
    {
        $crm = config('crm');
        $validated = $request->validate([
            'client_name' => 'nullable|string|max:100',
            'client_id' => 'nullable|integer',
            'client_phone' => 'nullable|string|max:50',
            'client_address' => 'nullable|string|max:300',
            'requester_type' => ['nullable', 'string', 'in:'.implode(',', array_keys($crm['requester_types']))],
            'project_type' => ['required', 'string', 'in:'.implode(',', array_keys($crm['project_types']))],
            'work_type' => 'nullable|string|max:60',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:60',
            'free_name' => 'nullable|string|max:200',
            'overview' => 'nullable|string|max:2000',
        ]);

        if (empty($validated['tags'])) {
            return response()->json(['message' => '대주제 태그를 1개 이상 선택하세요.'], 422);
        }

        $firstStage = $crm['project_types'][$validated['project_type']]['pipeline'][0]['key'] ?? 'consult';

        $p = DemoProject::create([
            ...$validated,
            'stage' => $firstStage,
            'status' => 'active',
            'created_by' => Auth::id(),
        ]);

        return response()->json($this->serialize($p), 201);
    }

    public function update(Request $request, DemoProject $project): JsonResponse
    {
        $crm = config('crm');
        $validated = $request->validate([
            'client_name' => 'nullable|string|max:100',
            'client_id' => 'nullable|integer',
            'client_phone' => 'nullable|string|max:50',
            'client_address' => 'nullable|string|max:300',
            'requester_type' => ['nullable', 'string', 'in:'.implode(',', array_keys($crm['requester_types']))],
            'project_type' => ['required', 'string', 'in:'.implode(',', array_keys($crm['project_types']))],
            'work_type' => 'nullable|string|max:60',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:60',
            'free_name' => 'nullable|string|max:200',
            'overview' => 'nullable|string|max:2000',
        ]);
        if (empty($validated['tags'])) {
            return response()->json(['message' => '대주제 태그를 1개 이상 선택하세요.'], 422);
        }

        // 유형이 바뀌어 현재 stage가 새 파이프라인에 없으면 첫 단계로 보정
        $stages = collect($crm['project_types'][$validated['project_type']]['pipeline'] ?? [])->pluck('key')->all();
        if (! in_array($project->stage, $stages, true)) {
            $validated['stage'] = $stages[0] ?? 'consult';
        }

        $project->update($validated);

        return response()->json($this->serialize($project->fresh()));
    }

    public function updateStage(Request $request, DemoProject $project): JsonResponse
    {
        $validated = $request->validate(['stage' => 'required|string|max:30']);
        $crm = config('crm');
        $stages = collect($crm['project_types'][$project->project_type]['pipeline'] ?? [])->pluck('key')->all();
        if (! in_array($validated['stage'], $stages, true)) {
            return response()->json(['message' => '유효하지 않은 단계입니다.'], 422);
        }
        $project->update(['stage' => $validated['stage']]);
        // 마지막 단계면 done 처리
        if ($validated['stage'] === end($stages) && $project->status === 'active') {
            $project->update(['status' => 'done']);
        }

        return response()->json($this->serialize($project->fresh()));
    }

    public function cancel(Request $request, DemoProject $project): JsonResponse
    {
        $validated = $request->validate(['cancel_reason' => 'required|string|max:100']);
        $project->update(['status' => 'cancelled', 'cancel_reason' => $validated['cancel_reason']]);

        return response()->json($this->serialize($project->fresh()));
    }

    public function destroy(DemoProject $project): JsonResponse
    {
        $project->delete();

        return response()->json(['ok' => true]);
    }

    // ── 결제 내역 (운영 동일: charge/refund/cancel 트랜잭션) ──

    /** 결제 트랜잭션 목록 + charge별 누적 환불액 */
    public function payments(DemoProject $project): JsonResponse
    {
        $rows = $project->payments()->orderByDesc('created_at')->orderByDesc('id')->get();

        $byCharge = [];
        foreach ($rows as $r) {
            if (in_array($r->type, ['refund', 'cancel'], true) && $r->parent_payment_id) {
                $byCharge[$r->parent_payment_id] = ($byCharge[$r->parent_payment_id] ?? 0) + abs($r->amount);
            }
        }

        return response()->json([
            'has_balance' => (bool) $project->has_balance,
            'balance_amount' => (int) $project->balance_amount,
            'payments' => $rows->map(fn ($r) => [
                'id' => $r->id,
                'parent_payment_id' => $r->parent_payment_id,
                'type' => $r->type,
                'amount' => $r->amount,
                'items' => $r->items ?? [],
                'method' => $r->method,
                'paid_at' => $r->paid_at?->format('Y-m-d'),
                'memo' => $r->memo,
                'created_at' => $r->created_at->format('Y-m-d H:i'),
                'refunded_amount' => $r->type === 'charge' ? ($byCharge[$r->id] ?? 0) : 0,
                'is_fully_refunded' => $r->type === 'charge' && ($byCharge[$r->id] ?? 0) >= $r->amount,
            ])->values(),
        ]);
    }

    /** 결제(charge) 등록 */
    public function savePayment(Request $request, DemoProject $project): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'nullable|integer|min:0',
            'paid_at' => 'nullable|date',
            'method' => 'nullable|string|max:30',
            'items' => 'nullable|array',
            'items.*.name' => 'nullable|string|max:200',
            'items.*.qty' => 'nullable|integer|min:0',
            'items.*.price' => 'nullable|integer|min:0',
            'memo' => 'nullable|string|max:1000',
            'has_balance' => 'nullable|boolean',
            'balance_amount' => 'nullable|integer|min:0',
        ]);

        $items = array_values(array_filter($validated['items'] ?? [], fn ($i) => ! empty($i['name'])));

        $project->payments()->create([
            'type' => 'charge',
            'amount' => (int) ($validated['amount'] ?? 0),
            'items' => $items,
            'method' => $validated['method'] ?? null,
            'paid_at' => $validated['paid_at'] ?? now()->format('Y-m-d'),
            'memo' => $validated['memo'] ?? null,
            'recorded_by' => Auth::id(),
        ]);

        $project->update([
            'has_balance' => ! empty($validated['has_balance']),
            'balance_amount' => ! empty($validated['has_balance']) ? ($validated['balance_amount'] ?? 0) : 0,
        ]);

        return response()->json(['ok' => true], 201);
    }

    /** 결제(charge) 수정 — charge만 허용 */
    public function updatePayment(Request $request, DemoProject $project, DemoPayment $payment): JsonResponse
    {
        if ($payment->demo_project_id !== $project->id) {
            return response()->json(['error' => '해당 프로젝트의 결제가 아닙니다.'], 404);
        }
        if ($payment->type !== 'charge') {
            return response()->json(['error' => '환불/취소 항목은 수정할 수 없습니다. 삭제 후 재처리하세요.'], 422);
        }

        $validated = $request->validate([
            'amount' => 'nullable|integer|min:0',
            'paid_at' => 'nullable|date',
            'method' => 'nullable|string|max:30',
            'items' => 'nullable|array',
            'items.*.name' => 'nullable|string|max:200',
            'items.*.qty' => 'nullable|integer|min:0',
            'items.*.price' => 'nullable|integer|min:0',
            'memo' => 'nullable|string|max:1000',
        ]);

        if (isset($validated['items'])) {
            $items = array_values(array_filter($validated['items'], fn ($i) => ! empty($i['name'])));
            if (! isset($validated['amount'])) {
                $validated['amount'] = array_sum(array_map(fn ($it) => ((int) ($it['qty'] ?? 1)) * ((int) ($it['price'] ?? 0)), $items));
            }
            $validated['items'] = $items;
        }

        if (isset($validated['amount'])) {
            $refundedAbs = abs((int) $project->payments()->where('parent_payment_id', $payment->id)
                ->whereIn('type', ['refund', 'cancel'])->sum('amount'));
            if ($refundedAbs > 0 && (int) $validated['amount'] < $refundedAbs) {
                return response()->json(['message' => "이미 환불된 금액({$refundedAbs}원) 이상으로만 수정할 수 있습니다."], 422);
            }
        }

        $payment->update($validated);

        return response()->json(['ok' => true]);
    }

    /** 결제 내역 삭제 — charge 삭제 시 연결된 환불/취소도 함께 삭제 */
    public function destroyPayment(DemoProject $project, DemoPayment $payment): JsonResponse
    {
        if ($payment->demo_project_id !== $project->id) {
            return response()->json(['error' => '해당 프로젝트의 결제가 아닙니다.'], 404);
        }

        DB::transaction(function () use ($project, $payment) {
            if ($payment->type === 'charge') {
                $project->payments()->where('parent_payment_id', $payment->id)->delete();
            }
            $payment->delete();
        });

        return response()->json(['ok' => true]);
    }

    /** 환불 / 결제 취소 */
    public function refundPayment(Request $request, DemoProject $project): JsonResponse
    {
        $validated = $request->validate([
            'parent_payment_id' => 'required|integer',
            'type' => 'required|in:refund,cancel',
            'items' => 'nullable|array',
            'items.*.name' => 'nullable|string|max:200',
            'items.*.qty' => 'nullable|integer|min:0',
            'items.*.price' => 'nullable|integer|min:0',
            'amount' => 'nullable|integer|min:0',
            'reason' => 'nullable|string|max:500',
            'method' => 'nullable|string|max:30',
        ]);

        $parent = $project->payments()->findOrFail($validated['parent_payment_id']);
        if ($parent->type !== 'charge') {
            return response()->json(['error' => '결제(charge) 항목에 대해서만 환불할 수 있습니다.'], 422);
        }

        $alreadyRefunded = $project->payments()->where('parent_payment_id', $parent->id)
            ->whereIn('type', ['refund', 'cancel'])->sum('amount');
        $refundable = $parent->amount + $alreadyRefunded; // alreadyRefunded는 음수
        if ($parent->amount <= 0) {
            return response()->json(['error' => '0원 결제는 환불할 금액이 없습니다. 삭제 또는 수정 기능을 사용하세요.'], 422);
        }
        if ($refundable <= 0) {
            return response()->json(['error' => '이미 전액 환불된 결제입니다.'], 422);
        }

        $items = array_values(array_filter($validated['items'] ?? [], fn ($i) => ! empty($i['name'])));
        if ($validated['type'] === 'cancel') {
            $amount = $refundable;
        } elseif (! empty($items)) {
            $amount = array_sum(array_map(fn ($it) => ((int) ($it['qty'] ?? 1)) * ((int) ($it['price'] ?? 0)), $items));
        } else {
            $amount = (int) ($validated['amount'] ?? 0);
        }

        if ($amount <= 0) {
            return response()->json(['error' => '환불 금액이 0 이상이어야 합니다.'], 422);
        }
        if ($amount > $refundable) {
            return response()->json(['error' => "환불 가능 금액({$refundable}원)을 초과합니다."], 422);
        }

        $project->payments()->create([
            'parent_payment_id' => $parent->id,
            'type' => $validated['type'],
            'amount' => -$amount,
            'items' => $items ?: null,
            'method' => $validated['method'] ?? $parent->method,
            'paid_at' => now()->toDateString(),
            'memo' => $validated['reason'] ?? null,
            'recorded_by' => Auth::id(),
        ]);

        return response()->json(['ok' => true], 201);
    }

    // ── 태그 관리 ──
    public function tags(): JsonResponse
    {
        return response()->json(DB::table('demo_tags')->orderBy('sort_order')->orderBy('id')->get(['id', 'name']));
    }

    public function storeTag(Request $request): JsonResponse
    {
        $v = $request->validate(['name' => 'required|string|max:60|unique:demo_tags,name']);
        $order = (int) DB::table('demo_tags')->max('sort_order') + 1;
        $id = DB::table('demo_tags')->insertGetId(['name' => $v['name'], 'sort_order' => $order, 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['id' => $id, 'name' => $v['name']], 201);
    }

    public function destroyTag($id): JsonResponse
    {
        DB::table('demo_tags')->where('id', $id)->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * 직렬화 + 파이프라인/청구 계산
     */
    private function serialize(DemoProject $p): array
    {
        $crm = config('crm');
        $typeDef = $crm['project_types'][$p->project_type] ?? null;
        $pipeline = $typeDef['pipeline'] ?? [];
        $stageLabels = collect($pipeline)->pluck('label', 'key')->all();

        // 순 결제액 = 결제(charge) 합 - 환불/취소 합 (환불/취소는 음수 저장)
        $paymentNet = (int) ($p->payments_net ?? $p->payments()->sum('amount'));
        $balanceOutstanding = $p->has_balance ? (int) $p->balance_amount : 0;

        return [
            'id' => $p->id,
            'client_name' => $p->client_name,
            'client_id' => $p->client_id,
            'client_phone' => $p->client_phone,
            'client_address' => $p->client_address,
            'overview' => $p->overview,
            'requester_type' => $p->requester_type,
            'requester_type_label' => $crm['requester_types'][$p->requester_type]['label'] ?? null,
            'project_type' => $p->project_type,
            'project_type_label' => $typeDef['label'] ?? $p->project_type,
            'work_type' => $p->work_type,
            'tags' => $p->tags ?? [],
            'free_name' => $p->free_name,
            'stage' => $p->stage,
            'stage_label' => $stageLabels[$p->stage] ?? $p->stage,
            'pipeline' => $pipeline,
            'status' => $p->status,
            'cancel_reason' => $p->cancel_reason,
            'department' => $crm['departments'][$typeDef['department'] ?? ''] ?? null,
            'payment_net' => $paymentNet,
            'has_balance' => (bool) $p->has_balance,
            'balance_outstanding' => $balanceOutstanding,
            'created_at' => $p->created_at?->format('Y-m-d'),
        ];
    }
}
