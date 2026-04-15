<?php

namespace App\Http\Controllers;

use App\Models\Consultation;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConsultationController extends Controller
{
    public function store(Request $request, Project $project)
    {
        $validated = $request->validate([
            'consulted_at' => 'required|date',
            'consult_type' => 'required|in:kakao,phone,visit,field',
            'result' => 'required|in:in_progress,waiting,valid,invalid,done',
            'content' => 'nullable|string',
            'is_important' => 'boolean',
            'manager_name' => 'nullable|string|max:100',
        ]);

        $validated['project_id'] = $project->id;
        $validated['client_id'] = $project->client_id;
        $validated['consultant_id'] = Auth::id();
        $validated['author_user_id'] = Auth::id();
        $validated['is_important'] = $request->boolean('is_important');

        Consultation::create($validated);

        $project->client->update(['last_contact_at' => now()]);

        return back()->with('success', '상담 이력이 등록되었습니다.');
    }

    public function update(Request $request, Consultation $consultation)
    {
        $validated = $request->validate([
            'consulted_at' => 'required|date',
            'consult_type' => 'required|in:kakao,phone,visit,field',
            'result' => 'required|in:in_progress,waiting,valid,invalid,done',
            'content' => 'nullable|string',
            'is_important' => 'boolean',
            'manager_name' => 'nullable|string|max:100',
        ]);

        $validated['is_important'] = $request->boolean('is_important');

        $consultation->update($validated);

        return back()->with('success', '상담 이력이 수정되었습니다.');
    }

    public function destroy(Consultation $consultation)
    {
        $consultation->delete();

        return back()->with('success', '삭제되었습니다.');
    }
}
