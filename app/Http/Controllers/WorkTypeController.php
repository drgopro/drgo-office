<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\WorkType;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WorkTypeController extends Controller
{
    private const VALID_SCALES = ['personal', 'studio', 'corporate', 'rental', 'broadcast_room'];

    public function index()
    {
        return response()->json(
            WorkType::orderBy('sort_order')->orderBy('id')->get()
        );
    }

    /** 활성 목록 (드롭다운용) */
    public function active()
    {
        return response()->json(
            WorkType::where('is_active', true)
                ->orderBy('sort_order')->orderBy('id')
                ->get(['id', 'key', 'label', 'scale_keys'])
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'key' => 'nullable|string|max:50|regex:/^[a-z0-9_]+$/|unique:work_types,key',
            'label' => 'required|string|max:100',
            'scale_keys' => 'nullable|array',
            'scale_keys.*' => ['string', 'in:'.implode(',', self::VALID_SCALES)],
            'sort_order' => 'nullable|integer',
            'is_active' => 'sometimes|boolean',
        ]);

        if (empty($validated['key'])) {
            $base = Str::slug($validated['label'], '_');
            $base = preg_replace('/[^a-z0-9_]/', '', $base);
            if (! $base) {
                $base = 'wt_'.time();
            }
            $key = $base;
            $i = 1;
            while (WorkType::where('key', $key)->exists()) {
                $key = $base.'_'.$i++;
            }
            $validated['key'] = $key;
        }

        $validated['sort_order'] = $validated['sort_order'] ?? ((WorkType::max('sort_order') ?? 0) + 1);
        $validated['is_active'] = $validated['is_active'] ?? true;

        $wt = WorkType::create($validated);

        return response()->json($wt, 201);
    }

    public function update(Request $request, WorkType $type)
    {
        $validated = $request->validate([
            'label' => 'sometimes|string|max:100',
            'scale_keys' => 'nullable|array',
            'scale_keys.*' => ['string', 'in:'.implode(',', self::VALID_SCALES)],
            'sort_order' => 'sometimes|integer',
            'is_active' => 'sometimes|boolean',
        ]);

        $type->update($validated);

        return response()->json($type);
    }

    public function destroy(WorkType $type)
    {
        if (array_key_exists($type->key, WorkType::DEFAULTS)) {
            return response()->json([
                'message' => '기본 작업 유형은 삭제할 수 없습니다. 비활성화로 처리해 주세요.',
            ], 422);
        }

        $inUse = Project::where('work_type', $type->key)->exists();
        if ($inUse) {
            return response()->json([
                'message' => '이 유형을 사용 중인 프로젝트가 있어 삭제할 수 없습니다.',
            ], 422);
        }

        $type->delete();

        return response()->json(['ok' => true]);
    }
}
