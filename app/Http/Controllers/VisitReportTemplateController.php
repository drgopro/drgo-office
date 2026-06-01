<?php

namespace App\Http\Controllers;

use App\Models\VisitReportTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VisitReportTemplateController extends Controller
{
    public function index()
    {
        return response()->json(
            VisitReportTemplate::with('creator')
                ->orderByDesc('is_default')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn ($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'content' => $t->content,
                    'sort_order' => $t->sort_order,
                    'is_default' => $t->is_default,
                    'is_active' => $t->is_active,
                    'creator' => $t->creator?->display_name,
                    'updated_at' => $t->updated_at?->format('Y-m-d H:i'),
                ])
        );
    }

    /** 활성 템플릿 목록 (보고서 에디터의 '템플릿 적용' 드롭다운용) */
    public function active()
    {
        return response()->json(
            VisitReportTemplate::where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'name', 'content', 'is_default'])
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'content' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'is_default' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        $template = DB::transaction(function () use ($validated) {
            if (! empty($validated['is_default'])) {
                VisitReportTemplate::where('is_default', true)->update(['is_default' => false]);
            }
            $validated['sort_order'] = $validated['sort_order'] ?? (VisitReportTemplate::max('sort_order') ?? 0) + 1;
            $validated['is_active'] = $validated['is_active'] ?? true;
            $validated['created_by'] = Auth::id();

            return VisitReportTemplate::create($validated);
        });

        return response()->json($template, 201);
    }

    public function update(Request $request, VisitReportTemplate $template)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'content' => 'nullable|string',
            'sort_order' => 'sometimes|integer',
            'is_default' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        DB::transaction(function () use ($template, $validated) {
            if (! empty($validated['is_default'])) {
                VisitReportTemplate::where('is_default', true)
                    ->where('id', '!=', $template->id)
                    ->update(['is_default' => false]);
            }
            $template->update($validated);
        });

        return response()->json($template);
    }

    public function destroy(VisitReportTemplate $template)
    {
        $template->delete();

        return response()->json(['ok' => true]);
    }
}
