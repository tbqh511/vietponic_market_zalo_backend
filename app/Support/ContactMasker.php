<?php

namespace App\Support;

/**
 * ContactMasker — che thông tin nhạy cảm của khách hàng trước khi gửi xuống
 * giao diện đóng gói (Mini App farm). Mục tiêu bảo mật: nhân viên đóng gói
 * KHÔNG bao giờ nhận được SĐT đầy đủ hay địa chỉ số nhà/đường từ server.
 *
 * Làm ở server (không chỉ ẩn ở UI) để dữ liệu đầy đủ không lọt qua network /
 * DevTools / response cache.
 */
class ContactMasker
{
    /**
     * Che SĐT giữ 4 số đầu + 3 số cuối, phần giữa thay bằng '***'.
     * "0937456739" → "0937***739". SĐT quá ngắn (<7) che toàn bộ phần giữa.
     */
    public static function maskPhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        // Chỉ giữ chữ số để mask nhất quán (bỏ khoảng trắng, dấu chấm...).
        $digits = preg_replace('/\D+/', '', $phone);

        if ($digits === '') {
            return null;
        }

        $len = strlen($digits);

        if ($len <= 7) {
            // Quá ngắn để giữ 4+3 mà vẫn còn phần che — giữ 2 đầu + 1 cuối.
            $head = substr($digits, 0, min(2, $len - 1));
            $tail = substr($digits, -1);
            return $head . '***' . $tail;
        }

        return substr($digits, 0, 4) . '***' . substr($digits, -3);
    }

    /**
     * Rút địa chỉ xuống 2 đoạn cuối (thường là phường/quận + tỉnh/thành), bỏ
     * số nhà / tên đường. "12 Lê Lợi, P. Bến Nghé, Q.1, TP.HCM" →
     * "Q.1, TP.HCM". Địa chỉ ≤2 đoạn trả nguyên (đã đủ thô).
     */
    public static function maskAddress(?string $address): ?string
    {
        if ($address === null) {
            return null;
        }

        $trimmed = trim($address);
        if ($trimmed === '') {
            return null;
        }

        $parts = array_values(array_filter(
            array_map('trim', explode(',', $trimmed)),
            fn ($p) => $p !== ''
        ));

        if (count($parts) <= 2) {
            return implode(', ', $parts);
        }

        return implode(', ', array_slice($parts, -2));
    }
}
