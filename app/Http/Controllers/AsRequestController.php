<?php

namespace App\Http\Controllers;

use App\Models\AsRequest;
use App\Models\Client;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * AS 현황판 — 사무실로 들어온 AS 건 관리.
 * 물건을 사무실에서 받은 시점부터 기재하며(상담 단계는 기재 대상 아님),
 * 의뢰자·프로젝트 연동이 필수다. 단계: 사무실 접수 → AS 접수/진행 → AS 완료 → 완료(의뢰자 배송).
 */
class AsRequestController extends Controller
{
    public function index(): View
    {
        return view('as.index', [
            'canEdit' => (bool) Auth::user()?->hasPermission('projects.edit'),
        ]);
    }

    /** 목록 + 상태별 카운트 */
    public function list(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(AsRequest::STATUS_LABELS))],
            'q' => 'nullable|string|max:100',
        ]);

        $query = AsRequest::with(['client:id,name,nickname', 'project:id,name', 'creator:id,display_name,username']);

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['q'])) {
            $like = '%'.$validated['q'].'%';
            $query->where(function ($q) use ($like) {
                $q->where('product_name', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', $like)->orWhere('nickname', 'like', $like))
                    ->orWhereHas('project', fn ($p) => $p->where('name', 'like', $like));
            });
        }

        // 진행 중인 건이 위, 완료 건은 완료된 순 — 같은 단계 안에서는 접수 최신순
        $items = $query
            ->orderByRaw("case when status = 'done' then 1 else 0 end")
            ->orderByDesc('done_at')
            ->orderByDesc('received_at')
            ->limit(300)
            ->get()
            ->map(fn (AsRequest $r) => $this->payload($r));

        $counts = AsRequest::selectRaw('status, count(*) as cnt')->groupBy('status')->pluck('cnt', 'status');

        return response()->json([
            'items' => $items,
            'counts' => collect(AsRequest::STATUS_LABELS)->map(fn ($label, $key) => (int) ($counts[$key] ?? 0)),
        ]);
    }

    /** 의뢰자의 프로젝트 목록 — 등록 모달 연동용 (detail API는 과하게 무겁다) */
    public function clientProjects(Client $client): JsonResponse
    {
        return response()->json(
            $client->projects()->orderByDesc('created_at')->get(['id', 'name', 'created_at'])
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'created' => $p->created_at?->format('Y-m-d')])
        );
    }

    /** 제품명 자동완성 — 제품관리 카탈로그에서 이름으로 검색 (연동은 이름 기입 방식, 수동 입력도 허용) */
    public function productSearch(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json([]);
        }
        $like = '%'.$q.'%';

        return response()->json(
            Product::where('is_active', true)
                ->where(function ($query) use ($like) {
                    $query->where('name', 'like', $like)
                        ->orWhere('option_name', 'like', $like)
                        ->orWhere('search_tags', 'like', $like);
                })
                ->orderBy('name')
                ->limit(10)
                ->get(['id', 'name', 'option_name'])
                ->map(fn (Product $p) => [
                    'id' => $p->id,
                    'label' => trim($p->name.($p->option_name ? ' · '.$p->option_name : '')),
                ])
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);
        $validated['created_by'] = Auth::id();
        $validated['status'] = 'received';
        $validated['received_at'] = now();

        $item = AsRequest::create($validated);

        return response()->json(['ok' => true, 'item' => $this->payload($item->load('client:id,name,nickname', 'project:id,name'))]);
    }

    public function update(Request $request, AsRequest $asRequest): JsonResponse
    {
        $asRequest->update($this->validatePayload($request));

        return response()->json(['ok' => true, 'item' => $this->payload($asRequest->fresh(['client:id,name,nickname', 'project:id,name']))]);
    }

    /** 단계 변경 — 전환 시각을 함께 기록 (이전 단계로 되돌리면 이후 단계 시각은 초기화) */
    public function updateStatus(Request $request, AsRequest $asRequest): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(AsRequest::STATUS_LABELS))],
        ]);

        $order = array_keys(AsRequest::STATUS_LABELS);
        $newIndex = array_search($validated['status'], $order, true);

        $fill = ['status' => $validated['status']];
        foreach ($order as $i => $key) {
            $column = AsRequest::STATUS_TIMESTAMPS[$key];
            if ($i > $newIndex) {
                $fill[$column] = null; // 되돌린 단계 이후의 시각은 지운다
            } elseif ($asRequest->{$column} === null) {
                $fill[$column] = now(); // 건너뛴 단계 포함, 도달한 단계까지 시각 보장
            }
        }
        $asRequest->update($fill);

        return response()->json(['ok' => true, 'item' => $this->payload($asRequest->fresh(['client:id,name,nickname', 'project:id,name']))]);
    }

    public function destroy(AsRequest $asRequest): JsonResponse
    {
        $asRequest->delete();

        return response()->json(['ok' => true]);
    }

    /** @return array<string, mixed> */
    private function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'client_id' => 'required|integer|exists:clients,id',
            'project_id' => [
                'required', 'integer',
                Rule::exists('projects', 'id')->where('client_id', (int) $request->input('client_id')),
            ],
            'product_name' => 'required|string|max:200',
            'description' => 'nullable|string|max:5000',
            'cost_type' => ['nullable', Rule::in(array_keys(AsRequest::COST_LABELS))],
            'cost_amount' => 'nullable|integer|min:0|max:100000000',
        ], [
            'client_id.required' => '의뢰자를 연동해주세요.',
            'project_id.required' => '프로젝트를 연동해주세요.',
            'project_id.exists' => '선택한 의뢰자의 프로젝트가 아닙니다.',
            'product_name.required' => '제품명을 입력해주세요.',
        ]);

        if (($validated['cost_type'] ?? null) !== 'paid') {
            $validated['cost_amount'] = null; // 무상/미정이면 비용 없음
        }

        return $validated;
    }

    /** @return array<string, mixed> */
    private function payload(AsRequest $r): array
    {
        return [
            'id' => $r->id,
            'client_id' => $r->client_id,
            'client' => $r->client?->nickname ?: $r->client?->name,
            'project_id' => $r->project_id,
            'project' => $r->project?->name,
            'product_name' => $r->product_name,
            'description' => $r->description,
            'status' => $r->status,
            'status_label' => AsRequest::STATUS_LABELS[$r->status] ?? $r->status,
            'cost_type' => $r->cost_type,
            'cost_label' => $r->cost_type ? (AsRequest::COST_LABELS[$r->cost_type] ?? $r->cost_type) : null,
            'cost_amount' => $r->cost_amount,
            'received_at' => $r->received_at?->format('Y-m-d'),
            'sent_at' => $r->sent_at?->format('Y-m-d'),
            'repaired_at' => $r->repaired_at?->format('Y-m-d'),
            'done_at' => $r->done_at?->format('Y-m-d'),
            'creator' => $r->creator?->display_name ?: $r->creator?->username,
        ];
    }
}
