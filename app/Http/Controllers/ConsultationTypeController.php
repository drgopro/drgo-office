<?php

namespace App\Http\Controllers;

use App\Models\ConsultationType;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ConsultationTypeController extends Controller
{
    public function index()
    {
        return response()->json(
            ConsultationType::orderBy('sort_order')->orderBy('id')->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'key' => 'nullable|string|max:50|regex:/^[a-z0-9_]+$/|unique:consultation_types,key',
            'label' => 'required|string|max:100',
            'sort_order' => 'nullable|integer',
            'is_active' => 'sometimes|boolean',
        ]);

        // key 자동 생성 (label → slug 영문/숫자만)
        if (empty($validated['key'])) {
            $base = Str::slug($validated['label'], '_');
            $base = preg_replace('/[^a-z0-9_]/', '', $base);
            if (! $base) {
                $base = 'type_'.time();
            }
            $key = $base;
            $i = 1;
            while (ConsultationType::where('key', $key)->exists()) {
                $key = $base.'_'.$i++;
            }
            $validated['key'] = $key;
        }

        $validated['sort_order'] = $validated['sort_order'] ?? ((ConsultationType::max('sort_order') ?? 0) + 1);
        $validated['is_active'] = $validated['is_active'] ?? true;

        $type = ConsultationType::create($validated);

        return response()->json($type, 201);
    }

    public function update(Request $request, ConsultationType $type)
    {
        $validated = $request->validate([
            'label' => 'sometimes|string|max:100',
            'sort_order' => 'sometimes|integer',
            'is_active' => 'sometimes|boolean',
        ]);

        $type->update($validated);

        return response()->json($type);
    }

    public function destroy(ConsultationType $type)
    {
        // 기본 키는 보호 (기존 데이터 영향)
        if (array_key_exists($type->key, ConsultationType::DEFAULTS)) {
            return response()->json([
                'message' => '기본 유형은 삭제할 수 없습니다. 비활성화로 사용해 주세요.',
            ], 422);
        }

        // 사용 중인지 확인
        $inUse = Project::where('project_type', $type->key)->exists();
        if ($inUse) {
            return response()->json([
                'message' => '이 유형을 사용 중인 프로젝트가 있어 삭제할 수 없습니다. 비활성화로 처리해 주세요.',
            ], 422);
        }

        $type->delete();

        return response()->json(['ok' => true]);
    }

    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:consultation_types,id',
        ]);

        foreach ($validated['order'] as $i => $id) {
            ConsultationType::where('id', $id)->update(['sort_order' => $i]);
        }

        return response()->json(['ok' => true]);
    }
}
