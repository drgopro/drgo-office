<?php

namespace App\Http\Controllers;

use App\Models\Assignee;

class AssigneeController extends Controller
{
    public function index()
    {
        $assignees = Assignee::where('is_active', true)
            ->orderBy('display_order')
            ->get(['id', 'name']);

        return response()->json($assignees);
    }
}
