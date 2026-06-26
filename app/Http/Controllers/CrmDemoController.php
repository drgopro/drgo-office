<?php

namespace App\Http\Controllers;

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

    /** 프로젝트 목록 */
    public function projects(Request $request): JsonResponse
    {
        $q = DemoProject::query()->orderByDesc('id');
        if ($type = $request->query('project_type')) {
            $q->where('project_type', $type);
        }
        if ($request->boolean('billing_only')) {
            // 잔금 남은 건만
            $q->whereNotNull('billing');
        }

        $rows = $q->limit(200)->get()->map(fn ($p) => $this->serialize($p));

        // 잔금 필터는 직렬화 후 적용(계산 기반)
        if ($request->boolean('billing_only')) {
            $rows = $rows->filter(fn ($r) => $r['billing_outstanding'] > 0)->values();
        }

        return response()->json($rows);
    }

    public function store(Request $request): JsonResponse
    {
        $crm = config('crm');
        $validated = $request->validate([
            'client_name' => 'nullable|string|max:100',
            'client_id' => 'nullable|integer',
            'requester_type' => ['nullable', 'string', 'in:'.implode(',', array_keys($crm['requester_types']))],
            'project_type' => ['required', 'string', 'in:'.implode(',', array_keys($crm['project_types']))],
            'work_type' => 'nullable|string|max:60',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:60',
            'free_name' => 'nullable|string|max:200',
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

    /** 청구/입금 갱신 — billing: [{label, amount, paid}] */
    public function saveBilling(Request $request, DemoProject $project): JsonResponse
    {
        $validated = $request->validate([
            'billing' => 'nullable|array',
            'billing.*.label' => 'nullable|string|max:100',
            'billing.*.amount' => 'nullable|integer|min:0',
            'billing.*.paid' => 'nullable|integer|min:0',
            'billing.*.paid_at' => 'nullable|string|max:20',
        ]);
        $project->update(['billing' => array_values($validated['billing'] ?? [])]);

        return response()->json($this->serialize($project->fresh()));
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

        $billing = $p->billing ?? [];
        $charged = array_sum(array_map(fn ($b) => (int) ($b['amount'] ?? 0), $billing));
        $paid = array_sum(array_map(fn ($b) => (int) ($b['paid'] ?? 0), $billing));

        return [
            'id' => $p->id,
            'client_name' => $p->client_name,
            'client_id' => $p->client_id,
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
            'billing' => $billing,
            'billing_charged' => $charged,
            'billing_paid' => $paid,
            'billing_outstanding' => max(0, $charged - $paid),
            'created_at' => $p->created_at?->format('Y-m-d'),
        ];
    }
}
