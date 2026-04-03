<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClientController extends Controller
{
    // 목록
    public function index(Request $request)
    {
        $query = Client::with('assignedUser')
            ->where('status', '!=', 'blacklist');

        // 검색
        if ($search = $request->query('search')) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('nickname', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // 등급 필터
        if ($grade = $request->query('grade')) {
            $query->where('grade', $grade);
        }

        $clients = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('clients.index', compact('clients'));
    }

    // 상세
    public function show(Client $client)
    {
        $client->load('assignedUser', 'projects', 'documents');
        return view('clients.show', compact('client'));
    }

    // 등록 폼
    public function create()
    {
        return view('clients.form', ['client' => null]);
    }

    // 저장
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:100',
            'nickname'       => 'nullable|string|max:100',
            'phone'          => 'nullable|string|max:30',
            'address'        => 'nullable|string|max:300',
            'address_detail' => 'nullable|string|max:200',
            'grade'          => 'required|in:normal,vip,rental',
            'platforms'      => 'nullable|array',
            'content_types'  => 'nullable|array',
            'gender'         => 'nullable|in:male,female,other',
            'affiliation'    => 'nullable|string|max:200',
            'important_memo' => 'nullable|string',
            'memo'           => 'nullable|string',
        ]);

        $validated['assigned_user_id'] = Auth::id();
        $validated['status'] = 'active';

        $client = Client::create($validated);

        return redirect()->route('clients.show', $client)->with('success', '의뢰자가 등록되었습니다.');
    }

    // 수정 폼
    public function edit(Client $client)
    {
        return view('clients.form', compact('client'));
    }

    // 업데이트
    public function update(Request $request, Client $client)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:100',
            'nickname'       => 'nullable|string|max:100',
            'phone'          => 'nullable|string|max:30',
            'address'        => 'nullable|string|max:300',
            'address_detail' => 'nullable|string|max:200',
            'grade'          => 'required|in:normal,vip,rental',
            'platforms'      => 'nullable|array',
            'content_types'  => 'nullable|array',
            'gender'         => 'nullable|in:male,female,other',
            'affiliation'    => 'nullable|string|max:200',
            'important_memo' => 'nullable|string',
            'memo'           => 'nullable|string',
        ]);

        $client->update($validated);

        return redirect()->route('clients.show', $client)->with('success', '수정되었습니다.');
    }

    // 삭제
    public function destroy(Client $client)
    {
        $client->delete();
        return redirect()->route('clients.index')->with('success', '삭제되었습니다.');
    }
}
