<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ZaloOrder;
use App\Models\ZaloOrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

/**
 * CustomerCancelGuardTest (B4 — ORDPRO-05 + ORDPRO-11, đường KHÁCH).
 *
 * Khoá 2 hành vi của POST /api/orders/{id}/cancel (cancelByCustomer):
 *   - ORDPRO-05: KHÔNG cho khách huỷ đơn đã 'delivered' → 422, giữ nguyên trạng thái.
 *   - ORDPRO-11: khi reason_code='other' thì reason BẮT BUỘC ≥5 ký tự (rule BE
 *     required_if:reason_code,other|min:5|max:500). Gọi thẳng API với reason='a'
 *     hoặc thiếu reason → 422, đơn KHÔNG bị huỷ. Lý do preset (reason_code khác
 *     'other', không kèm reason) vẫn huỷ được bình thường.
 *
 * Guard ORDPRO-05 đã có sẵn trong code; ORDPRO-11 thêm rule mới ở B4.
 * Đường admin web/API admin phủ ở AdminWebOrderUpdateGuardTest / UpdateOrderStatusTest.
 */
class CustomerCancelGuardTest extends TestCase
{
    use RefreshDatabase;

    private function customerAuthHeaders(Customer $customer): array
    {
        $token = JWTAuth::claims(['customer_id' => $customer->id])->fromUser($customer);
        return ['Authorization' => "Bearer {$token}"];
    }

    private function makeCustomer(): Customer
    {
        return Customer::create([
            'name'        => 'Khách Test',
            'firebase_id' => '',
            'email'       => 'cust_' . uniqid() . '@test.local',
            'mobile'      => '09' . substr((string) crc32(uniqid()), 0, 8),
            'address'     => '',
            'logintype'   => 'mobile',
            'isActive'    => 1,
        ]);
    }

    /**
     * Đơn của 1 khách + 1 order item (đủ để release/refund no-op an toàn).
     */
    private function makeOrderForCustomer(Customer $customer, string $status): ZaloOrder
    {
        $order = ZaloOrder::create([
            'customer_id'    => $customer->id,
            'status'         => $status,
            'payment_status' => 'cod',
            'payment_method' => 'COD',
            'total'          => '100000',
            'created_at'     => now(),
        ]);
        ZaloOrderItem::create([
            'order_id'   => $order->id,
            'product_id' => '1',
            'name'       => 'Rau test',
            'price'      => '100000',
            'quantity'   => '1',
        ]);
        return $order;
    }

    // ── ORDPRO-05: khách KHÔNG huỷ được đơn đã giao ─────────────────────────────

    public function test_customer_cannot_cancel_delivered_order(): void
    {
        $buyer = $this->makeCustomer();
        $order = $this->makeOrderForCustomer($buyer, 'delivered');

        $res = $this->withHeaders($this->customerAuthHeaders($buyer))
            ->postJson("/api/orders/{$order->id}/cancel", ['reason_code' => 'wrong_item']);

        $res->assertStatus(422);
        $this->assertStringContainsString('delivered', (string) $res->json('message'));
        $this->assertStringContainsString('không thể huỷ', (string) $res->json('message'));
        // Trạng thái không đổi.
        $this->assertSame('delivered', $order->fresh()->status);
    }

    // ── ORDPRO-11: reason_code='other' → reason bắt buộc ≥5 ký tự ────────────────

    public function test_other_reason_under_5_chars_rejected(): void
    {
        $buyer = $this->makeCustomer();
        $order = $this->makeOrderForCustomer($buyer, 'pending');

        $res = $this->withHeaders($this->customerAuthHeaders($buyer))
            ->postJson("/api/orders/{$order->id}/cancel", [
                'reason_code' => 'other',
                'reason'      => 'a', // 1 ký tự < 5
            ]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors('reason');
        // KHÔNG được huỷ đơn khi validate fail.
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_other_reason_missing_rejected(): void
    {
        $buyer = $this->makeCustomer();
        $order = $this->makeOrderForCustomer($buyer, 'pending');

        // Chọn 'Lý do khác' nhưng KHÔNG gửi reason → required_if kích hoạt.
        $res = $this->withHeaders($this->customerAuthHeaders($buyer))
            ->postJson("/api/orders/{$order->id}/cancel", ['reason_code' => 'other']);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors('reason');
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_other_reason_valid_passes(): void
    {
        $buyer = $this->makeCustomer();
        $order = $this->makeOrderForCustomer($buyer, 'pending');

        $res = $this->withHeaders($this->customerAuthHeaders($buyer))
            ->postJson("/api/orders/{$order->id}/cancel", [
                'reason_code' => 'other',
                'reason'      => 'Đổi ý không mua nữa',
            ]);

        $res->assertStatus(200);
        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_preset_reason_without_text_passes(): void
    {
        // reason_code KHÁC 'other' + không kèm reason → required_if KHÔNG kích hoạt,
        // vẫn huỷ bình thường (rule mới không over-block lý do preset).
        $buyer = $this->makeCustomer();
        $order = $this->makeOrderForCustomer($buyer, 'pending');

        $res = $this->withHeaders($this->customerAuthHeaders($buyer))
            ->postJson("/api/orders/{$order->id}/cancel", ['reason_code' => 'changed_mind']);

        $res->assertStatus(200);
        $this->assertSame('cancelled', $order->fresh()->status);
    }
}
