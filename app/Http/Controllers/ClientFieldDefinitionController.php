<?php

namespace App\Http\Controllers;

use App\Models\ClientFieldDefinition;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClientFieldDefinitionController extends Controller
{
    public function index()
    {
        $fields = ClientFieldDefinition::ordered()->get();

        return response()->json($fields);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'key' => 'nullable|string|max:50|alpha_dash|unique:client_field_definitions,key',
            'label' => 'required|string|max:100',
            'type' => 'required|in:'.implode(',', ClientFieldDefinition::TYPES),
            'section' => 'nullable|string|max:30',
            'width' => 'nullable|integer|between:1,4',
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
            while (ClientFieldDefinition::where('key', $key)->exists()) {
                $key = $base.'_'.$i++;
            }
            $validated['key'] = $key;
        }

        $field = ClientFieldDefinition::create($validated);

        return response()->json($field, 201);
    }

    public function update(Request $request, ClientFieldDefinition $field)
    {
        $validated = $request->validate([
            'label' => 'sometimes|string|max:100',
            'type' => 'sometimes|in:'.implode(',', ClientFieldDefinition::TYPES),
            'section' => 'nullable|string|max:30',
            'width' => 'nullable|integer|between:1,4',
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

    public function destroy(ClientFieldDefinition $field)
    {
        $field->delete();

        return response()->json(['ok' => true]);
    }

    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:client_field_definitions,id',
        ]);

        foreach ($validated['order'] as $i => $id) {
            ClientFieldDefinition::where('id', $id)->update(['sort_order' => $i]);
        }

        return response()->json(['ok' => true]);
    }
}
