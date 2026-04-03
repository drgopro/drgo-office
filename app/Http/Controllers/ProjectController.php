<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Project;
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
            $query->whereHas('client', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('nickname', 'like', "%{$search}%");
            })->orWhere('name', 'like', "%{$search}%");
        }

        // 단계 필터
        if ($stage = $request->query('stage')) {
            $query->where('stage', $stage);
        }

        // 유형 필터
        if ($type = $request->query('project_type')) {
            $query->where('project_type', $type);
        }

        $projects = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('projects.index', compact('projects'));
    }

    // 등록
    public function store(Request $request, Client $client)
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:200',
            'project_type' => 'required|in:visit,remote,as',
            'memo'         => 'nullable|string',
        ]);

        $validated['client_id']        = $client->id;
        $validated['assigned_user_id'] = Auth::id();
        $validated['stage']            = 'consulting';
        $validated['status']           = 'active';

        $project = Project::create($validated);

        return redirect()->route('projects.show', $project)->with('success', '프로젝트가 생성되었습니다.');
    }

    // 상세
    public function show(Project $project)
    {
        $project->load('client', 'assignedUser', 'consultations.consultant');
        return view('projects.show', compact('project'));
    }

    // 단계 변경
    public function updateStage(Request $request, Project $project)
    {
        $request->validate([
            'stage' => 'required|in:consulting,equipment,proposal,estimate,payment,visit,as,done,cancelled',
        ]);

        $project->update(['stage' => $request->stage]);

        if ($request->stage === 'done') {
            $project->update(['completed_at' => now()]);
        }

        return back()->with('success', '단계가 변경되었습니다.');
    }
}
