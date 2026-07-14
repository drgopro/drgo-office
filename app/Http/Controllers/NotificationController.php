<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /** 상단 알림 리스트 — 최근 30건 + 안읽음 수 */
    public function index(): JsonResponse
    {
        $user = Auth::user();

        $items = $user->notifications()->limit(30)->get()->map(fn ($n) => [
            'id' => $n->id,
            'title' => $n->data['title'] ?? '',
            'body' => $n->data['body'] ?? '',
            'url' => $n->data['url'] ?? null,
            'read' => $n->read_at !== null,
            'time' => $n->created_at->diffForHumans(),
        ]);

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'items' => $items,
        ]);
    }

    /** 읽음 처리 — id 지정 시 해당 알림만, 없으면 전체 */
    public function markRead(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|string|max:64',
        ]);

        $query = Auth::user()->unreadNotifications();
        if (! empty($validated['id'])) {
            $query->where('id', $validated['id']);
        }
        $query->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
