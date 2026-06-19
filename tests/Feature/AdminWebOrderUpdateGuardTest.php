<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ZaloOrder;
use App\Models\ZaloOrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AdminWebOrderUpdateGuardTest (B4 — ORDPRO-04/05, đường ADMIN WEB).
 *
 * Khoá guard transition của Admin\ZaloOrderController@update (route
 * zalo-orders.update, middleware auth+checklogin+language). Auth bằng User active
 * (status=1, type=0 admin) — cùng pattern AdminProductCreateTest.
 *
 * Lỗi guard trả qua redirect()->back()->withErrors(['status' => ...]) → assert
 * bằng assertSessionHasErrors + DB không đổi:
 *   - ORDPRO-04: chặn lùi delivering → pending (message "Không thể chuyển đơn từ…").
 *   - ORDPRO-05: chặn huỷ đơn delivered (message "Không thể hủy đơn hàng đã giao thành công").
 *   - Regression: chuyển tiến confirmed → preparing vẫn OK (guard không over-block).
 *
 * Guard đã có sẵn trong code; B4 chỉ bổ sung test. Đường API admin phủ ở
 * UpdateOrderStatusTest, đường khách ở CustomerCancelGuardTest.
 */
class AdminWebOrderUpdateGuardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'status'      => 1,
            'type'        => 0,
            'permissions' => '',
        ]);
    }

    /**
     * Đơn + 1 order item (admin web recalc total từ items sau guard → cần ≥1 item).
     */
    private function makeOrder(string $status): ZaloOrder
    {
        $order = ZaloOrder::create([
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

    // ── ORDPRO-04: chặn lùi trạng thái ─────────────────────────────────────────

    public function test_blocks_backward_transition(): void
    {
        $order = $this->makeOrder('delivering');

        $res = $this->actingAs($this->admin())
            ->patch(route('zalo-orders.update', $order->id), ['status' => 'pending']);

        $res->assertSessionHasErrors([
            'status' => 'Không thể chuyển đơn từ "đang giao" về "chờ xác nhận".',
        ]);
        // Trạng thái không đổi.
        $this->assertSame('delivering', $order->fresh()->status);
    }

    // ── ORDPRO-05: chặn huỷ đơn đã giao ────────────────────────────────────────

    public function test_blocks_cancelling_delivered(): void
    {
        $order = $this->makeOrder('delivered');

        $res = $this->actingAs($this->admin())
            ->patch(route('zalo-orders.update', $order->id), ['status' => 'cancelled']);

        $res->assertSessionHasErrors('status');
        $this->assertStringContainsString(
            'Không thể hủy đơn hàng đã giao thành công',
            session('errors')->get('status')[0]
        );
        $this->assertSame('delivered', $order->fresh()->status);
    }

    // ── Regression: chuyển tiến hợp lệ vẫn lưu được ────────────────────────────

    public function test_forward_transition_succeeds(): void
    {
        $order = $this->makeOrder('confirmed');

        $res = $this->actingAs($this->admin())
            ->patch(route('zalo-orders.update', $order->id), ['status' => 'preparing']);

        $res->assertSessionHasNoErrors();
        $res->assertRedirect(route('zalo-orders.show', $order->id));
        $this->assertSame('preparing', $order->fresh()->status);
    }
}
