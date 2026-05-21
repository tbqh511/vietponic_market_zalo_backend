<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use App\Services\VoucherService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class VoucherApiController extends Controller
{
    public function __construct(private VoucherService $voucherService) {}

    /**
     * GET /api/vouchers/available?subtotal=...&shipping_fee=...
     *
     * Liệt kê voucher public + active + trong hạn, kèm cờ `usable` để FE
     * biết voucher nào dùng được với giỏ hàng hiện tại. Voucher không
     * usable vẫn show (xám + lý do) để khách hiểu cần tăng đơn / chọn ship.
     */
    public function available(Request $request)
    {
        $customerId = (int) $request->attributes->get('zalo_customer_id');
        $subtotal   = (int) $request->query('subtotal', 0);
        $shipping   = (int) $request->query('shipping_fee', 0);
        $now        = Carbon::now();

        $vouchers = Voucher::query()
            ->where('is_public', true)
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $now);
            })
            ->where(function ($q) {
                $q->whereNull('max_uses')->orWhereColumn('used_count', '<', 'max_uses');
            })
            ->orderByDesc('id')
            ->get();

        $data = $vouchers->map(function (Voucher $v) use ($customerId, $subtotal, $shipping) {
            $check  = $v->isUsable($customerId, $subtotal);
            $usable = $check === true;
            $reason = $usable ? null : $check;
            $breakdown = $this->voucherService->breakdown($v, $subtotal, $shipping);

            // free_shipping mà đơn không có shipping → mark unusable
            if (
                $usable
                && $v->discount_type === Voucher::TYPE_FREE_SHIPPING
                && $shipping <= 0
            ) {
                $usable = false;
                $reason = 'Áp dụng cho đơn giao hàng';
            }

            return [
                'id'                   => $v->id,
                'code'                 => $v->code,
                'name'                 => $v->name,
                'description'          => $v->description,
                'discount_type'        => $v->discount_type,
                'discount_value'       => (float) $v->discount_value,
                'max_discount_amount'  => $v->max_discount_amount,
                'min_order_amount'     => (int) $v->min_order_amount,
                'valid_from'           => optional($v->valid_from)->toIso8601String(),
                'valid_to'             => optional($v->valid_to)->toIso8601String(),
                'is_public'            => (bool) $v->is_public,
                'usable'               => $usable,
                'unusable_reason'      => $reason,
                'preview_subtotal'     => $breakdown['subtotal'],
                'preview_shipping'     => $breakdown['shipping'],
                'preview_total'        => $breakdown['subtotal'] + $breakdown['shipping'],
            ];
        });

        return response()->json(['error' => false, 'data' => $data]);
    }

    /**
     * POST /api/vouchers/validate { code, subtotal, shipping_fee }
     * Dùng khi khách nhập mã thủ công ở Cart sheet.
     */
    public function validateCode(Request $request)
    {
        $request->validate([
            'code'         => 'required|string|max:50',
            'subtotal'     => 'required|integer|min:0',
            'shipping_fee' => 'nullable|integer|min:0',
        ]);

        $customerId = (int) $request->attributes->get('zalo_customer_id');
        $subtotal   = (int) $request->input('subtotal');
        $shipping   = (int) $request->input('shipping_fee', 0);

        $res = $this->voucherService->validate(
            (string) $request->input('code'),
            $customerId,
            $subtotal,
            $shipping
        );

        if (!$res['ok']) {
            return response()->json([
                'error'   => true,
                'ok'      => false,
                'message' => $res['error'],
            ], 422);
        }

        /** @var Voucher $v */
        $v = $res['voucher'];
        return response()->json([
            'error' => false,
            'ok'    => true,
            'data'  => [
                'voucher' => [
                    'id'                   => $v->id,
                    'code'                 => $v->code,
                    'name'                 => $v->name,
                    'description'          => $v->description,
                    'discount_type'        => $v->discount_type,
                    'discount_value'       => (float) $v->discount_value,
                    'max_discount_amount'  => $v->max_discount_amount,
                    'min_order_amount'     => (int) $v->min_order_amount,
                ],
                'discount_subtotal' => (int) $res['discount_subtotal'],
                'discount_shipping' => (int) $res['discount_shipping'],
                'discount_total'    => (int) ($res['discount_subtotal'] + $res['discount_shipping']),
            ],
        ]);
    }
}
