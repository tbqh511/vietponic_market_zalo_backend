<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fire khi đơn chuyển sang status 'delivered' (giao thành công), tại MỌI điểm
 * chuyển status: ZaloApiController::updateStatus (API admin), ZaloOrderController::update
 * (admin web), VtpWebhookService (webhook VTP code 501).
 *
 * AFF-03 (B2): mốc ghi hoa hồng CTV dời từ OrderPaymentSucceeded sang đây, để
 * hoa hồng tính theo đơn GIAO THÀNH CÔNG (gồm cả COD), không theo thanh toán.
 */
class OrderDelivered
{
    use Dispatchable, SerializesModels;

    public int $orderId;

    public function __construct(int $orderId)
    {
        $this->orderId = $orderId;
    }
}
