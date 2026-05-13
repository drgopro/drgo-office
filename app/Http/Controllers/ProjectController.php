<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Estimate;
use App\Models\Project;
use App\Models\ProjectMemo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        $projects = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('projects.index', compact('projects'));
    }

    // 등록
    public function store(Request $request, Client $client)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'project_type' => 'required|in:visit,remote,design,inquiry,as,troubleshoot',
            'client_scale' => 'nullable|in:personal,studio,corporate,rental,broadcast_room',
            'work_type' => 'nullable|in:setup,remote,survey,filming,design,as,dispatch,monthly,hourly',
            'memo' => 'nullable|string',
            'custom_data' => 'nullable|array',
        ]);

        $validated['client_id'] = $client->id;
        $validated['assigned_user_id'] = Auth::id();
        $validated['stage'] = 'consulting';
        $validated['status'] = 'active';

        $project = Project::create($validated);

        return redirect()->route('projects.show', $project)->with('success', '프로젝트가 생성되었습니다.');
    }

    // 상세
    public function show(Project $project)
    {
        $project->load('client', 'assignedUser', 'consultations.consultant', 'documents', 'memos.user');

        return view('projects.show', compact('project'));
    }

    // 메모 추가
    public function storeMemo(Request $request, Project $project)
    {
        $validated = $request->validate([
            'content' => 'required|string|max:2000',
        ]);

        $memo = $project->memos()->create([
            'user_id' => Auth::id(),
            'content' => $validated['content'],
        ]);

        $memo->load('user');

        return response()->json([
            'id' => $memo->id,
            'content' => $memo->content,
            'user_name' => $memo->user?->display_name,
            'created_at' => $memo->created_at->format('Y.m.d H:i'),
        ], 201);
    }

    // 메모 삭제
    public function destroyMemo(ProjectMemo $memo)
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

    // 프로젝트 부분 수정 (이름, 메모 등)
    public function updateJson(Request $request, Project $project)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:200',
            'memo' => 'nullable|string',
            'project_type' => 'sometimes|in:visit,remote,design,inquiry,as,troubleshoot',
            'client_scale' => 'sometimes|nullable|in:personal,studio,corporate,rental,broadcast_room',
            'work_type' => 'sometimes|nullable|in:setup,remote,survey,filming,design,as,dispatch,monthly,hourly',
            'custom_data' => 'nullable|array',
        ]);

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
            'memo' => 'nullable|string|max:1000',
            'mark_estimate_paid' => 'nullable|boolean',
        ]);

        // payment_info에 저장 (estimate_id, amount, paid_at, method, items, memo, recorded_at)
        $payment = [
            'estimate_id' => $validated['estimate_id'] ?? null,
            'amount' => $validated['amount'] ?? 0,
            'paid_at' => $validated['paid_at'] ?? now()->format('Y-m-d'),
            'method' => $validated['method'] ?? null,
            'items' => array_values(array_filter($validated['items'] ?? [], fn ($i) => ! empty($i['name']))),
            'memo' => $validated['memo'] ?? null,
            'recorded_at' => now()->toIso8601String(),
            'recorded_by' => Auth::id(),
        ];

        $project->update(['payment_info' => $payment]);

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

    // 프로젝트 완전 삭제 (soft delete)
    public function destroy(Request $request, Project $project)
    {
        $project->delete();

        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->json(['success' => true, 'message' => '프로젝트가 삭제되었습니다.']);
        }

        return back()->with('success', '프로젝트가 삭제되었습니다.');
    }
}
