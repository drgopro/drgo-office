<?php

namespace App\Http\Controllers;

use App\Models\ProjectFieldDefinition;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProjectFieldDefinitionController extends Controller
{
    public function index()
    {
        $fields = ProjectFieldDefinition::ordered()->get();

        return response()->json($fields);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'key' => 'nullable|string|max:50|alpha_dash|unique:project_field_definitions,key',
            'label' => 'required|string|max:100',
            'type' => 'required|in:'.implode(',', ProjectFieldDefinition::TYPES),
            'section' => 'nullable|string|max:30',
            'subsection' => 'nullable|string|max:50',
            'width' => 'nullable|integer|between:1,4',
            'has_quantity' => 'boolean',
            'options' => 'nullable|array',
            'options.*' => 'string|max:100',
            'placeholder' => 'nullable|string|max:200',
            'help_text' => 'nullable|string',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ]);

        // key 자동 생성 (label에서)
        if (empty($validated['key'])) {
            $base = Str::slug($validated['label'], '_');
            if (! $base || preg_match('/^_+$/', $base)) {
                $base = 'field_'.time();
            }
            $key = $base;
            $i = 1;
            while (ProjectFieldDefinition::where('key', $key)->exists()) {
                $key = $base.'_'.$i++;
            }
            $validated['key'] = $key;
        }

        $field = ProjectFieldDefinition::create($validated);

        return response()->json($field, 201);
    }

    public function update(Request $request, ProjectFieldDefinition $field)
    {
        $validated = $request->validate([
            'label' => 'sometimes|string|max:100',
            'type' => 'sometimes|in:'.implode(',', ProjectFieldDefinition::TYPES),
            'section' => 'nullable|string|max:30',
            'subsection' => 'nullable|string|max:50',
            'width' => 'nullable|integer|between:1,4',
            'has_quantity' => 'boolean',
            'options' => 'nullable|array',
            'options.*' => 'string|max:100',
            'placeholder' => 'nullable|string|max:200',
            'help_text' => 'nullable|string',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ]);

        $field->update($validated);

        return response()->json($field);
    }

    public function destroy(ProjectFieldDefinition $field)
    {
        $field->delete();

        return response()->json(['ok' => true]);
    }

    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:project_field_definitions,id',
        ]);

        foreach ($validated['order'] as $i => $id) {
            ProjectFieldDefinition::where('id', $id)->update(['sort_order' => $i]);
        }

        return response()->json(['ok' => true]);
    }
}
