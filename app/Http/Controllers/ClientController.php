<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientMemo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClientController extends Controller
{
    // 목록
    public function index(Request $request)
    {
        $query = Client::with('assignedUser')
            ->where('status', '!=', 'blacklist');

        // 검색
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('nickname', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // 등급 필터
        if ($grade = $request->query('grade')) {
            $query->where('grade', $grade);
        }

        $clients = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('clients.index', compact('clients'));
    }

    // 상세
    public function show(Client $client)
    {
        $client->load('assignedUser', 'projects', 'documents', 'estimates.creator');

        return view('clients.show', compact('client'));
    }

    // 등록 폼
    public function create()
    {
        return view('clients.form', ['client' => null]);
    }

    // 저장
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'nickname' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:300',
            'address_detail' => 'nullable|string|max:200',
            'grade' => 'required|in:normal,vip,rental',
            'platforms' => 'nullable|array',
            'platform_etc' => 'nullable|string|max:100',
            'content_types' => 'nullable|array',
            'topic_etc' => 'nullable|string|max:100',
            'broadcast_id' => 'nullable|string|max:100',
            'inflow_source' => 'nullable|string|in:search,referral,sns,ad,community,other',
            'client_type' => 'nullable|string|in:personal,enterprise,studio',
            'custom_data' => 'nullable|array',
            'gender' => 'nullable|in:male,female,other',
            'affiliation' => 'nullable|string|max:200',
            'important_memo' => 'nullable|string',
            'memo' => 'nullable|string',
            'personality' => 'nullable|string|max:500',
            'budget_style' => 'nullable|string|max:500',
        ]);

        $validated['assigned_user_id'] = Auth::id();
        $validated['status'] = 'active';

        $client = Client::create($validated);

        return redirect()->route('clients.show', $client)->with('success', '의뢰자가 등록되었습니다.');
    }

    // 수정 폼
    public function edit(Client $client)
    {
        return view('clients.form', compact('client'));
    }

    // 업데이트
    public function update(Request $request, Client $client)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'nickname' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:300',
            'address_detail' => 'nullable|string|max:200',
            'grade' => 'required|in:normal,vip,rental',
            'platforms' => 'nullable|array',
            'platform_etc' => 'nullable|string|max:100',
            'content_types' => 'nullable|array',
            'topic_etc' => 'nullable|string|max:100',
            'broadcast_id' => 'nullable|string|max:100',
            'inflow_source' => 'nullable|string|in:search,referral,sns,ad,community,other',
            'client_type' => 'nullable|string|in:personal,enterprise,studio',
            'custom_data' => 'nullable|array',
            'gender' => 'nullable|in:male,female,other',
            'affiliation' => 'nullable|string|max:200',
            'important_memo' => 'nullable|string',
            'memo' => 'nullable|string',
            'personality' => 'nullable|string|max:500',
            'budget_style' => 'nullable|string|max:500',
        ]);

        $client->update($validated);

        return redirect()->route('clients.show', $client)->with('success', '수정되었습니다.');
    }

    // JSON 상세 API (탭 내 로드)
    public function detail(Client $client)
    {
        $client->load('assignedUser', 'projects.consultations', 'documents', 'memos.user', 'estimates.creator');

        // 의뢰자의 모든 프로젝트에서 stage_data.equipment 합산 (장비 정보 요약)
        $equipmentSummary = [
            'summaries' => [],          // [{project_id, project_name, summary, updated_at}]
            'items' => [],              // [{project_id, project_name, name, qty, note}]
            'project_count' => 0,
        ];
        foreach ($client->projects as $p) {
            $sd = $p->stage_data ?? [];
            $eq = $sd['equipment'] ?? null;
            if (! $eq) {
                continue;
            }
            $equipmentSummary['project_count']++;
            if (! empty($eq['summary'])) {
                $equipmentSummary['summaries'][] = [
                    'project_id' => $p->id,
                    'project_name' => $p->name,
                    'summary' => $eq['summary'],
                    'updated_at' => $eq['updated_at'] ?? null,
                ];
            }
            foreach (($eq['items'] ?? []) as $it) {
                if (empty($it['name'])) {
                    continue;
                }
                $equipmentSummary['items'][] = [
                    'project_id' => $p->id,
                    'project_name' => $p->name,
                    'name' => $it['name'],
                    'qty' => $it['qty'] ?? '',
                    'note' => $it['note'] ?? '',
                ];
            }
        }

        return response()->json([
            'id' => $client->id,
            'name' => $client->name,
            'nickname' => $client->nickname,
            'phone' => $client->phone,
            'address' => $client->address,
            'address_detail' => $client->address_detail,
            'grade' => $client->grade,
            'platforms' => $client->platforms ?? [],
            'platform_etc' => $client->platform_etc,
            'content_types' => $client->content_types ?? [],
            'topic_etc' => $client->topic_etc,
            'broadcast_id' => $client->broadcast_id,
            'inflow_source' => $client->inflow_source,
            'client_type' => $client->client_type,
            'custom_data' => $client->custom_data ?? new \stdClass,
            'equipment_summary' => $equipmentSummary,
            'gender' => $client->gender,
            'affiliation' => $client->affiliation,
            'important_memo' => $client->important_memo,
            'memo' => $client->memo,
            'personality' => $client->personality,
            'budget_style' => $client->budget_style,
            'status' => $client->status,
            'assigned_user' => $client->assignedUser?->display_name,
            'created_at' => $client->created_at->format('Y.m.d'),
            'projects' => $client->projects->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'type' => $p->type,
                'stage' => $p->stage,
                'created_at' => $p->created_at->format('Y.m.d'),
                'consultations_count' => $p->consultations->count(),
            ]),
            'documents' => $client->documents->map(fn ($d) => [
                'id' => $d->id,
                'file_name' => $d->file_name,
                'mime_type' => $d->mime_type,
                'file_size' => $d->file_size,
                'note' => $d->note,
                'view_url' => route('documents.serve', $d),
                'download_url' => route('documents.download', $d),
                'created_at' => $d->created_at->format('Y.m.d H:i:s'),
            ]),
            'memos' => $client->memos->map(fn ($m) => [
                'id' => $m->id,
                'content' => $m->content,
                'user_name' => $m->user?->display_name ?? '알 수 없음',
                'created_at' => $m->created_at->format('Y.m.d H:i'),
            ]),
            'estimates' => $client->estimates->map(fn ($e) => [
                'id' => $e->id,
                'status' => $e->status,
                'total_amount' => $e->total_amount,
                'created_at' => $e->created_at->format('Y.m.d'),
                'creator_name' => $e->creator?->display_name ?? $e->creator?->name,
                'print_url' => route('estimates.print', $e),
                'edit_url' => route('estimates.edit', $e),
            ]),
        ]);
    }

    // JSON 업데이트 API
    public function updateJson(Request $request, Client $client)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'nickname' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:300',
            'address_detail' => 'nullable|string|max:200',
            'grade' => 'required|in:normal,vip,rental',
            'platforms' => 'nullable|array',
            'platform_etc' => 'nullable|string|max:100',
            'content_types' => 'nullable|array',
            'topic_etc' => 'nullable|string|max:100',
            'broadcast_id' => 'nullable|string|max:100',
            'inflow_source' => 'nullable|string|in:search,referral,sns,ad,community,other',
            'client_type' => 'nullable|string|in:personal,enterprise,studio',
            'custom_data' => 'nullable|array',
            'gender' => 'nullable|in:male,female,other',
            'affiliation' => 'nullable|string|max:200',
            'important_memo' => 'nullable|string',
            'memo' => 'nullable|string',
            'personality' => 'nullable|string|max:500',
            'budget_style' => 'nullable|string|max:500',
        ]);

        $client->update($validated);

        return response()->json(['message' => '저장되었습니다.']);
    }

    // JSON 생성 API
    public function storeJson(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'nickname' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'grade' => 'required|in:normal,vip,rental',
            'inflow_source' => 'nullable|string|in:search,referral,sns,ad,community,other',
            'client_type' => 'nullable|string|in:personal,enterprise,studio',
            'platforms' => 'nullable|array',
            'platform_etc' => 'nullable|string|max:100',
            'content_types' => 'nullable|array',
            'topic_etc' => 'nullable|string|max:100',
            'broadcast_id' => 'nullable|string|max:100',
            'personality' => 'nullable|string|max:500',
            'budget_style' => 'nullable|string|max:500',
        ]);

        $validated['assigned_user_id'] = Auth::id();
        $validated['status'] = 'active';

        $client = Client::create($validated);

        return response()->json(['id' => $client->id, 'message' => '등록되었습니다.'], 201);
    }

    // JSON 목록 API (서버사이드 페이지네이션)
    public function listJson(Request $request)
    {
        $query = Client::where('status', '!=', 'blacklist');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('nickname', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($grade = $request->query('grade')) {
            $query->where('grade', $grade);
        }

        $perPage = (int) ($request->query('per_page', 20));
        $perPage = max(1, min($perPage, 100));

        $paginated = $query->orderBy('created_at', 'desc')
            ->paginate($perPage, ['id', 'name', 'nickname', 'phone', 'grade', 'status', 'platforms']);

        return response()->json([
            'data' => $paginated->items(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
            'total' => $paginated->total(),
        ]);
    }

    // 메모 추가
    public function storeMemo(Request $request, Client $client)
    {
        $validated = $request->validate([
            'content' => 'required|string|max:2000',
        ]);

        $memo = $client->memos()->create([
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
    public function destroyMemo(ClientMemo $memo)
    {
        $memo->delete();

        return response()->json(['message' => '삭제되었습니다.']);
    }

    // 검색 API (견적서 등에서 사용)
    public function search(Request $request)
    {
        $q = $request->query('q', '');
        if (strlen($q) < 1) {
            return response()->json([]);
        }

        $clients = Client::where('status', '!=', 'blacklist')
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('nickname', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            })
            ->limit(10)
            ->get(['id', 'name', 'nickname', 'phone']);

        return response()->json($clients);
    }

    // 삭제
    public function destroy(Client $client)
    {
        $client->delete();

        return redirect()->route('clients.index')->with('success', '삭제되었습니다.');
    }
}
