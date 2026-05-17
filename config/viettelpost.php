<?php

return [
    'api_url'  => env('VTP_API_URL', 'https://partner.viettelpost.vn'),
    'username' => env('VTP_USERNAME', ''),
    'password' => env('VTP_PASSWORD', ''),

    // Sender pickup origin nay được quản lý qua bảng `stations` (admin "Trạm lấy hàng").
    // Xem App\Services\StationPickerService — chọn trạm theo tỉnh người nhận → Haversine.

    'default_product_type' => env('VTP_DEFAULT_PRODUCT_TYPE', 'HH'),
    'product_type'         => env('VTP_DEFAULT_PRODUCT_TYPE', 'HH'),

    // Người gửi mặc định (dùng khi createOrder nếu station không có thông tin)
    'sender_phone' => env('VTP_SENDER_PHONE', ''),
    'sender_email' => env('VTP_SENDER_EMAIL', ''),

    // Webhook config (Phase 3)
    'webhook_token'        => env('VTP_WEBHOOK_TOKEN', ''),
    'webhook_ip_whitelist' => array_values(array_filter(array_map('trim', explode(',', (string) env('VTP_WEBHOOK_IP_WHITELIST', ''))))),

    // Token dài hạn (1 năm): refresh sớm 10 ngày trước khi hết hạn
    'token_ttl_days'  => 355,
    // Cảnh báo và tự refresh khi token còn ít hơn số ngày này
    'token_warn_days' => 30,

    // Bảng phí phẳng fallback khi VTP API down (đơn vị: đồng)
    'fallback_fees' => [
        'intra_province' => 25000,  // Nội tỉnh Lâm Đồng
        'short_haul'     => 35000,  // Liên tỉnh ≤ 300km
        'long_haul'      => 50000,  // Liên tỉnh > 300km
    ],
];
