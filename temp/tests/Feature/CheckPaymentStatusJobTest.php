<?php

namespace Tests\Feature;

use App\Jobs\CheckPaymentStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\ZaloTestCase;

/**
 * Test cho CheckPaymentStatus Job.
 *
 * Job này được dispatch sau khi đơn hàng được link với Zalo Checkout SDK.
 * Nó gọi Zalo API để hỏi trạng thái giao dịch và cập nhật payment_status.
 *
 * Endpoint Zalo: GET https://payment-mini.zalo.me/api/transaction/get-status
 * Response: { "data": { "returnCode": 1 | 0 | -1 } }
 *   returnCode=1  → thanh toán thành công → payment_status = 'success'
 *   returnCode=-1 → giao dịch thất bại   → payment_status = 'failed'
 *   returnCode=0  → đang chờ xử lý       → payment_status giữ nguyên 'pending'
 *
 * Dùng Http::fake() để mock API Zalo – không gọi ra ngoài trong test.
 */
class CheckPaymentStatusJobTest extends ZaloTestCase
{

    private const MINI_APP_ID    = 'test_mini_app_12345';
    private const SDK_ORDER_ID   = 'zalo_checkout_sdk_order_001';
    private const ZALO_STATUS_URL = 'https://payment-mini.zalo.me/api/transaction/get-status*';

    /** Tạo ZaloOrder với trạng thái chờ thanh toán */
    private function createPendingOrder(array $attributes = []): int
    {
        return DB::table('zalo_orders')->insertGetId(array_merge([
            'status'                => 'confirmed',
            'payment_status'        => 'pending',
            'total'                 => 75000,
            'note'                  => '',
            'customer_id'           => null,
            'payment_method'        => null,
            'checkout_sdk_order_id' => self::SDK_ORDER_ID,
            'created_at'            => now(),
        ], $attributes));
    }

    /** Dispatch job và chạy handle() trực tiếp */
    private function runJob(int $orderId): void
    {
        $job = new CheckPaymentStatus($orderId, self::SDK_ORDER_ID, self::MINI_APP_ID);
        $job->handle();
    }

    // ─── returnCode = 1 (thành công) ──────────────────────────────────────────

    /**
     * Zalo trả returnCode=1 → payment_status phải được cập nhật thành 'success'.
     * Đây là case quan trọng nhất: xác nhận thanh toán thành công từ Zalo.
     */
    public function test_return_code_1_sets_payment_status_to_success(): void
    {
        Http::fake([
            self::ZALO_STATUS_URL => Http::response([
                'returnCode' => 1,
                'data'       => ['returnCode' => 1, 'returnMessage' => 'Success'],
            ], 200),
        ]);

        $orderId = $this->createPendingOrder();
        $this->runJob($orderId);

        $this->assertDatabaseHas('zalo_orders', [
            'id'             => $orderId,
            'payment_status' => 'success',
        ]);
    }

    // ─── returnCode = -1 (thất bại) ───────────────────────────────────────────

    /**
     * Zalo trả returnCode=-1 → payment_status phải được cập nhật thành 'failed'.
     * Dùng để thông báo người dùng giao dịch không thành công.
     */
    public function test_return_code_minus_1_sets_payment_status_to_failed(): void
    {
        Http::fake([
            self::ZALO_STATUS_URL => Http::response([
                'returnCode' => -1,
                'data'       => ['returnCode' => -1, 'returnMessage' => 'Transaction failed'],
            ], 200),
        ]);

        $orderId = $this->createPendingOrder();
        $this->runJob($orderId);

        $this->assertDatabaseHas('zalo_orders', [
            'id'             => $orderId,
            'payment_status' => 'failed',
        ]);
    }

    // ─── returnCode = 0 (đang chờ) ────────────────────────────────────────────

