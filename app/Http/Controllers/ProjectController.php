<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Estimate;
use App\Models\Project;
use App\Models\ProjectBilling;
use App\Models\ProjectFeedback;
use App\Models\ProjectPayment;
use App\Models\ProjectSubtag;
use App\Models\Schedule;
use App\Models\WorkType;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    // 목록
    public function index(Request $request)
    {
        $query = Project::with('client', 'assignedUser')
            ->where('status', '!=', 'cancelled');

        // 검색 — 의뢰자명/닉네임/프로젝트명/미연동 주관식 이름 (그룹으로 묶어 status 필터와 AND 유지)
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('client', function ($qq) use ($search) {
                    $qq->where('name', 'like', "%{$search}%")
                        ->orWhere('nickname', 'like', "%{$search}%");
                })
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('manual_client_name', 'like', "%{$search}%");
            });
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

    // 등록 (의뢰자 연동)
    public function store(Request $request, Client $client)
    {
        return $this->createProject($request, $client);
    }

    /** 의뢰자 미연동 등록 — 의뢰자명 확인 불가 시 주관식 이름으로 생성 */
    public function storeStandalone(Request $request)
    {
        return $this->createProject($request, null);
    }

    private function createProject(Request $request, ?Client $client)
    {
        $rules = [
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
        ];
        if (! $client) {
            $rules['manual_client_name'] = 'nullable|string|max:100';
        }
        $validated = $request->validate($rules);
        $validated['tags'] = $this->normalizeTags($request->input('tags'));
        if (isset($validated['memo']) && ! isset($validated['overview'])) {
            $validated['overview'] = $validated['memo'];
        }
        unset($validated['memo']);

        $validated['client_id'] = $client?->id;
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

    // 단계 변경 — 허용 단계는 유형별 flow 기준 (done/cancelled는 항상 허용)
    public function updateStage(Request $request, Project $project)
    {
        $allowedStages = array_unique(array_merge(
            array_column($project->flowStages(), 'code'),
            ['done', 'cancelled'],
        ));
        $request->validate([
            'stage' => ['required', Rule::in($allowedStages)],
            'cancel_reason' => 'nullable|string|max:100', // 관리자 정의 사유 허용

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

        // back() 금지 — 알림 폴링 등 비-AJAX GET이 세션의 이전 URL을 오염시키면
        // JSON 응답 페이지로 이동해버린다. 항상 프로젝트 상세로 명시 리다이렉트.
        return redirect()->route('projects.show', $project)->with('success', '단계가 변경되었습니다.');
    }

    // 프로젝트 부분 수정 (이름, 프로젝트 개요 등)
    public function updateJson(Request $request, Project $project)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:200',
            'overview' => 'nullable|string',
            'address' => 'sometimes|nullable|string|max:300', // 세팅 장소 (의뢰자 주소와 별개)
            'address_detail' => 'sometimes|nullable|string|max:200',
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

        // 장비 항목 정의(__equip_items)는 관리자 이상만 변경 가능 — 멤버는 값 입력만, 정의는 기존 그대로 유지
        if (isset($validated['custom_data']) && ! $request->user()->isAdmin()) {
            $existingItems = $project->custom_data['__equip_items'] ?? null;
            if ($existingItems === null) {
                unset($validated['custom_data']['__equip_items']);
            } else {
                $validated['custom_data']['__equip_items'] = $existingItems;
            }
        }

        $project->update($validated);

        return response()->json(['success' => true, 'project' => $project]);
    }

    /** 캘린더 연동 — 프로젝트에 작성된 의뢰 내용(세팅 항목 선택, custom_data.__req_items) 조회 */
    public function requestItems(Project $project): JsonResponse
    {
        $items = collect((array) ($project->custom_data['__req_items'] ?? []))
            ->filter(fn ($i) => is_array($i) && ! empty($i['t']) && ! empty($i['c']) && ! empty($i['d']))
            ->values();

        return response()->json(['req_items' => $items]);
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
            ->get(['id', 'estimate_no', 'client_name', 'client_nickname', 'total_amount', 'product_total', 'service_total', 'status', 'issued_at', 'created_at', 'product_items', 'service_items', 'project_id'])
            ->map(fn ($e) => [
                'id' => $e->id,
                'no' => $e->display_no,
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
            'billing_id' => 'nullable|integer|exists:project_billings,id',
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
                'billing_id' => $validated['billing_id'] ?? null,
                'estimate_id' => $payment['estimate_id'] ?? null,
                'amount' => (int) ($payment['amount'] ?? 0),
                'items' => $items,
                'method' => $payment['method'] ?? null,
                'paid_at' => $payment['paid_at'] ?? null,
                'memo' => $payment['memo'] ?? null,
                'recorded_by' => Auth::id(),
            ]);

            // 청구에 연결된 입금이면 잔금 상태 갱신 (부분/전액 입금 자동 반영)
            if (! empty($validated['billing_id'])) {
                ProjectBilling::find($validated['billing_id'])?->refreshStatus();
            }

            // 잔금 여부 O + 잔금 금액 — 남은 금액을 청구로 자동 등록해 잔금 관리 화면과 연동
            if (! empty($payment['has_balance']) && (int) $payment['balance_amount'] > 0) {
                ProjectBilling::create([
                    'project_id' => $project->id,
                    'amount' => (int) $payment['balance_amount'],
                    'billed_at' => $payment['paid_at'] ?? now()->format('Y-m-d'),
                    'status' => 'unpaid',
                    'memo' => '결제 시 잔금'.($payment['memo'] ? ' — '.mb_substr($payment['memo'], 0, 400) : ''),
                    'created_by' => Auth::id(),
                ]);
            }
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => '결제 저장 실패: '.$e->getMessage(),
                'exception' => class_basename($e),
                'file' => basename($e->getFile()).':'.$e->getLine(),
            ], 500);
        }

        // 현재 stage가 payment 이전이면 payment로 진행 (이후 단계는 그대로 유지) — 순서는 유형별 flow 기준.
        // flow에 결제 단계가 없는 유형(문의/AS 등)은 결제 기록만 남기고 단계는 건드리지 않음.
        $stageOrder = array_column($project->flowStages(), 'code');
        $payIdx = array_search('payment', $stageOrder, true);
        if ($payIdx !== false) {
            $curIdx = array_search($project->stage, $stageOrder, true);
            if ($curIdx === false || $curIdx < $payIdx) {
                $project->update(['stage' => 'payment']);
            }
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

        // 일정제안: 선택된 캘린더 일정에도 프로젝트 연결을 양방향 반영 (request_data.project_id)
        if ($validated['key'] === 'proposal') {
            $newIds = array_map('intval', $validated['data']['schedule_ids'] ?? []);
            $prevIds = array_map('intval', data_get($stageData, 'proposal.schedule_ids', []));

            foreach (Schedule::whereIn('id', $newIds)->get() as $schedule) {
                $gold = $schedule->request_data ?? [];
                $gold['project_id'] = $project->id;
                $gold['client_id'] = $gold['client_id'] ?? $project->client_id;
                $schedule->update(['request_data' => $gold]);
            }
            // 선택 해제된 일정: 이 프로젝트로 연결돼 있던 경우만 연결 제거
            foreach (Schedule::whereIn('id', array_diff($prevIds, $newIds))->get() as $schedule) {
                $gold = $schedule->request_data ?? [];
                if ((int) ($gold['project_id'] ?? 0) === $project->id) {
                    $gold['project_id'] = null;
                    $schedule->update(['request_data' => $gold]);
                }
            }
        }

        $stageData[$validated['key']] = array_merge(
            $validated['data'] ?? [],
            ['updated_at' => now()->toIso8601String(), 'updated_by' => Auth::id()]
        );

        $project->update(['stage_data' => $stageData]);

        // stage 자동 진행 — 순서는 유형별 flow 기준
        $stageOrder = array_column($project->flowStages(), 'code');
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
     * 캘린더 일정 요약용 프로젝트 스냅샷 — 결제 합계 + 진행 단계.
     * 결제 트랜잭션이 없으면 구버전 payment_info(JSON) 금액으로 폴백.
     */
    public function summary(Project $project): JsonResponse
    {
        $charges = ProjectPayment::where('project_id', $project->id)->where('type', 'charge');
        $chargedTotal = (int) $charges->clone()->sum('amount');
        $refundedTotal = (int) ProjectPayment::where('project_id', $project->id)
            ->whereIn('type', ['refund', 'cancel'])
            ->get()
            ->sum(fn ($r) => abs($r->amount));
        $paymentsCount = $charges->clone()->count();
        $lastPaidAt = $charges->clone()->max('paid_at');

        // 트랜잭션 도입 이전에 payment_info로만 기록된 결제 폴백
        if ($paymentsCount === 0 && (int) ($project->payment_info['amount'] ?? 0) > 0) {
            $chargedTotal = (int) $project->payment_info['amount'];
            $paymentsCount = 1;
            $lastPaidAt = $project->payment_info['paid_at'] ?? null;
        }

        // 미수 잔금 (미입금·부분입금 청구의 잔액 합)
        $outstanding = ProjectBilling::where('project_id', $project->id)
            ->whereIn('status', ['unpaid', 'partial'])
            ->get()
            ->sum(fn ($b) => $b->balance());

        // 이 프로젝트에 연동된 견적서 — 캘린더 프로젝트 요약 카드에 표시
        $estimateStatus = ['temp' => '작성중', 'created' => '완성', 'editing' => '수정중', 'completed' => '작성 완료', 'issued' => '발행 완료', 'paid' => '결제완료', 'cancelled' => '결제 취소', 'hold' => '보류'];
        $linkedEstimates = Estimate::where('project_id', $project->id)
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'estimate_no', 'total_amount', 'status', 'issued_at', 'created_at'])
            ->map(fn ($e) => [
                'id' => $e->id,
                'no' => $e->display_no,
                'total_amount' => (int) $e->total_amount,
                'status' => $estimateStatus[$e->status] ?? $e->status,
                'date' => ($e->issued_at ?? $e->created_at)?->format('Y-m-d'),
            ]);

        return response()->json([
            'outstanding_balance' => $outstanding,
            'id' => $project->id,
            'name' => $project->name,
            'stage' => $project->stageLabel(),
            'work_type' => WorkType::where('key', $project->work_type)->value('label') ?? $project->work_type,
            'charged_total' => $chargedTotal,
            'refunded_total' => $refundedTotal,
            'paid_total' => $chargedTotal - $refundedTotal,
            'payments_count' => $paymentsCount,
            'last_paid_at' => $lastPaidAt,
            'estimates' => $linkedEstimates,
        ]);
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
                'billing_id' => $r->billing_id,
                'type' => $r->type,
                'estimate_id' => $r->estimate_id,
                'amount' => $r->amount,
                'items' => $r->items ?? [],
                'method' => $r->method,
                'paid_at' => $r->paid_at?->format('Y-m-d'),
                'refund_requested_at' => $r->refund_requested_at?->format('Y-m-d H:i'),
                'refunded_at' => $r->refunded_at?->format('Y-m-d H:i'),
                'memo' => $r->memo,
                'recorder' => $r->recorder?->display_name,
                'created_at' => $r->created_at->format('Y-m-d H:i'),
                'refunded_amount' => $r->type === 'charge' ? ($byCharge[$r->id] ?? 0) : 0,
                'is_fully_refunded' => $r->type === 'charge' && ($byCharge[$r->id] ?? 0) >= $r->amount,
            ]),
            'billings' => ProjectBilling::where('project_id', $project->id)
                ->orderByDesc('billed_at')->orderByDesc('id')->get()
                ->map(fn ($b) => [
                    'id' => $b->id,
                    'amount' => $b->amount,
                    'billed_at' => $b->billed_at?->format('Y-m-d'),
                    'status' => $b->status,
                    'memo' => $b->memo,
                    'paid_total' => $b->paidTotal(),
                    'balance' => $b->balance(),
                ]),
        ]);
    }

    /** 청구 생성 — 결제 단계의 '청구' 체크 (받을 금액 등록, 입금은 이후 추적) */
    public function storeBilling(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|integer|min:1',
            'billed_at' => 'nullable|date',
            'memo' => 'nullable|string|max:500',
        ]);

        $billing = ProjectBilling::create([
            'project_id' => $project->id,
            'amount' => $validated['amount'],
            'billed_at' => $validated['billed_at'] ?? now()->format('Y-m-d'),
            'status' => 'unpaid',
            'memo' => $validated['memo'] ?? null,
            'created_by' => Auth::id(),
        ]);

        // 현재 stage가 payment 이전이면 payment로 진행 (flow에 결제 단계가 있는 유형만)
        $stageOrder = array_column($project->flowStages(), 'code');
        $payIdx = array_search('payment', $stageOrder, true);
        if ($payIdx !== false) {
            $curIdx = array_search($project->stage, $stageOrder, true);
            if ($curIdx === false || $curIdx < $payIdx) {
                $project->update(['stage' => 'payment']);
            }
        }

        return response()->json($billing->only('id', 'amount', 'billed_at', 'status', 'memo'), 201);
    }

    /** 청구 수정 — 금액/청구일/메모, status 직접 지정 시 수동 완료 처리 */
    public function updateBilling(Request $request, ProjectBilling $billing): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'sometimes|integer|min:1',
            'billed_at' => 'sometimes|date',
            'memo' => 'nullable|string|max:500',
            'status' => ['sometimes', Rule::in(ProjectBilling::STATUSES)],
        ]);

        $billing->update($validated);
        if (! array_key_exists('status', $validated) && array_key_exists('amount', $validated)) {
            $billing->refreshStatus(); // 청구액 변경 시 잔금 상태 재계산
        }

        return response()->json([
            'ok' => true,
            'billing' => array_merge(
                $billing->fresh()->only('id', 'amount', 'billed_at', 'status', 'memo'),
                ['paid_total' => $billing->paidTotal(), 'balance' => $billing->balance()],
            ),
        ]);
    }

    public function destroyBilling(ProjectBilling $billing): JsonResponse
    {
        $billing->delete(); // 연결 입금은 billing_id만 해제 (기록 보존)

        return response()->json(['ok' => true]);
    }

    /** 잔금 관리 화면 — 미입금·부분입금 청구가 있는 프로젝트 모아보기 */
    public function billingIndex()
    {
        $billings = ProjectBilling::with(['project.client', 'creator:id,display_name'])
            ->whereIn('status', ['unpaid', 'partial'])
            ->orderBy('billed_at')
            ->get();

        $rows = $billings->map(fn ($b) => [
            'billing' => $b,
            'paid_total' => $b->paidTotal(),
            'balance' => $b->balance(),
            'last_paid_at' => $b->payments()->where('type', 'charge')->max('paid_at'),
        ]);

        return view('projects.billing', [
            'rows' => $rows,
            'totalBalance' => $rows->sum('balance'),
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
            return $this->updateRefundRow($request, $payment);
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
            'has_balance' => 'nullable|boolean',
            'balance_amount' => 'nullable|integer|min:0',
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

            // 잔금 여부/금액은 결제 레코드가 아니라 payment_info + 자동 청구로 관리
            $hasBalance = array_key_exists('has_balance', $validated) ? (bool) $validated['has_balance'] : null;
            $balanceAmount = $hasBalance ? (int) ($validated['balance_amount'] ?? 0) : 0;
            unset($validated['has_balance'], $validated['balance_amount']);

            $payment->update($validated);
            $payment->billing?->refreshStatus(); // 금액 수정 시 청구 잔금 재계산

            // payment_info(project)도 동기화 — 결제 모달이 다시 열릴 때 prefill용
            $paymentInfo = array_merge((array) $project->payment_info, [
                'amount' => $payment->amount,
                'items' => $payment->items ?? [],
                'method' => $payment->method,
                'paid_at' => $payment->paid_at?->format('Y-m-d'),
                'memo' => $payment->memo,
                'estimate_id' => $payment->estimate_id,
            ]);
            if ($hasBalance !== null) {
                $paymentInfo['has_balance'] = $hasBalance;
                $paymentInfo['balance_amount'] = $balanceAmount;
            }
            $project->update(['payment_info' => $paymentInfo]);

            // 잔금 자동 청구 동기화 — 수정 시에는 기존 '결제 시 잔금' 미입금 청구를 재사용해 중복 생성 방지
            if ($hasBalance !== null) {
                $autoBilling = ProjectBilling::where('project_id', $project->id)
                    ->where('status', 'unpaid')
                    ->where('memo', 'like', '결제 시 잔금%')
                    ->latest('id')->first();

                if ($hasBalance && $balanceAmount > 0) {
                    if ($autoBilling) {
                        $autoBilling->update(['amount' => $balanceAmount]);
                    } else {
                        ProjectBilling::create([
                            'project_id' => $project->id,
                            'amount' => $balanceAmount,
                            'billed_at' => $payment->paid_at?->format('Y-m-d') ?? now()->format('Y-m-d'),
                            'status' => 'unpaid',
                            'memo' => '결제 시 잔금'.($payment->memo ? ' — '.mb_substr($payment->memo, 0, 400) : ''),
                            'created_by' => Auth::id(),
                        ]);
                    }
                } elseif (! $hasBalance && $autoBilling) {
                    $autoBilling->delete(); // 잔금 X로 변경 → 미입금 자동 청구 정리
                }
            }

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

        $billingId = $payment->billing_id;
        DB::transaction(function () use ($payment) {
            if ($payment->type === 'charge') {
                // 자식 환불/취소 트랜잭션 함께 삭제
                ProjectPayment::where('parent_payment_id', $payment->id)->delete();
            }
            $payment->delete();
        });
        if ($billingId) {
            ProjectBilling::find($billingId)?->refreshStatus(); // 입금 삭제 시 청구 잔금 재계산
        }

        // 마지막 결제 삭제 시 payment_info(프리필 JSON)도 정리 — 남겨두면 요약의
        // 구버전 폴백이 삭제된 결제를 '결제 1건'으로 되살려 보여준다 (유령 결제 버그)
        if (! ProjectPayment::where('project_id', $project->id)->where('type', 'charge')->exists()) {
            $project->update(['payment_info' => null]);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * 환불/취소 행 수정 — 금액(양수 입력, 부모 결제의 환불 가능 한도 검증)·수단·사유와
     * 환불 요청 일시·환불 완료 일시를 수정한다.
     */
    private function updateRefundRow(Request $request, ProjectPayment $payment): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'nullable|integer|min:1',
            'method' => 'nullable|string|max:50',
            'memo' => 'nullable|string|max:1000',
            'paid_at' => 'nullable|date',
            'refund_requested_at' => 'nullable|date',
            'refunded_at' => 'nullable|date',
        ]);

        if (isset($validated['amount'])) {
            $parent = ProjectPayment::find($payment->parent_payment_id);
            if ($parent) {
                $otherRefunded = abs((int) ProjectPayment::where('parent_payment_id', $parent->id)
                    ->whereIn('type', ['refund', 'cancel'])
                    ->where('id', '!=', $payment->id)
                    ->sum('amount'));
                if ($parent->amount < $validated['amount'] + $otherRefunded) {
                    $max = number_format($parent->amount - $otherRefunded);

                    return response()->json(['message' => "환불 가능 금액({$max}원)을 초과합니다."], 422);
                }
            }
            $validated['amount'] = -abs($validated['amount']); // 환불은 음수로 저장
        }

        $payment->update($validated);
        $payment->billing?->refreshStatus(); // 환불 금액 변경 시 청구 잔금 재계산

        return response()->json(['ok' => true, 'payment' => $payment->fresh('recorder')]);
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
            'items.*.estimate_item_index' => 'nullable|integer|min:0', // 견적서 항목 연동 — 스냅샷에 환불 기록
            'amount' => 'nullable|integer|min:0',
            'reason' => 'nullable|string|max:500',
            'method' => 'nullable|string|max:30',
            'refund_requested_at' => 'nullable|date',
            'refunded_at' => 'nullable|date',
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
            'billing_id' => $parent->billing_id, // 청구 연결 입금의 환불은 잔금에 반영
            'type' => $validated['type'],
            'estimate_id' => $parent->estimate_id,
            'amount' => -$amount, // 음수로 저장
            'items' => $items ?: null,
            'method' => $validated['method'] ?? $parent->method,
            'paid_at' => now()->toDateString(),
            'refund_requested_at' => $validated['refund_requested_at'] ?? null,
            'refunded_at' => $validated['refunded_at'] ?? null,
            'memo' => $validated['reason'] ?? null,
            'recorded_by' => Auth::id(),
        ]);
        $row->billing?->refreshStatus();

        // 견적서 연동 — 선택된 견적서 항목에 환불 기록 (어떤 항목을 얼마 환불했는지 표시용)
        if ($parent->estimate_id) {
            $refunds = collect($items)
                ->filter(fn ($i) => isset($i['estimate_item_index']))
                ->map(fn ($i) => [
                    'index' => (int) $i['estimate_item_index'],
                    'qty' => (int) ($i['qty'] ?? 0),
                    'amount' => ((int) ($i['qty'] ?? 1)) * ((int) ($i['price'] ?? 0)),
                ])->values()->all();
            if ($refunds !== []) {
                Estimate::find($parent->estimate_id)?->applyItemRefunds($refunds);
            }
        }

        return response()->json([
            'ok' => true,
            'payment' => $row->fresh('recorder'),
        ], 201);
    }
}
