<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Farm;
use App\Models\OrderFarmAssignment;
use App\Models\OrderPackingLog;
use App\Models\ZaloDelivery;
use App\Models\ZaloOrder;
use App\Models\ZaloOrderItem;
use App\Models\ZaloProduct;
use App\Support\ContactMasker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * OrderPackingTest — khâu đóng gói đơn (Packing Station).
 *
 *   A. Phân công (owner gán staff) + audit log.
 *   B. Phân quyền: staff chỉ thấy/thao tác đơn được gán cho mình.
 *   C. Luồng status: confirm-packed → đơn sang 'delivering' khi mọi farm xong.
 *   D. Ẩn thông tin: incoming không chứa SĐT/địa chỉ đầy đủ.
 *   E. ContactMasker (unit).
 *
 * Dùng SQLite :memory: (phpunit.xml). Lặp lại pattern helper từ FarmHubTest.
 */
class OrderPackingTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function makeCustomer(array $attrs = []): Customer
    {
        static $seq = 0;
        $seq++;
        return Customer::create(array_merge([
            'name'        => "Customer {$seq}",
            'firebase_id' => '',
            'email'       => "pack{$seq}_" . uniqid() . '@test.local',
            'mobile'      => '09' . str_pad((string)(30000000 + $seq), 8, '0', STR_PAD_LEFT),
            'address'     => '',
            'logintype'   => 'mobile',
            'isActive'    => 1,
        ], $attrs));
    }

    /** Tạo farm + owner. */
    private function makeFarmPartner(): array
    {
        $owner = $this->makeCustomer([
            'role'                => 'farm_partner',
            'farm_partner_status' => 'approved',
        ]);

        $farm = Farm::create([
            'code'              => 'PACK' . uniqid(),
            'name'              => 'Packing Farm',
            'owner_customer_id' => $owner->id,
            'address'           => 'Đà Lạt',
            'commission_rate'   => 0.1000,
            'payment_cycle'     => 'monthly',
            'is_active'         => true,
            'approved_at'       => now(),
        ]);

        $owner->forceFill(['farm_id' => $farm->id, 'farm_role' => 'owner'])->save();

        return [$owner, $farm];
    }

    /** Tạo nhân viên (staff) thuộc farm cho trước. */
    private function makeStaff(Farm $farm): Customer
    {
        return $this->makeCustomer([
            'role'                => 'farm_partner',
            'farm_partner_status' => 'approved',
            'farm_id'             => $farm->id,
            'farm_role'           => 'staff',
        ]);
    }

    private function makeProduct(): ZaloProduct
    {
        static $seq = 0;
        $seq++;
        \App\Models\ZaloCategory::firstOrCreate(['id' => 1], ['name' => 'Test Category']);
        return ZaloProduct::create([
            'id'          => 7000 + $seq,
            'category_id' => 1,
            'name'        => "Rau Pack {$seq}",
            'price'       => '15000',
        ]);
    }

    /**
     * Tạo một đơn có 1 item thuộc $farm, kèm delivery shipping (để test mask).
     */
    private function makeOrderForFarm(Farm $farm, Customer $buyer, array $orderAttrs = []): ZaloOrder
    {
        $product = $this->makeProduct();

        $order = ZaloOrder::create(array_merge([
            'customer_id'    => $buyer->id,
            'status'         => 'confirmed',
            'payment_status' => 'cod',
            'total'          => '90000',
            'created_at'     => now(),
        ], $orderAttrs));

        ZaloOrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'farm_id'    => $farm->id,
            'name'       => $product->name,
            'price'      => '15000',
            'quantity'   => 6,
        ]);

        ZaloDelivery::create([
            'order_id' => $order->id,
            'type'     => 'shipping',
            'name'     => 'Nguyễn Văn A',
            'phone'    => '0937456739',
            'address'  => '12 Lê Lợi, P. Bến Nghé, Q.1, TP.HCM',
        ]);

        return $order;
    }

    private function authHeader(Customer $customer): array
    {
        return ['Authorization' => 'Bearer ' . JWTAuth::fromUser($customer)];
    }

    /** Lấy phiếu (order, farm) — tạo trước qua incoming hoặc trực tiếp. */
    private function assignmentFor(int $orderId, int $farmId): OrderFarmAssignment
    {
        return OrderFarmAssignment::firstOrCreate(
            ['order_id' => $orderId, 'farm_id' => $farmId],
            ['status' => OrderFarmAssignment::STATUS_UNASSIGNED]
        );
    }

    // ─── A. Phân công + audit log ─────────────────────────────────────────────

    public function test_owner_can_assign_order_to_staff_and_log_is_recorded(): void
    {
        [$owner, $farm] = $this->makeFarmPartner();
        $staff = $this->makeStaff($farm);
        $buyer = $this->makeCustomer();
        $order = $this->makeOrderForFarm($farm, $buyer);

        $response = $this->withHeaders($this->authHeader($owner))
            ->postJson("/api/farm/orders/{$order->id}/assign", [
                'packer_customer_id' => $staff->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.assignment_status', 'assigned')
            ->assertJsonPath('data.assigned_customer_id', $staff->id);

        $this->assertDatabaseHas('order_farm_assignments', [
            'order_id'             => $order->id,
            'farm_id'              => $farm->id,
            'assigned_customer_id' => $staff->id,
            'status'               => 'assigned',
        ]);

        // Audit log ghi đúng actor (owner) + action 'assigned'.
        $log = OrderPackingLog::where('order_id', $order->id)
            ->where('action', 'assigned')
            ->first();
        $this->assertNotNull($log);
        $this->assertEquals($owner->id, $log->actor_customer_id);
        $this->assertEquals($staff->id, $log->meta['packer_customer_id']);
    }

    public function test_staff_cannot_assign_orders(): void
    {
        [$owner, $farm] = $this->makeFarmPartner();
        $staff  = $this->makeStaff($farm);
        $staff2 = $this->makeStaff($farm);
        $buyer  = $this->makeCustomer();
        $order  = $this->makeOrderForFarm($farm, $buyer);

        $response = $this->withHeaders($this->authHeader($staff))
            ->postJson("/api/farm/orders/{$order->id}/assign", [
                'packer_customer_id' => $staff2->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_cannot_assign_packer_from_other_farm(): void
    {
        [$owner, $farm]   = $this->makeFarmPartner();
        [, $otherFarm]    = $this->makeFarmPartner();
        $outsider         = $this->makeStaff($otherFarm);
        $buyer            = $this->makeCustomer();
        $order            = $this->makeOrderForFarm($farm, $buyer);

        $response = $this->withHeaders($this->authHeader($owner))
            ->postJson("/api/farm/orders/{$order->id}/assign", [
                'packer_customer_id' => $outsider->id,
            ]);

        $response->assertStatus(422);
    }

    // ─── B. Phân quyền hiển thị (incoming) ────────────────────────────────────

    public function test_staff_sees_all_farm_orders_with_is_mine_flag(): void
    {
        // Mô hình self-claim: staff THẤY mọi đơn của farm (để nhận đơn chưa ai
        // nhận / thấy đơn người khác đang đóng). is_mine phân biệt đơn của mình.
        [$owner, $farm] = $this->makeFarmPartner();
        $staffA = $this->makeStaff($farm);
        $staffB = $this->makeStaff($farm);
        $buyer  = $this->makeCustomer();

        $orderA = $this->makeOrderForFarm($farm, $buyer);
        $orderB = $this->makeOrderForFarm($farm, $buyer);

        // Gán orderA cho staffA, orderB cho staffB.
        $this->assignmentFor($orderA->id, $farm->id)->update([
            'assigned_customer_id' => $staffA->id,
            'status'               => 'assigned',
        ]);
        $this->assignmentFor($orderB->id, $farm->id)->update([
            'assigned_customer_id' => $staffB->id,
            'status'               => 'assigned',
        ]);

        $response = $this->withHeaders($this->authHeader($staffA))
            ->getJson('/api/farm/orders/incoming');

        $response->assertOk();
        $rows = collect($response->json('data'));

        // Staff A thấy CẢ HAI đơn.
        $orderIds = $rows->pluck('order_id')->unique()->all();
        $this->assertContains($orderA->id, $orderIds);
        $this->assertContains($orderB->id, $orderIds);

        // is_mine đúng: chỉ orderA là của staffA.
        $this->assertTrue($rows->firstWhere('order_id', $orderA->id)['is_mine']);
        $this->assertFalse($rows->firstWhere('order_id', $orderB->id)['is_mine']);
    }

    public function test_staff_can_claim_unassigned_order(): void
    {
        [$owner, $farm] = $this->makeFarmPartner();
        $staff = $this->makeStaff($farm);
        $buyer = $this->makeCustomer();
        $order = $this->makeOrderForFarm($farm, $buyer);

        // Phiếu chưa ai nhận.
        $this->assignmentFor($order->id, $farm->id);

        $response = $this->withHeaders($this->authHeader($staff))
            ->postJson("/api/farm/orders/{$order->id}/claim");

        $response->assertOk()
            ->assertJsonPath('data.assignment_status', 'assigned')
            ->assertJsonPath('data.assigned_customer_id', $staff->id)
            ->assertJsonPath('data.is_mine', true);

        $this->assertDatabaseHas('order_farm_assignments', [
            'order_id'             => $order->id,
            'assigned_customer_id' => $staff->id,
            'status'               => 'assigned',
        ]);

        // Log 'claimed' ghi đúng actor.
        $log = OrderPackingLog::where('order_id', $order->id)->where('action', 'claimed')->first();
        $this->assertNotNull($log);
        $this->assertEquals($staff->id, $log->actor_customer_id);
    }

    public function test_staff_cannot_claim_order_already_taken(): void
    {
        [$owner, $farm] = $this->makeFarmPartner();
        $staffA = $this->makeStaff($farm);
        $staffB = $this->makeStaff($farm);
        $buyer  = $this->makeCustomer();
        $order  = $this->makeOrderForFarm($farm, $buyer);

        // staffA đã nhận.
        $this->assignmentFor($order->id, $farm->id)->update([
            'assigned_customer_id' => $staffA->id,
            'status'               => 'assigned',
        ]);

        // staffB cố nhận → 422.
        $this->withHeaders($this->authHeader($staffB))
            ->postJson("/api/farm/orders/{$order->id}/claim")
            ->assertStatus(422);
    }

    public function test_owner_sees_all_farm_orders(): void
    {
        [$owner, $farm] = $this->makeFarmPartner();
        $buyer = $this->makeCustomer();
        $order1 = $this->makeOrderForFarm($farm, $buyer);
        $order2 = $this->makeOrderForFarm($farm, $buyer);

        $response = $this->withHeaders($this->authHeader($owner))
            ->getJson('/api/farm/orders/incoming');

        $response->assertOk();
        $orderIds = collect($response->json('data'))->pluck('order_id')->unique()->all();
        $this->assertContains($order1->id, $orderIds);
        $this->assertContains($order2->id, $orderIds);
    }

    public function test_unassigned_staff_cannot_start_or_confirm(): void
    {
        [$owner, $farm] = $this->makeFarmPartner();
        $staffA = $this->makeStaff($farm);
        $staffB = $this->makeStaff($farm);
        $buyer  = $this->makeCustomer();
        $order  = $this->makeOrderForFarm($farm, $buyer);

        $this->assignmentFor($order->id, $farm->id)->update([
            'assigned_customer_id' => $staffA->id,
            'status'               => 'assigned',
        ]);

        // staffB chưa được gán → 403 cho cả 2 action.
        $this->withHeaders($this->authHeader($staffB))
            ->postJson("/api/farm/orders/{$order->id}/start-packing")
            ->assertStatus(403);

        $this->withHeaders($this->authHeader($staffB))
            ->postJson("/api/farm/orders/{$order->id}/confirm-packed")
            ->assertStatus(403);
    }

    // ─── C. Luồng status: confirm-packed → delivering ─────────────────────────

    public function test_confirm_packed_marks_slip_packed_without_auto_delivering(): void
    {
        // Theo wireframe: confirm-packed chỉ đánh dấu phiếu 'packed', KHÔNG tự
        // đẩy đơn sang delivering — chủ farm bấm "Bàn giao ship" riêng.
        [$owner, $farm] = $this->makeFarmPartner();
        $staff = $this->makeStaff($farm);
        $buyer = $this->makeCustomer();
        $order = $this->makeOrderForFarm($farm, $buyer, ['status' => 'preparing']);

        $this->assignmentFor($order->id, $farm->id)->update([
            'assigned_customer_id' => $staff->id,
            'status'               => 'assigned',
        ]);

        $response = $this->withHeaders($this->authHeader($staff))
            ->postJson("/api/farm/orders/{$order->id}/confirm-packed");

        $response->assertOk()
            ->assertJsonPath('data.assignment_status', 'packed');

        // Phiếu packed nhưng đơn VẪN preparing (chờ owner bàn giao).
        $order->refresh();
        $this->assertEquals('preparing', $order->status);
        $this->assertNull($order->delivered_at);

        // Chưa có log status_changed sang delivering (chưa bàn giao).
        $this->assertDatabaseMissing('order_packing_logs', [
            'order_id'  => $order->id,
            'action'    => 'status_changed',
            'to_status' => 'delivering',
        ]);
    }

    public function test_owner_handoff_ship_moves_packed_order_to_delivering(): void
    {
        [$owner, $farm] = $this->makeFarmPartner();
        $staff = $this->makeStaff($farm);
        $buyer = $this->makeCustomer();
        $order = $this->makeOrderForFarm($farm, $buyer, ['status' => 'preparing']);

        // Phiếu đã đóng gói xong.
        $this->assignmentFor($order->id, $farm->id)->update([
            'assigned_customer_id' => $staff->id,
            'status'               => 'packed',
            'packed_at'            => now(),
        ]);

        $response = $this->withHeaders($this->authHeader($owner))
            ->postJson("/api/farm/orders/{$order->id}/handoff-ship");

        $response->assertOk()
            ->assertJsonPath('data.order_status', 'delivering');

        $order->refresh();
        $this->assertEquals('delivering', $order->status);
        $this->assertNull($order->delivered_at);

        // Log status_changed preparing → delivering + log handed_off.
        $statusLog = OrderPackingLog::where('order_id', $order->id)
            ->where('action', 'status_changed')->first();
        $this->assertNotNull($statusLog);
        $this->assertEquals('preparing', $statusLog->from_status);
        $this->assertEquals('delivering', $statusLog->to_status);
        $this->assertDatabaseHas('order_packing_logs', [
            'order_id' => $order->id,
            'action'   => 'handed_off',
        ]);
    }

    public function test_handoff_ship_blocked_when_a_farm_not_packed_yet(): void
    {
        [$owner, $farm] = $this->makeFarmPartner();
        $staff = $this->makeStaff($farm);
        $buyer = $this->makeCustomer();
        $order = $this->makeOrderForFarm($farm, $buyer, ['status' => 'preparing']);

        // Phiếu mới đang đóng dở (packing), chưa packed.
        $this->assignmentFor($order->id, $farm->id)->update([
            'assigned_customer_id' => $staff->id,
            'status'               => 'packing',
        ]);

        $this->withHeaders($this->authHeader($owner))
            ->postJson("/api/farm/orders/{$order->id}/handoff-ship")
            ->assertStatus(422);

        $order->refresh();
        $this->assertEquals('preparing', $order->status);
    }

    public function test_staff_cannot_handoff_or_confirm_order(): void
    {
        [$owner, $farm] = $this->makeFarmPartner();
        $staff = $this->makeStaff($farm);
        $buyer = $this->makeCustomer();
        $order = $this->makeOrderForFarm($farm, $buyer, ['status' => 'pending']);

        $this->assignmentFor($order->id, $farm->id)->update([
            'assigned_customer_id' => $staff->id,
            'status'               => 'packed',
            'packed_at'            => now(),
        ]);

        // Chỉ owner được xác nhận đơn / bàn giao ship.
        $this->withHeaders($this->authHeader($staff))
            ->postJson("/api/farm/orders/{$order->id}/confirm-order")
            ->assertStatus(403);
        $this->withHeaders($this->authHeader($staff))
            ->postJson("/api/farm/orders/{$order->id}/handoff-ship")
            ->assertStatus(403);
    }

    public function test_owner_confirm_order_moves_pending_to_confirmed(): void
    {
        [$owner, $farm] = $this->makeFarmPartner();
        $buyer = $this->makeCustomer();
        $order = $this->makeOrderForFarm($farm, $buyer, ['status' => 'pending']);
        $this->assignmentFor($order->id, $farm->id);

        $response = $this->withHeaders($this->authHeader($owner))
            ->postJson("/api/farm/orders/{$order->id}/confirm-order");

        $response->assertOk()
            ->assertJsonPath('data.order_status', 'confirmed');

        $order->refresh();
        $this->assertEquals('confirmed', $order->status);
        $this->assertDatabaseHas('order_packing_logs', [
            'order_id' => $order->id,
            'action'   => 'order_confirmed',
        ]);
    }

    public function test_order_stays_in_preparing_until_all_farms_packed(): void
    {
        [$owner, $farm1] = $this->makeFarmPartner();
        $staff1 = $this->makeStaff($farm1);
        [, $farm2]       = $this->makeFarmPartner();
        $buyer = $this->makeCustomer();

        // Đơn chứa item của 2 farm.
        $product2 = $this->makeProduct();
        $order = $this->makeOrderForFarm($farm1, $buyer, ['status' => 'preparing']);
        ZaloOrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $product2->id,
            'farm_id'    => $farm2->id,
            'name'       => $product2->name,
            'price'      => '20000',
            'quantity'   => 3,
        ]);

        $this->assignmentFor($order->id, $farm1->id)->update([
            'assigned_customer_id' => $staff1->id,
            'status'               => 'assigned',
        ]);
        // farm2 chưa đóng gói.
        $this->assignmentFor($order->id, $farm2->id);

        $this->withHeaders($this->authHeader($staff1))
            ->postJson("/api/farm/orders/{$order->id}/confirm-packed")
            ->assertOk();

        // Còn farm2 chưa packed → đơn vẫn 'preparing'.
        $order->refresh();
        $this->assertEquals('preparing', $order->status);
    }

    public function test_start_packing_advances_order_to_preparing(): void
    {
        [$owner, $farm] = $this->makeFarmPartner();
        $staff = $this->makeStaff($farm);
        $buyer = $this->makeCustomer();
        $order = $this->makeOrderForFarm($farm, $buyer, ['status' => 'confirmed']);

        $this->assignmentFor($order->id, $farm->id)->update([
            'assigned_customer_id' => $staff->id,
            'status'               => 'assigned',
        ]);

        $this->withHeaders($this->authHeader($staff))
            ->postJson("/api/farm/orders/{$order->id}/start-packing")
            ->assertOk()
            ->assertJsonPath('data.assignment_status', 'packing');

        $order->refresh();
        $this->assertEquals('preparing', $order->status);
    }

    // ─── D. Ẩn thông tin khách hàng ───────────────────────────────────────────

    public function test_incoming_orders_mask_phone_and_address(): void
    {
        [$owner, $farm] = $this->makeFarmPartner();
        $buyer = $this->makeCustomer();
        $order = $this->makeOrderForFarm($farm, $buyer);

        $response = $this->withHeaders($this->authHeader($owner))
            ->getJson('/api/farm/orders/incoming');

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('order_id', $order->id);
        $this->assertNotNull($row);

        // KHÔNG có SĐT đầy đủ.
        $this->assertNotEquals('0937456739', $row['customer_phone']);
        $this->assertEquals('0937***739', $row['customer_phone']);

        // KHÔNG có số nhà / tên đường — chỉ 2 đoạn cuối.
        $this->assertStringNotContainsString('Lê Lợi', $row['delivery_address']);
        $this->assertStringNotContainsString('12 ', $row['delivery_address']);
        $this->assertEquals('Q.1, TP.HCM', $row['delivery_address']);
    }

    // ─── E. ContactMasker (unit) ──────────────────────────────────────────────

    public function test_contact_masker_phone(): void
    {
        $this->assertEquals('0937***739', ContactMasker::maskPhone('0937456739'));
        $this->assertEquals('0937***739', ContactMasker::maskPhone('093 745 6739'));
        $this->assertNull(ContactMasker::maskPhone(null));
        $this->assertNull(ContactMasker::maskPhone(''));
    }

    public function test_contact_masker_address(): void
    {
        $this->assertEquals(
            'Q.1, TP.HCM',
            ContactMasker::maskAddress('12 Lê Lợi, P. Bến Nghé, Q.1, TP.HCM')
        );
        // ≤ 2 đoạn → giữ nguyên.
        $this->assertEquals('Q.1, TP.HCM', ContactMasker::maskAddress('Q.1, TP.HCM'));
        $this->assertNull(ContactMasker::maskAddress(null));
    }
}
