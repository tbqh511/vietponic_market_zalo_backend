<?php

namespace App\Services;

use App\Models\Voucher;
use App\Models\VoucherRedemption;
use App\Models\ZaloOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * VoucherService — validate / calculate / redeem / release.
 *
 * Pattern theo StockService:
 *   - validate() trả ['ok' => bool, ...] thay vì throw, để controller dễ build
 *     HTTP response 422.
 *   - redeem() và release() chạy bên trong transaction cùng order để tránh
 *     race condition (2 khách dùng nốt voucher cuối cùng).
 *
 * 3 loại discount MVP:
 *   - percent       : discount_value = % (0..100), capped bởi max_discount_amount.
 *   - fixed         : discount_value = số tiền VND.
 *   - free_shipping : giảm phí ship; discount_value > 0 → đóng vai cap, = 0 → free toàn bộ.
 */
class VoucherService
{
    /**
     * Validate voucher cho 1 customer + 1 đơn cụ thể.
     *
     * @return array{ok:bool, voucher?:Voucher, discount_subtotal?:int, discount_shipping?:int, error?:string}
     */
    public function validate(string $code, int $customerId, int $subtotal, int $shippingFee): array
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return ['ok' => false, 'error' => 'Vui lòng nhập mã giảm giá'];
        }

        $voucher = Voucher::whereRaw('UPPER(code) = ?', [$code])->first();
        if (!$voucher) {
            return ['ok' => false, 'error' => 'Mã giảm giá không tồn tại'];
        }

        $check = $voucher->isUsable($customerId, $subtotal);
        if ($check !== true) {
            return ['ok' => false, 'error' => $check];
        }

        $breakdown = $this->breakdown($voucher, $subtotal, $shippingFee);

        // free_shipping mà đơn không có shipping → coi như không dùng được
        // (UX tốt hơn là chặn ngay thay vì cho dùng nhưng không giảm gì).
        if (
            $voucher->discount_type === Voucher::TYPE_FREE_SHIPPING
            && $shippingFee <= 0
        ) {
            return ['ok' => false, 'error' => 'Mã chỉ áp dụng cho đơn giao hàng'];
        }

        return [
            'ok'                 => true,
            'voucher'            => $voucher,
            'discount_subtotal'  => $breakdown['subtotal'],
            'discount_shipping'  => $breakdown['shipping'],
        ];
    }

    /**
     * Tính tổng số tiền giảm (subtotal + shipping). Pure function — không
     * touch DB. Dùng cả ở checkout flow để verify total, và ở /vouchers/available
     * để show preview cho FE.
     */
    public function calculateDiscount(Voucher $voucher, int $subtotal, int $shippingFee): int
    {
        $b = $this->breakdown($voucher, $subtotal, $shippingFee);
        return $b['subtotal'] + $b['shipping'];
    }

    /**
     * Tách giảm theo từng phần: subtotal vs shipping.
     *
     * @return array{subtotal:int, shipping:int}
     */
    public function breakdown(Voucher $voucher, int $subtotal, int $shippingFee): array
    {
        $value = (float) $voucher->discount_value;
        $subtotalDiscount = 0;
        $shippingDiscount = 0;

        switch ($voucher->discount_type) {
            case Voucher::TYPE_PERCENT:
                $raw = (int) floor($subtotal * $value / 100);
                if (!is_null($voucher->max_discount_amount)) {
                    $raw = min($raw, (int) $voucher->max_discount_amount);
                }
                $subtotalDiscount = min($raw, $subtotal);
                break;

            case Voucher::TYPE_FIXED:
                $subtotalDiscount = min((int) $value, $subtotal);
                break;

            case Voucher::TYPE_FREE_SHIPPING:
                if ($value > 0) {
                    // value đóng vai cap (vd: giảm tối đa 30.000đ phí ship)
                    $shippingDiscount = min((int) $value, $shippingFee);
                } else {
                    // value = 0 → free toàn bộ phí ship
                    $shippingDiscount = $shippingFee;
                }
                $shippingDiscount = max(0, $shippingDiscount);
                break;
        }

        return [
            'subtotal' => max(0, $subtotalDiscount),
            'shipping' => max(0, $shippingDiscount),
        ];
    }

    /**
     * Ghi nhận redemption + tăng used_count. Bắt buộc phải gọi trong cùng
     * transaction với việc tạo order (để rollback nếu allocation fail).
     */
    public function redeem(Voucher $voucher, ZaloOrder $order, int $customerId, int $discountAmount): void
    {
        VoucherRedemption::create([
            'voucher_id'      => $voucher->id,
            'order_id'        => $order->id,
            'customer_id'     => $customerId,
            'discount_amount' => $discountAmount,
            'redeemed_at'     => Carbon::now(),
        ]);

        // Atomic increment để tránh race với checkout concurrent.
        Voucher::where('id', $voucher->id)->increment('used_count');
    }

    /**
     * Hoàn lượt voucher khi order bị huỷ. Idempotent — không lỗi nếu order
     * chưa từng dùng voucher.
     */
    public function release(ZaloOrder $order): void
    {
        if (!$order->voucher_id) {
            return;
        }

        DB::transaction(function () use ($order) {
            $redemption = VoucherRedemption::where('order_id', $order->id)->first();
            if (!$redemption) {
                return;
            }

            $voucherId = $redemption->voucher_id;
            $redemption->delete();

            // Giảm used_count nhưng không xuống âm.
            $voucher = Voucher::where('id', $voucherId)->lockForUpdate()->first();
            if ($voucher && $voucher->used_count > 0) {
                $voucher->decrement('used_count');
            }
        });
    }
}
