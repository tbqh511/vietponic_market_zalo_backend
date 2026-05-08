<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\ZaloTestCase;

/**
 * Integration test cho endpoint PATCH /api/orders/{id}/status.
 *
 * Endpoint này chỉ dành cho admin (middleware zalo.admin) và cho phép cập nhật
 * trạng thái đơn hàng theo vòng đời: pending → confirmed → preparing → delivering → delivered.
 * Cũng có thể chuyển sang 'cancelled' ở bất kỳ bước nào.
 *
 * Authentication: Header X-Admin-Secret phải khớp với env ADMIN_API_SECRET.
 */
class UpdateOrderStatusTest extends ZaloTestCase
{

    private const ADMIN_SECRET = 'test_admin_secret_for_testing';
    private const VALID_STATUSES = [
        'pending', 'confirmed', 'preparing', 'delivering', 'delivered', 'cancelled',
    ];

    /** Header admin hợp lệ để tái sử dụng */
    private function adminHeaders(): array
    {
        return ['X-Admin-Secret' => self::ADMIN_SECRET];
    }

    /** Tạo một ZaloOrder test với status cho trước */
    private function createOrder(string $status = 'pending'): int
    {
        return DB::table('zalo_orders')->insertGetId([
            'status'         => $status,
            'payment_status' => 'cod',
            'total'          => 50000,
            'note'           => '',
            'customer_id'    => null,
            'created_at'     => now(),
        ]);
    }

    // ─── Happy path ───────────────────────────────────────────────────────────

    /**
     * Admin cập nhật status từ 'pending' → 'confirmed' → HTTP 200.
     * Đây là bước đầu tiên trong vòng đời đơn hàng sau khi nhận được.
     */
    public function test_valid_status_update_returns_200_and_updates_db(): void
    {
        $orderId = $this->createOrder('pending');

        $response = $this->patchJson(
            "/api/orders/{$orderId}/status",
            ['status' => 'confirmed'],
            $this->adminHeaders()
        );

        $response->assertStatus(200)
            ->assertJson(['error' => false])
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('zalo_orders', [
            'id'     => $orderId,
            'status' => 'confirmed',
        ]);
    }

    /**
     * Tất cả 6 status hợp lệ đều phải được chấp nhận.
     * Dùng data provider tương đương.
     */
    public function test_all_valid_statuses_are_accepted(): void
    {
        foreach (self::VALID_STATUSES as $status) {
            $orderId = $this->createOrder('pending');

            $response = $this->patchJson(
                "/api/orders/{$orderId}/status",
                ['status' => $status],
                $this->adminHeaders()
            );

            $this->assertSame(200, $response->status(), "Status '{$status}' phải được chấp nhận");

            $this->assertDatabaseHas('zalo_orders', [
                'id'     => $orderId,
                'status' => $status,
            ]);
        }
    }

    /**
     * Response phải kèm theo dữ liệu đơn hàng đầy đủ (data object).
     */
    public function test_response_includes_updated_order_data(): void
    {
        $orderId = $this->createOrder('confirmed');

        $response = $this->patchJson(
            "/api/orders/{$orderId}/status",
            ['status' => 'preparing'],
            $this->adminHeaders()
        );

        $response->assertStatus(200)
            ->assertJsonStructure([
                'error',
                'data' => ['id', 'status', 'payment_status', 'total'],
            ]);
    }

    // ─── Validation errors ────────────────────────────────────────────────────

    /**
     * Status không nằm trong whitelist → HTTP 422 Unprocessable Entity.
     * Ngăn admin set status tùy tiện làm vỡ state machine của đơn hàng.
     */
    public function test_invalid_status_returns_422(): void
    {
        $orderId = $this->createOrder('pending');

        $response = $this->patchJson(
            "/api/orders/{$orderId}/status",
            ['status' => 'processing'], // không có trong whitelist
            $this->adminHeaders()
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrorFor('status');
    }

    /**
     * Thiếu field `status` → HTTP 422.
     */
    public function test_missing_status_field_returns_422(): void
    {
        $orderId = $this->createOrder('pending');

        $this->patchJson(
            "/api/orders/{$orderId}/status",
            [], // không gửi status
            $this->adminHeaders()
        )
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('status');
    }

    /**
     * Các status không hợp lệ điển hình → đều phải bị từ chối.
     */
    public function test_various_invalid_statuses_return_422(): void
    {
        $orderId = $this->createOrder();

        $invalidStatuses = ['processing', 'done', 'paid', 'refunded', '', '   '];

        foreach ($invalidStatuses as $status) {
            $response = $this->patchJson(
                "/api/orders/{$orderId}/status",
                ['status' => $status],
                $this->adminHeaders()
            );

            $this->assertContains(
                $response->status(),
                [422, 500],
                "Status '{$status}' phải bị từ chối (422 hoặc lỗi)"
            );
        }
    }

    // ─── Not found ────────────────────────────────────────────────────────────

    /**
     * orderId không tồn tại → HTTP 404.
     */
    public function test_nonexistent_order_returns_404(): void
    {
        $this->patchJson(
            '/api/orders/99999/status',
            ['status' => 'confirmed'],
            $this->adminHeaders()
        )
            ->assertStatus(404)
            ->assertJson(['error' => true]);
    }

    // ─── Authentication ───────────────────────────────────────────────────────

    /**
     * Không có header X-Admin-Secret → HTTP 403 Forbidden.
     * Bảo vệ endpoint admin khỏi request không xác thực.
     */
    public function test_missing_admin_secret_header_returns_403(): void
    {
        $orderId = $this->createOrder();

        $this->patchJson(
            "/api/orders/{$orderId}/status",
            ['status' => 'confirmed']
            // không có adminHeaders()
        )
            ->assertStatus(403);
    }

    /**
     * Header X-Admin-Secret sai → HTTP 403 Forbidden.
     * Ngăn brute-force bằng secret sai.
     */
    public function test_wrong_admin_secret_returns_403(): void
    {
        $orderId = $this->createOrder();

        $this->patchJson(
            "/api/orders/{$orderId}/status",
            ['status' => 'confirmed'],
            ['X-Admin-Secret' => 'wrong_secret_value']
        )
            ->assertStatus(403);
    }

    /**
     * ADMIN_API_SECRET chưa được cấu hình trong env → HTTP 500.
     * Middleware phải phát hiện và báo lỗi server config thay vì silently fail.
     */
    public function test_missing_admin_api_secret_env_returns_500(): void
    {
        $orderId  = $this->createOrder();
        $original = env('ADMIN_API_SECRET');

        putenv('ADMIN_API_SECRET=');
        $_ENV['ADMIN_API_SECRET']    = '';
        $_SERVER['ADMIN_API_SECRET'] = '';

        try {
            $this->patchJson(
                "/api/orders/{$orderId}/status",
                ['status' => 'confirmed'],
                ['X-Admin-Secret' => self::ADMIN_SECRET]
            )
                ->assertStatus(500);
        } finally {
            putenv("ADMIN_API_SECRET={$original}");
            $_ENV['ADMIN_API_SECRET']    = $original;
            $_SERVER['ADMIN_API_SECRET'] = $original;
        }
    }
}
