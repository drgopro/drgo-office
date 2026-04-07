<?php

namespace App\Http\Controllers;

use App\Models\LoginLog;
use App\Models\Setting;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function index()
    {
        $logs = LoginLog::with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        $sellerSettings = Setting::getMany([
            'seller_name', 'seller_biz_no', 'seller_address',
            'seller_biz_type', 'seller_biz_item', 'seller_phone',
        ]);

        return view('admin.index', compact('logs', 'sellerSettings'));
    }

    public function settings()
    {
        return response()->json(Setting::getMany([
            'seller_name', 'seller_biz_no', 'seller_address',
            'seller_biz_type', 'seller_biz_item', 'seller_phone',
        ]));
    }

    public function updateSettings(Request $request)
    {
        $keys = ['seller_name', 'seller_biz_no', 'seller_address', 'seller_biz_type', 'seller_biz_item', 'seller_phone'];

        foreach ($keys as $key) {
            if ($request->has($key)) {
                Setting::set($key, $request->input($key));
            }
        }

        return response()->json(['message' => '저장되었습니다.']);
    }
}