    /**
     * Zalo trả returnCode=0 (pending/chưa xử lý) → payment_status không được thay đổi.
     * Đơn hàng vẫn ở trạng thái 'pending', chờ kiểm tra lại sau.
     */
    public function test_return_code_0_does_not_change_payment_status(): void
    {
        Http::fake([
            self::ZALO_STATUS_URL => Http::response([
                'returnCode' => 0,
                'data'       => ['returnCode' => 0, 'returnMessage' => 'Processing'],
            ], 200),
        ]);

        $orderId = $this->createPendingOrder();
        $this->runJob($orderId);

        // payment_status vẫn là 'pending' – không bị ghi đè thành success hay failed
        $this->assertDatabaseHas('zalo_orders', [
            'id'             => $orderId,
            'payment_status' => 'pending',
        ]);
    }

    // ─── Edge cases ───────────────────────────────────────────────────────────

    /**
     * Đơn hàng không tồn tại → job thoát im lặng, không throw exception.
     * Tránh crash worker queue khi đơn hàng bị xóa sau khi job được dispatch.
     */
    public function test_nonexistent_order_exits_silently(): void
    {
        Http::fake(); // Không gọi API nào

        // Không có order nào được tạo
        $job = new CheckPaymentStatus(99999, self::SDK_ORDER_ID, self::MINI_APP_ID);
        $job->handle();

        // Nếu đến đây không throw exception là pass
        Http::assertNothingSent();
        $this->assertTrue(true);
    }

    /**
     * Đơn hàng đã có payment_status khác 'pending' (ví dụ 'success') → job bỏ qua.
     * Tránh ghi đè trạng thái đã được xử lý bởi webhook notifySDK trước đó.
     */
    public function test_already_processed_order_is_skipped(): void
    {
        Http::fake(); // Không được gọi API nếu order đã xử lý

        $orderId = $this->createPendingOrder(['payment_status' => 'success']);
        $this->runJob($orderId);

        Http::assertNothingSent();

        // payment_status không bị thay đổi từ 'success'
        $this->assertDatabaseHas('zalo_orders', [
            'id'             => $orderId,
            'payment_status' => 'success',
        ]);
    }

    /**
     * Zalo API trả về lỗi HTTP (ví dụ 500) → payment_status không thay đổi.
     * Job phải graceful: không crash khi Zalo side bị lỗi.
     */
    public function test_zalo_api_http_error_does_not_change_payment_status(): void
    {
        Http::fake([
            self::ZALO_STATUS_URL => Http::response(['error' => 'Internal Server Error'], 500),
        ]);

        $orderId = $this->createPendingOrder();
        $this->runJob($orderId);

        $this->assertDatabaseHas('zalo_orders', [
            'id'             => $orderId,
            'payment_status' => 'pending',
        ]);
    }

    /**
     * MAC được tạo trong job phải đúng: hash_hmac('sha256', "appId=X&orderId=Y", secret).
     * Verify bằng cách capture request và kiểm tra tham số mac được gửi lên Zalo.
     */
    public function test_job_sends_correct_mac_to_zalo_api(): void
    {
        $secret      = env('ZALO_CHECK_OUT_SECRET');
        $expectedMac = hash_hmac(
            'sha256',
            "appId=" . self::MINI_APP_ID . "&orderId=" . self::SDK_ORDER_ID,
            $secret
        );

        Http::fake([
            self::ZALO_STATUS_URL => Http::response([
                'returnCode' => 1,
                'data'       => ['returnCode' => 1],
            ], 200),
        ]);

        $orderId = $this->createPendingOrder();
        $this->runJob($orderId);

        Http::assertSent(function ($request) use ($expectedMac) {
            $queryParams = [];
            parse_str($request->url(), $queryParams);

            // Lấy query string từ URL
            $urlParts = parse_url($request->url());
            parse_str($urlParts['query'] ?? '', $queryParams);

            return isset($queryParams['mac']) && $queryParams['mac'] === $expectedMac;
        });
    }
}
