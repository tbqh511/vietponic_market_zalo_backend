<?php

namespace App\Services;

use App\Models\Station;
use App\Models\VtpTrackingEvent;
use App\Models\ZaloOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orchestration: build payload từ ZaloOrder + ZaloDelivery + items → call VTP createOrder → persist mã vận đơn.
 * KHÔNG retry trong service — caller (controller/command) tự quyết định retry strategy.
 */
class VtpOrderService
{
    public function __construct(
        private ViettelPostService $vtp,
        private StationPickerService $picker,
    ) {}

    /**
     * Tạo đơn VTP cho 1 ZaloOrder (chỉ với delivery type=shipping).
     * Return data response từ VTP (ORDER_NUMBER, MONEY_TOTAL, EXCHANGE_WEIGHT, …).
     */
    public function dispatchOrderToVtp(ZaloOrder $order): array
    {
        $order->loadMissing(['items', 'delivery']);

        $delivery = $order->delivery;
        if (!$delivery || $delivery->type !== 'shipping') {
            throw new \InvalidArgumentException("Order #{$order->id} không phải delivery=shipping, bỏ qua VTP.");
        }
        if (!empty($delivery->vtp_order_number)) {
            throw new \RuntimeException("Order #{$order->id} đã có vtp_order_number={$delivery->vtp_order_number}, không tạo lại.");
        }

        // 1. Chọn trạm lấy hàng (cùng logic ShippingController estimate)
        $station = $this->picker->pickForReceiver(
            (int) ($delivery->province_id ?? 0),
            $delivery->lat !== null ? (float) $delivery->lat : null,
            $delivery->lng !== null ? (float) $delivery->lng : null,
        );
        if (!$station) {
            throw new \RuntimeException("Không tìm thấy trạm lấy hàng cho order #{$order->id} (province={$delivery->province_id}).");
        }

        // 2. Build LIST_ITEM + tổng weight (lấy weight từ ZaloProduct qua relation/query)
        $items = $order->items;
        $productIds = $items->pluck('product_id')->unique()->toArray();
        $products = \App\Models\ZaloProduct::whereIn('id', $productIds)->get()->keyBy('id');

        $totalWeight = 0;
        $maxLength = 0;
        $maxWidth = 0;
        $maxHeight = 0;
        $listItem = [];
        foreach ($items as $item) {
            $product = $products->get($item->product_id);
            $qty = (int) $item->quantity;
            $price = (int) $item->price;
            $weight = $product ? (int) ($product->weight ?? 0) : 0;
            $totalWeight += $weight * $qty;
            if ($product) {
                $maxLength = max($maxLength, (int) ($product->length ?? 0));
                $maxWidth  = max($maxWidth,  (int) ($product->width ?? 0));
                $maxHeight = max($maxHeight, (int) ($product->height ?? 0));
            }
            $listItem[] = [
                'PRODUCT_NAME'     => mb_substr((string) $item->name, 0, 100),
                'PRODUCT_PRICE'    => $price,
                'PRODUCT_WEIGHT'   => $weight,
                'PRODUCT_QUANTITY' => $qty,
            ];
        }
        $totalWeight = max($totalWeight, 1);

        // 3. ORDER_PAYMENT theo payment_method
        //    1 = người gửi trả cước, không COD (đã thanh toán online — BANK/ZALOPAY/MOMO)
        //    4 = người gửi trả cước + COD (COD case)
        $isCod = in_array(strtoupper((string) $order->payment_method), ['COD', 'COD_SANDBOX'], true);
        $orderPayment = $isCod ? 4 : 1;
        $moneyCollection = $isCod ? (int) $order->total : 0;

        // 4. Reference partner — quy ước "VPN{id}" để webhook parse ngược
        $reference = 'VPN' . $order->id;

        $payload = [
            'ORDER_NUMBER'       => $reference,
            'GROUPADDRESS_ID'    => 0,
            'CUS_ID'             => 0,
            'DELIVERY_DATE'      => now()->format('d/m/Y H:i:s'),
            'SENDER_FULLNAME'    => (string) ($station->name ?? 'Vietponics'),
            'SENDER_ADDRESS'     => (string) ($station->vtp_address ?: $station->address ?: ''),
            'SENDER_PHONE'       => config('viettelpost.sender_phone', ''),
            'SENDER_EMAIL'       => config('viettelpost.sender_email', ''),
            'SENDER_PROVINCE'    => (int) $station->vtp_province_id,
            'SENDER_DISTRICT'    => (int) ($station->vtp_district_id ?? 0),
            'SENDER_WARD'        => (int) ($station->vtp_ward_id ?? 0),
            'SENDER_LATITUDE'    => $station->lat !== null ? (float) $station->lat : 0,
            'SENDER_LONGITUDE'   => $station->lng !== null ? (float) $station->lng : 0,
            'RECEIVER_FULLNAME'  => (string) $delivery->name,
            'RECEIVER_ADDRESS'   => (string) $delivery->address,
            'RECEIVER_PHONE'     => (string) ($delivery->phone ?? ''),
            'RECEIVER_PROVINCE'  => (int) ($delivery->province_id ?? 0),
            'RECEIVER_DISTRICT'  => (int) ($delivery->district_id ?? 0),
            'RECEIVER_WARD'      => (int) ($delivery->ward_id ?? 0),
            'PRODUCT_NAME'       => $this->buildProductName($items),
            'PRODUCT_DESCRIPTION'=> 'Đơn hàng Vietponics #' . $order->id,
            'PRODUCT_QUANTITY'   => (int) $items->sum('quantity'),
            'PRODUCT_PRICE'      => (int) $order->subtotal,
            'PRODUCT_WEIGHT'     => $totalWeight,
            'PRODUCT_LENGTH'     => $maxLength ?: 20,
            'PRODUCT_WIDTH'      => $maxWidth ?: 15,
            'PRODUCT_HEIGHT'     => $maxHeight ?: 10,
            'PRODUCT_TYPE'       => config('viettelpost.default_product_type', 'HH'),
            'ORDER_PAYMENT'      => $orderPayment,
            'ORDER_SERVICE'      => (string) ($order->shipping_service_code ?? 'VHT'),
            'ORDER_SERVICE_ADD'  => '',
            'ORDER_VOUCHER'      => '',
            'ORDER_NOTE'         => (string) ($order->note ?? ''),
            'MONEY_COLLECTION'   => $moneyCollection,
            'NATIONAL_TYPE'      => 1,
            'LIST_ITEM'          => $listItem,
        ];

        Log::channel('viettelpost')->info('[VTP createOrder] request', [
            'order_id'  => $order->id,
            'reference' => $reference,
            'service'   => $payload['ORDER_SERVICE'],
            'station'   => $station->id,
        ]);

        // 5. Call VTP createOrder (throw nếu fail — caller log + bypass)
        $data = $this->vtp->createOrder($payload);

        // 6. Persist mã vận đơn + reference + response gốc; tạo event đầu (status 103)
        DB::transaction(function () use ($order, $delivery, $data, $reference) {
            $delivery->update([
                'vtp_order_number'    => $data['ORDER_NUMBER'],
                'vtp_order_reference' => $reference,
                'vtp_status_code'     => '103',
                'vtp_status_name'     => 'Đã tiếp nhận đơn',
                'vtp_status_at'       => now(),
                'vtp_create_response' => $data,
            ]);

            VtpTrackingEvent::create([
                'order_id'         => $order->id,
                'vtp_order_number' => $data['ORDER_NUMBER'],
                'status_code'      => 103,
                'status_name'      => 'Đã tiếp nhận đơn — Điều phối bưu cục lấy hàng',
                'location'         => null,
                'note'             => 'Tạo bởi hệ thống sau khi khách hàng đặt đơn',
                'is_returning'     => false,
                'status_at'        => now(),
                'raw_payload'      => ['source' => 'createOrder', 'response' => $data],
                'created_at'       => now(),
            ]);
        });

        return $data;
    }

    private function buildProductName($items): string
    {
        $first = $items->first();
        if (!$first) return 'Đơn hàng Vietponics';
        $count = $items->count();
        $name = mb_substr((string) $first->name, 0, 60);
        if ($count > 1) {
            return $name . " (+{$count} sản phẩm)";
        }
        return $name;
    }
}
