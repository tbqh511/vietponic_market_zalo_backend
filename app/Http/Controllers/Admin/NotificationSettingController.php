<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\ZaloOaToken;
use Illuminate\Http\Request;

/**
 * Trang admin bật/tắt push notification Zalo (runtime, không cần đổi .env).
 *
 * Cờ lưu ở Setting 'zalo_notify_enabled' ('1'/'0'). Job SendZaloNotification đọc
 * cờ này trước khi gửi; fallback config('services.zalo.notify_enabled') khi chưa
 * có row.
 */
class NotificationSettingController extends Controller
{
    public function index()
    {
        $row = Setting::where('type', 'zalo_notify_enabled')->value('data');
        $enabled = $row !== null
            ? ((string) $row === '1')
            : (bool) config('services.zalo.notify_enabled', false);

        $token = ZaloOaToken::query()->oldest('id')->first();
        $tokenStatus = [
            'has_token'  => (bool) ($token && $token->access_token),
            'expires_at' => $token?->expires_at?->toDateTimeString(),
            'expired'    => $token && $token->expires_at ? $token->expires_at->isPast() : null,
        ];

        $templates = [
            'paid'      => config('services.zalo.zns_template_paid'),
            'status'    => config('services.zalo.zns_template_status'),
            'cancelled' => config('services.zalo.zns_template_cancelled'),
            'shipping'  => config('services.zalo.zns_template_shipping'),
        ];

        return view('admin.notifications.index', compact('enabled', 'tokenStatus', 'templates'));
    }

    public function toggle(Request $request)
    {
        $data = $request->validate(['enabled' => 'required|boolean']);
        Setting::updateOrCreate(
            ['type' => 'zalo_notify_enabled'],
            ['data' => $data['enabled'] ? '1' : '0']
        );

        return back()->with('success', 'Đã cập nhật trạng thái thông báo Zalo');
    }
}
