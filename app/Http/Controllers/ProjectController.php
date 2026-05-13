<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectMemo;
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
        ]);

        $project->update($validated);

        return response()->json(['success' => true, 'project' => $project]);
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
