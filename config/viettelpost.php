<?php

return [
    'api_url'  => env('VTP_API_URL', 'https://partner.viettelpost.vn'),
    'username' => env('VTP_USERNAME', ''),
    'password' => env('VTP_PASSWORD', ''),

    // Sender pickup origin nay được quản lý qua bảng `stations` (admin "Trạm lấy hàng").
    // Xem App\Services\StationPickerService — chọn trạm theo tỉnh người nhận → Haversine.

    'default_product_type' => env('VTP_DEFAULT_PRODUCT_TYPE', 'HH'),
    'product_type'         => env('VTP_DEFAULT_PRODUCT_TYPE', 'HH'),

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
