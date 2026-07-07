<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Estimate;
use App\Models\Project;
use App\Models\ProjectFeedback;
use App\Models\ProjectPayment;
use App\Models\ProjectSubtag;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    // 목록
    public function index(Request $request)
    {
        $query = Project::with('client', 'assignedUser')
            ->where('status', '!=', 'cancelled');

        // 검색
        if ($search = $request->query('search')) {
            $query->whereHas('client', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('nickname', 'like', "%{$search}%");
            })->orWhere('name', 'like', "%{$search}%");
        }

        // 단계 필터 (단일/콤마 구분/배열 모두 지원)
        if ($stage = $request->query('stage')) {
            $stages = is_array($stage)
                ? array_values(array_filter($stage))
                : array_values(array_filter(array_map('trim', explode(',', (string) $stage))));
            if (! empty($stages)) {
                $query->whereIn('stage', $stages);
            }
        }

        // 유형 필터 (단일/콤마 구분/배열 모두 지원)
        if ($type = $request->query('project_type')) {
            $types = is_array($type)
                ? array_values(array_filter($type))
                : array_values(array_filter(array_map('trim', explode(',', (string) $type))));
            if (! empty($types)) {
                $query->whereIn('project_type', $types);
            }
        }

        // 태그 필터 — 선택한 태그 중 하나라도 대분류/소분류에 포함되면 매칭 (OR, 다른 필터와 동일)
        if ($tag = $request->query('tag')) {
            $tags = is_array($tag)
                ? array_values(array_filter($tag))
                : array_values(array_filter(array_map('trim', explode(',', (string) $tag))));
            if (! empty($tags)) {
                $query->where(function ($q) use ($tags) {
                    foreach ($tags as $t) {
                        $q->orWhereJsonContains('tags->major', $t)
                            ->orWhereJsonContains('tags->minor', $t);
                    }
                });
            }
        }

        $projects = $query->orderBy('created_at', 'desc')->paginate(20);

        // 필터 드롭다운용 태그 목록
        $tagOptions = [
            'major' => config('crm.major_tags', []),
            'minor' => ProjectSubtag::orderBy('sort_order')->orderBy('id')->pluck('name')->all(),
        ];

        return view('projects.index', compact('projects', 'tagOptions'));
    }

    // 등록
    public function store(Request $request, Client $client)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'project_type' => 'required|string|max:50|exists:consultation_types,key',
            'client_scale' => 'nullable|in:personal,studio,corporate,rental,broadcast_room',
            'work_type' => 'nullable|string|max:50',
            'overview' => 'nullable|string',
            'memo' => 'nullable|string', // 하위 호환 (구버전 클라이언트)
            'custom_data' => 'nullable|array',
            'tags' => 'nullable|array',
            'tags.major' => 'nullable|array',
            'tags.major.*' => 'string|max:60',
            'tags.minor' => 'nullable|array',
            'tags.minor.*' => 'string|max:60',
        ]);
        $validated['tags'] = $this->normalizeTags($request->input('tags'));
        if (isset($validated['memo']) && ! isset($validated['overview'])) {
            $validated['overview'] = $validated['memo'];
        }
        unset($validated['memo']);

        $validated['client_id'] = $client->id;
        $validated['assigned_user_id'] = Auth::id();
        $validated['stage'] = 'consulting';
        $validated['status'] = 'active';

        $project = Project::create($validated);

        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'id' => $project->id,
                'project' => $project,
                'redirect' => route('projects.show', $project),
            ], 201);
        }

        return redirect()->route('projects.show', $project)->with('success', '프로젝트가 생성되었습니다.');
    }

    /**
     * 태그 입력 정규화 → ['major'=>[], 'minor'=>[]] 또는 null.
     *
     * @return array{major: array<int,string>, minor: array<int,string>}|null
     */
    private function normalizeTags(mixed $tags): ?array
    {
        if (! is_array($tags)) {
            return null;
        }
        $clean = function ($list) {
            return array_values(array_filter(
                array_map(fn ($t) => trim((string) $t), (array) $list),
                fn ($t) => $t !== ''
            ));
        };
        $major = $clean($tags['major'] ?? []);
        $minor = $clean($tags['minor'] ?? []);
        if (empty($major) && empty($minor)) {
            return null;
        }

        return ['major' => $major, 'minor' => $minor];
    }

    // 상세
    public function show(Project $project)
    {
        $project->load('client', 'assignedUser', 'consultations.consultant', 'documents', 'memos.user');

        return view('projects.show', compact('project'));
    }

    // 피드백 추가
    public function storeMemo(Request $request, Project $project)
    {
        $validated = $request->validate([
            'content' => 'required|string|max:2000',
        ]);

        $feedback = $project->feedbacks()->create([
            'user_id' => Auth::id(),
            'content' => $validated['content'],
        ]);

        $feedback->load('user');

        return response()->json([
            'id' => $feedback->id,
            'content' => $feedback->content,
            'user_name' => $feedback->user?->display_name,
            'created_at' => $feedback->created_at->format('Y.m.d H:i'),
        ], 201);
    }

    // 피드백 삭제 (라우트 호환을 위해 메서드명/파라미터명 유지)
    public function destroyMemo(ProjectFeedback $memo)
    {
        $memo->delete();

        return response()->json(['message' => '삭제되었습니다.']);
    }

    // 단계 변경
    public function updateStage(Request $request, Project $project)
    {
        $request->validate([
            'stage' => 'required|in:consulting,equipment,proposal,estimate,payment,visit,as,done,cancelled',
            'cancel_reason' => 'nullable|string|max:50',
            'cancel_detail' => 'nullable|string|max:500',
        ]);

        $data = ['stage' => $request->stage];

        if ($request->stage === 'done') {
            $data['completed_at'] = now();
        }

        if ($request->stage === 'cancelled') {
            $data['cancel_reason'] = $request->cancel_reason;
            $data['cancel_detail'] = $request->cancel_detail;
            $data['cancelled_at'] = now();
        }

        $project->update($data);

        if ($request->wantsJson()) {
            return response()->json(['message' => '변경되었습니다.']);
        }

        return back()->with('success', '단계가 변경되었습니다.');
    }

    // 프로젝트 부분 수정 (이름, 프로젝트 개요 등)
    public function updateJson(Request $request, Project $project)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:200',
            'overview' => 'nullable|string',
            'memo' => 'nullable|string', // 하위 호환
            'project_type' => 'sometimes|string|max:50|exists:consultation_types,key',
            'client_scale' => 'sometimes|nullable|in:personal,studio,corporate,rental,broadcast_room',
            'work_type' => 'sometimes|nullable|string|max:50',
            'visit_report' => 'sometimes|nullable|string',
            'custom_data' => 'nullable|array',
            'tags' => 'sometimes|nullable|array',
            'tags.major' => 'nullable|array',
            'tags.major.*' => 'string|max:60',
            'tags.minor' => 'nullable|array',
            'tags.minor.*' => 'string|max:60',
        ]);

        // 'memo' (legacy) → 'overview' 매핑
        if (isset($validated['memo']) && ! isset($validated['overview'])) {
            $validated['overview'] = $validated['memo'];
        }
        unset($validated['memo']);

        if ($request->has('tags')) {
            $validated['tags'] = $this->normalizeTags($request->input('tags'));
        }

        $project->update($validated);

        return response()->json(['success' => true, 'project' => $project]);
    }

    /**
     * 결제 단계 — 이 프로젝트 또는 같은 의뢰자의 견적서 목록 (드롭다운용)
     */
    public function paymentEstimates(Project $project): JsonResponse
    {
        $query = Estimate::query();
        // 이 프로젝트에 직접 연결된 견적서, 또는 같은 의뢰자의 견적서 모두 후보
        $query->where(function ($q) use ($project) {
            $q->where('project_id', $project->id);
            if ($project->client_id) {
                $q->orWhere('client_id', $project->client_id);
            }
        });
        $estimates = $query->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'client_name', 'client_nickname', 'total_amount', 'product_total', 'service_total', 'status', 'issued_at', 'created_at', 'product_items', 'service_items', 'project_id'])
            ->map(fn ($e) => [
                'id' => $e->id,
                'client_name' => $e->client_name,
                'client_nickname' => $e->client_nickname,
                'total_amount' => $e->total_amount,
                'product_total' => $e->product_total,
                'service_total' => $e->service_total,
                'status' => $e->status,
                'is_linked' => $e->project_id === $project->id,
                'created_at' => $e->created_at?->format('Y-m-d'),
                'issued_at' => $e->issued_at?->format('Y-m-d'),
                'items_summary' => [
                    'products' => count($e->product_items ?? []),
                    'services' => count($e->service_items ?? []),
                ],
                // 결제 항목 자동 채우기용 — {name, qty, price} 형태로 정규화
                'payment_items' => collect($e->product_items ?? [])
                    ->map(fn ($it) => [
                        'name' => $it['name'] ?? '항목',
                        'qty' => (int) ($it['qty'] ?? 1),
                        'price' => (int) ($it['sale_price'] ?? $it['price'] ?? 0),
                    ])
                    ->concat(
                        collect($e->service_items ?? [])->map(fn ($it) => [
                            'name' => $it['name'] ?? '서비스',
                            'qty' => (int) ($it['qty'] ?? 1),
                            'price' => (int) ($it['amount'] ?? $it['price'] ?? 0),
                        ])
                    )
                    ->values()
                    ->all(),
            ]);

        return response()->json($estimates);
    }

    /**
     * 프로젝트 결제 정보 저장 (+선택적 stage 자동 진행)
     *
     * 입력 예:
     *   estimate_id (nullable) — 연결할 견적서
     *   amount       (int) — 결제 금액
     *   paid_at      (date) — 결제일
     *   method       (string nullable) — 결제 수단 (카드/현금/이체 등)
     *   items        (array nullable) — 수기 항목 [{name, qty, price}, ...]
     *   memo         (string nullable)
     *   mark_estimate_paid (bool) — 연결 견적서의 status를 'paid'로 갱신
     */
    public function savePayment(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'estimate_id' => 'nullable|integer|exists:estimates,id',
            'amount' => 'nullable|integer|min:0',
            'paid_at' => 'nullable|date',
            'method' => 'nullable|string|max:30',
            'items' => 'nullable|array',
            'items.*.name' => 'nullable|string|max:200',
            'items.*.qty' => 'nullable|integer|min:0',
            'items.*.price' => 'nullable|integer|min:0',
            'items.*.source' => 'nullable|string|max:20',
            'memo' => 'nullable|string|max:1000',
            'mark_estimate_paid' => 'nullable|boolean',
            'has_balance' => 'nullable|boolean',
            'balance_amount' => 'nullable|integer|min:0',
        ]);

        try {
            // payment_info에 저장
            $payment = [
                'estimate_id' => $validated['estimate_id'] ?? null,
                'amount' => $validated['amount'] ?? 0,
                'paid_at' => $validated['paid_at'] ?? now()->format('Y-m-d'),
                'method' => $validated['method'] ?? null,
                'items' => array_values(array_filter($validated['items'] ?? [], fn ($i) => ! empty($i['name']))),
                'memo' => $validated['memo'] ?? null,
                'has_balance' => ! empty($validated['has_balance']),
                'balance_amount' => ! empty($validated['has_balance']) ? ($validated['balance_amount'] ?? 0) : 0,
                'recorded_at' => now()->toIso8601String(),
                'recorded_by' => Auth::id(),
            ];

            $project->update(['payment_info' => $payment]);

            // project_payments에 charge 트랜잭션 기록 (history 보관)
            $items = $payment['items'] ?? [];
            ProjectPayment::create([
                'project_id' => $project->id,
                'type' => 'charge',
                'estimate_id' => $payment['estimate_id'] ?? null,
                'amount' => (int) ($payment['amount'] ?? 0),
                'items' => $items,
                'method' => $payment['method'] ?? null,
                'paid_at' => $payment['paid_at'] ?? null,
                'memo' => $payment['memo'] ?? null,
                'recorded_by' => Auth::id(),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => '결제 저장 실패: '.$e->getMessage(),
                'exception' => class_basename($e),
                'file' => basename($e->getFile()).':'.$e->getLine(),
            ], 500);
        }

        // 현재 stage가 payment 이전이면 payment로 진행 (이후 단계는 그대로 유지)
        $stageOrder = ['consulting', 'equipment', 'proposal', 'estimate', 'payment', 'visit', 'as', 'done'];
        $curIdx = array_search($project->stage, $stageOrder, true);
        $payIdx = array_search('payment', $stageOrder, true);
        if ($curIdx === false || $curIdx < $payIdx) {
            $project->update(['stage' => 'payment']);
        }

        // 견적서 연결 + 결제 완료 표시
        if (! empty($validated['estimate_id'])) {
            $estimate = Estimate::find($validated['estimate_id']);
            if ($estimate) {
                $estimateUpdate = ['project_id' => $project->id];
                if (! empty($validated['mark_estimate_paid'])) {
                    $estimateUpdate['status'] = 'paid';
                }
                $estimate->update($estimateUpdate);
            }
        }

        return response()->json([
            'ok' => true,
            'project' => $project->fresh(),
            'payment' => $payment,
        ]);
    }

    /**
     * 단계별 데이터 저장 — equipment/proposal/estimate/visit 등
     *
     * body: { key: 'equipment', data: {...}, advance_to: 'equipment' (선택) }
     *   advance_to가 주어지고 현재 stage보다 뒤면 stage를 거기로 진행.
     */
    public function saveStageData(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'key' => 'required|string|max:30|alpha_dash',
            'data' => 'nullable|array',
            'advance_to' => 'nullable|string',
        ]);

        $stageData = $project->stage_data ?? [];

        // 일정제안: 선택된 캘린더 일정에도 프로젝트 연결을 양방향 반영 (gold_data.project_id)
        if ($validated['key'] === 'proposal') {
            $newIds = array_map('intval', $validated['data']['schedule_ids'] ?? []);
            $prevIds = array_map('intval', data_get($stageData, 'proposal.schedule_ids', []));

            foreach (Schedule::whereIn('id', $newIds)->get() as $schedule) {
                $gold = $schedule->gold_data ?? [];
                $gold['project_id'] = $project->id;
                $gold['client_id'] = $gold['client_id'] ?? $project->client_id;
                $schedule->update(['gold_data' => $gold]);
            }
            // 선택 해제된 일정: 이 프로젝트로 연결돼 있던 경우만 연결 제거
            foreach (Schedule::whereIn('id', array_diff($prevIds, $newIds))->get() as $schedule) {
                $gold = $schedule->gold_data ?? [];
                if ((int) ($gold['project_id'] ?? 0) === $project->id) {
                    $gold['project_id'] = null;
                    $schedule->update(['gold_data' => $gold]);
                }
            }
        }

        $stageData[$validated['key']] = array_merge(
            $validated['data'] ?? [],
            ['updated_at' => now()->toIso8601String(), 'updated_by' => Auth::id()]
        );

        $project->update(['stage_data' => $stageData]);

        // stage 자동 진행
        $stageOrder = ['consulting', 'equipment', 'proposal', 'estimate', 'payment', 'visit', 'as', 'done'];
        $advance = $validated['advance_to'] ?? null;
        if ($advance && in_array($advance, $stageOrder, true)) {
            $curIdx = array_search($project->stage, $stageOrder, true);
            $tgtIdx = array_search($advance, $stageOrder, true);
            if ($curIdx === false || $curIdx < $tgtIdx) {
                $project->update(['stage' => $advance]);
            }
        }

        return response()->json([
            'ok' => true,
            'stage_data' => $project->stage_data,
            'stage' => $project->stage,
        ]);
    }

    /**
     * 일정제안 모달용 — 같은 의뢰자(client_name 매칭)의 캘린더 일정 목록.
     */
    public function projectSchedules(Project $project): JsonResponse
    {
        $client = $project->client;
        $names = array_filter([$client?->name, $client?->nickname]);

        $q = Schedule::query();
        if (! empty($names)) {
            $q->where(function ($qq) use ($names) {
                foreach ($names as $n) {
                    $qq->orWhere('client_name', 'like', '%'.$n.'%');
                }
            });
        }
        $schedules = $q->orderByDesc('start_date')
            ->limit(100)
            ->get(['id', 'title', 'client_name', 'start_date', 'end_date', 'start_time', 'end_time', 'is_all_day', 'color', 'location'])
            ->map(fn ($s) => [
                'id' => $s->id,
                'title' => $s->title,
                'client_name' => $s->client_name,
                'start_date' => $s->start_date instanceof Carbon ? $s->start_date->format('Y-m-d') : $s->start_date,
                'end_date' => $s->end_date instanceof Carbon ? $s->end_date->format('Y-m-d') : $s->end_date,
                'start_time' => $s->start_time,
                'end_time' => $s->end_time,
                'is_all_day' => $s->is_all_day,
                'color' => $s->color,
                'location' => $s->location,
            ]);

        return response()->json($schedules);
    }

    // 프로젝트 완전 삭제 (soft delete)
    public function destroy(Request $request, Project $project)
    {
        $project->delete();

        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->json(['success' => true, 'message' => '프로젝트가 삭제되었습니다.']);
        }

        return back()->with('success', '프로젝트가 삭제되었습니다.');
    }

    /**
     * 결제 트랜잭션 목록 (charge + refund + cancel)
     */
    public function payments(Project $project): JsonResponse
    {
        $rows = ProjectPayment::with('recorder', 'refunds')
            ->where('project_id', $project->id)
            ->orderByDesc('created_at')
            ->get();

        // charge 항목별 누적 환불액 계산
        $byCharge = [];
        foreach ($rows as $r) {
            if (in_array($r->type, ['refund', 'cancel'], true) && $r->parent_payment_id) {
                $byCharge[$r->parent_payment_id] = ($byCharge[$r->parent_payment_id] ?? 0) + abs($r->amount);
            }
        }

        return response()->json([
            'payments' => $rows->map(fn ($r) => [
                'id' => $r->id,
                'parent_payment_id' => $r->parent_payment_id,
                'type' => $r->type,
                'estimate_id' => $r->estimate_id,
                'amount' => $r->amount,
                'items' => $r->items ?? [],
                'method' => $r->method,
                'paid_at' => $r->paid_at?->format('Y-m-d'),
                'memo' => $r->memo,
                'recorder' => $r->recorder?->display_name,
                'created_at' => $r->created_at->format('Y-m-d H:i'),
                'refunded_amount' => $r->type === 'charge' ? ($byCharge[$r->id] ?? 0) : 0,
                'is_fully_refunded' => $r->type === 'charge' && ($byCharge[$r->id] ?? 0) >= $r->amount,
            ]),
        ]);
    }

    /**
     * 환불 또는 결제 취소
     *
     * body:
     *   parent_payment_id (int) — 환불 대상 charge
     *   type   (refund|cancel) — refund=부분 환불 가능, cancel=전액 취소
     *   items  (array nullable) — 환불할 항목 [{name, qty, price}, ...]
     *   amount (int nullable) — items 미지정 시 직접 금액 지정
     *   reason (string nullable)
     *   method (string nullable)
     */
    /**
     * 결제 내역 수정 — charge 항목만 허용 (refund/cancel은 부모의 환불 흐름으로만 발생)
     */
    public function updatePayment(Request $request, Project $project, ProjectPayment $payment): JsonResponse
    {
        if ($payment->project_id !== $project->id) {
            return response()->json(['error' => '해당 프로젝트의 결제가 아닙니다.'], 404);
        }
        if ($payment->type !== 'charge') {
            return response()->json(['error' => '환불/취소 항목은 수정할 수 없습니다. 삭제 후 재처리하세요.'], 422);
        }

        $validated = $request->validate([
            'amount' => 'nullable|integer|min:0',
            'paid_at' => 'nullable|date',
            'method' => 'nullable|string|max:50',
            'items' => 'nullable|array',
            'items.*.name' => 'nullable|string|max:200',
            'items.*.qty' => 'nullable|integer|min:0',
            'items.*.price' => 'nullable|integer|min:0',
            'items.*.source' => 'nullable|string|max:20',
            'memo' => 'nullable|string|max:1000',
            'estimate_id' => 'nullable|integer|exists:estimates,id',
        ]);

        try {
            // items에서 금액 자동 합산 (제공된 경우)
            if (isset($validated['items'])) {
                $items = array_values(array_filter($validated['items'], fn ($i) => ! empty($i['name'])));
                $sum = 0;
                foreach ($items as $it) {
                    $sum += ((int) ($it['qty'] ?? 1)) * ((int) ($it['price'] ?? 0));
                }
                // amount가 명시되지 않았으면 items 합으로 채움
                if (! isset($validated['amount'])) {
                    $validated['amount'] = $sum;
                }
                $validated['items'] = $items;
            }

            // 환불된 금액 이하로 amount를 내릴 수 없음
            if (isset($validated['amount'])) {
                $alreadyRefunded = ProjectPayment::where('parent_payment_id', $payment->id)
                    ->whereIn('type', ['refund', 'cancel'])
                    ->sum('amount'); // 음수
                $refundedAbs = abs((int) $alreadyRefunded);
                if ($refundedAbs > 0 && (int) $validated['amount'] < $refundedAbs) {
                    return response()->json([
                        'message' => "이미 환불된 금액({$refundedAbs}원) 이상으로만 수정할 수 있습니다.",
                    ], 422);
                }
            }

            $payment->update($validated);

            // payment_info(project)도 동기화 — 결제 모달이 다시 열릴 때 prefill용
            $project->update(['payment_info' => array_merge((array) $project->payment_info, [
                'amount' => $payment->amount,
                'items' => $payment->items ?? [],
                'method' => $payment->method,
                'paid_at' => $payment->paid_at?->format('Y-m-d'),
                'memo' => $payment->memo,
                'estimate_id' => $payment->estimate_id,
            ])]);

            return response()->json(['ok' => true, 'payment' => $payment->fresh()]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => '결제 수정 실패: '.$e->getMessage(),
                'exception' => class_basename($e),
                'file' => basename($e->getFile()).':'.$e->getLine(),
            ], 500);
        }
    }

    /**
     * 결제 내역 삭제 — charge 삭제 시 연결된 환불/취소도 함께 삭제
     */
    public function destroyPayment(Project $project, ProjectPayment $payment): JsonResponse
    {
        if ($payment->project_id !== $project->id) {
            return response()->json(['error' => '해당 프로젝트의 결제가 아닙니다.'], 404);
        }

        DB::transaction(function () use ($payment) {
            if ($payment->type === 'charge') {
                // 자식 환불/취소 트랜잭션 함께 삭제
                ProjectPayment::where('parent_payment_id', $payment->id)->delete();
            }
            $payment->delete();
        });

        return response()->json(['ok' => true]);
    }

    public function refundPayment(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'parent_payment_id' => 'required|integer|exists:project_payments,id',
            'type' => 'required|in:refund,cancel',
            'items' => 'nullable|array',
            'items.*.name' => 'nullable|string|max:200',
            'items.*.qty' => 'nullable|integer|min:0',
            'items.*.price' => 'nullable|integer|min:0',
            'amount' => 'nullable|integer|min:0',
            'reason' => 'nullable|string|max:500',
            'method' => 'nullable|string|max:30',
        ]);

        $parent = ProjectPayment::where('project_id', $project->id)
            ->findOrFail($validated['parent_payment_id']);

        if ($parent->type !== 'charge') {
            return response()->json(['error' => '결제(charge) 항목에 대해서만 환불할 수 있습니다.'], 422);
        }

        // 금액 계산: cancel = 잔여 전액, refund = items 합산 또는 amount 직접
        $alreadyRefunded = ProjectPayment::where('parent_payment_id', $parent->id)
            ->whereIn('type', ['refund', 'cancel'])
            ->sum('amount');
        $refundable = $parent->amount + $alreadyRefunded; // alreadyRefunded는 음수
        if ($parent->amount <= 0) {
            return response()->json(['error' => '0원 결제는 환불할 금액이 없습니다. 삭제 또는 수정 기능을 사용해 주세요.'], 422);
        }
        if ($refundable <= 0) {
            return response()->json(['error' => '이미 전액 환불된 결제입니다.'], 422);
        }

        $items = array_values(array_filter($validated['items'] ?? [], fn ($i) => ! empty($i['name'])));
        if ($validated['type'] === 'cancel') {
            $amount = $refundable;
        } else {
            // refund: items 합산 우선, 없으면 amount 직접
            if (! empty($items)) {
                $amount = 0;
                foreach ($items as $it) {
                    $amount += ((int) ($it['qty'] ?? 1)) * ((int) ($it['price'] ?? 0));
                }
            } else {
                $amount = (int) ($validated['amount'] ?? 0);
            }
        }
        if ($amount <= 0) {
            return response()->json(['error' => '환불 금액이 0 이상이어야 합니다.'], 422);
        }
        if ($amount > $refundable) {
            return response()->json(['error' => "환불 가능 금액({$refundable}원)을 초과합니다."], 422);
        }

        $row = ProjectPayment::create([
            'project_id' => $project->id,
            'parent_payment_id' => $parent->id,
            'type' => $validated['type'],
            'estimate_id' => $parent->estimate_id,
            'amount' => -$amount, // 음수로 저장
            'items' => $items ?: null,
            'method' => $validated['method'] ?? $parent->method,
            'paid_at' => now()->toDateString(),
            'memo' => $validated['reason'] ?? null,
            'recorded_by' => Auth::id(),
        ]);

        return response()->json([
            'ok' => true,
            'payment' => $row->fresh('recorder'),
        ], 201);
    }
}
