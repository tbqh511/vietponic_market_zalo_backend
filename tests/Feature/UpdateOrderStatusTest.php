<?php

namespace Tests\Feature;

use App\Models\ZaloOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\AffiliateCustomerFactory;
use Tests\BuildsReservedOrderFactory;
use Tests\TestCase;

/**
 * UpdateOrderStatusTest — admin PATCH /api/orders/{id}/status (X-Admin-Secret).
 *   - Guard forward-only + chặn huỷ delivered (422).
 *   - Huỷ hợp lệ → releaseReservation hoàn kho + cancelled_by='admin'.
 * (Regression — guard đã có sẵn trong code; chỉ phủ test.)
 */
class UpdateOrderStatusTest extends TestCase
{
    use RefreshDatabase, AffiliateCustomerFactory, BuildsReservedOrderFactory;

    private function adminHeaders(): array
    {
        return ['X-Admin-Secret' => env('ADMIN_API_SECRET')];
    }

    private function makeSimpleOrder(string $status): ZaloOrder
    {
        return ZaloOrder::create([
            'customer_id'    => $this->makeCustomer()->id,
            'status'         => $status,
            'payment_status' => 'cod',
            'payment_method' => 'COD',
            'total'          => 100000,
            'created_at'     => now(),
        ]);
    }

    public function test_requires_admin_secret(): void
    {
        $order = $this->makeSimpleOrder('pending');

        $res = $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'confirmed']);

        $res->assertStatus(403);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_blocks_backward_transition(): void
    {
        $order = $this->makeSimpleOrder('delivering');

        $res = $this->withHeaders($this->adminHeaders())
            ->patchJson("/api/orders/{$order->id}/status", ['status' => 'pending']);

        $res->assertStatus(422);
        // ORDPRO-04: message đúng mẫu "Không thể chuyển đơn từ "…" về "…".".
        $this->assertStringContainsString(
            'Không thể chuyển đơn từ "delivering" về "pending".',
            (string) $res->json('message')
        );
        $this->assertSame('delivering', $order->fresh()->status);
    }

    public function test_blocks_cancelling_delivered_order(): void
    {
        $order = $this->makeSimpleOrder('delivered');

        $res = $this->withHeaders($this->adminHeaders())
            ->patchJson("/api/orders/{$order->id}/status", ['status' => 'cancelled']);

        $res->assertStatus(422);
        // ORDPRO-05: chứa cụm "Không thể hủy đơn hàng đã giao thành công".
        $this->assertStringContainsString(
            'Không thể hủy đơn hàng đã giao thành công',
            (string) $res->json('message')
        );
        $this->assertSame('delivered', $order->fresh()->status);
    }

    public function test_forward_transition_succeeds(): void
    {
        $order = $this->makeSimpleOrder('pending');

        $res = $this->withHeaders($this->adminHeaders())
            ->patchJson("/api/orders/{$order->id}/status", ['status' => 'confirmed']);

        $res->assertOk();
        $this->assertSame('confirmed', $order->fresh()->status);
    }

    public function test_cancel_releases_reserved_stock(): void
    {
        // orderQty=4, batchQty=10 → sau reserve: quantity_sold=4.
        [$order, $batch] = $this->makeReservedOrder(['status' => 'preparing'], orderQty: 4, batchQty: 10);
        $this->assertEquals('4.00', $batch->fresh()->quantity_sold);

        $res = $this->withHeaders($this->adminHeaders())
            ->patchJson("/api/orders/{$order->id}/status", ['status' => 'cancelled']);

        $res->assertOk();
        $fresh = $order->fresh();
        $this->assertSame('cancelled', $fresh->status);
        $this->assertSame('admin', $fresh->cancelled_by);
        // Kho hoàn: quantity_sold về 0.
        $this->assertEquals('0.00', $batch->fresh()->quantity_sold);
    }
}
