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
            'inbound_time' => ['nullable', 'regex:/^([01]\d|2[0-3]):(00|30)$/'], // 인입 시간 — 30분 단위 24시간
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

        // 익명(의뢰자 미연동) 프로젝트는 의뢰자 최근 연락일 갱신 생략
        $project->client?->update(['last_contact_at' => now()]);

        // back() 금지 — 첨부 이미지/영상 GET(/project-documents/{id}/view)이 세션의 이전 URL을
        // 오염시키면 등록 직후 미디어 파일로 이동해 사진/영상이 열려버린다. 항상 프로젝트 상세로.
        return redirect()->route('projects.show', $project)->with('success', '상담 이력이 등록되었습니다.');
    }

    public function update(Request $request, Consultation $consultation)
    {
        $validated = $request->validate([
            'consulted_at' => 'required|date',
            'inbound_time' => ['nullable', 'regex:/^([01]\d|2[0-3]):(00|30)$/'], // 인입 시간 — 30분 단위 24시간
            'consult_type' => 'required|in:kakao,phone,visit,field',
            'result' => 'required|in:in_progress,waiting,valid,invalid,done',
            'content' => 'nullable|string',
            'is_important' => 'boolean',
            'manager_name' => 'nullable|string|max:100',
        ]);

        $validated['is_important'] = $request->boolean('is_important');

        $consultation->update($validated);

        return $this->redirectToOwner($consultation)->with('success', '상담 이력이 수정되었습니다.');
    }

    public function destroy(Consultation $consultation)
    {
        $redirect = $this->redirectToOwner($consultation);
        $consultation->delete();

        return $redirect->with('success', '삭제되었습니다.');
    }

    /** 상담이 속한 프로젝트 상세로 명시 리다이렉트 — back()은 미디어 GET에 오염될 수 있음 */
    private function redirectToOwner(Consultation $consultation)
    {
        return $consultation->project_id
            ? redirect()->route('projects.show', $consultation->project_id)
            : redirect()->route('projects.index');
    }
}
