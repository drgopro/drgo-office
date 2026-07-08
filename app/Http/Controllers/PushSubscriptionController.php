<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PushSubscriptionController extends Controller
{
    /** 브라우저 푸시 구독 등록/갱신 (기기별 다중 구독) */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => 'required|string',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
        ]);

        Auth::user()->updatePushSubscription(
            $validated['endpoint'],
            $validated['keys']['p256dh'],
            $validated['keys']['auth'],
        );

        return response()->json(['ok' => true]);
    }

    /** 구독 해제 */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate(['endpoint' => 'required|string']);

        Auth::user()->deletePushSubscription($validated['endpoint']);

        return response()->json(['ok' => true]);
    }
}
