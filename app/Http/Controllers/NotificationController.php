<?php

namespace App\Http\Controllers;

use App\Services\AppVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * 상단 알림 리스트 — 최근 30건 + 안읽음 수.
     * 셸이 1분마다 폴링하는 유일한 요청이므로 배포 버전도 함께 실어 보낸다 (업데이트 안내 띠용 별도 요청 제거).
     */
    public function index(AppVersion $version): JsonResponse
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
            'version' => $version->current(),
        ])->header('Cache-Control', 'no-store, private');
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
