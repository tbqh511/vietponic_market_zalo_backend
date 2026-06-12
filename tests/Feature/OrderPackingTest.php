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

    /**
     * Tạo farm + owner. $isPackingHub mặc định true vì hầu hết test đóng gói
     * cần farm là Package Hub (chỉ hub mới thao tác đơn). Test farm thường
     * truyền false.
     */
    private function makeFarmPartner(bool $isPackingHub = true): array
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
            'is_packing_hub'    => $isPackingHub,
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
     * $deliveryAttrs ghi đè field delivery (vd type='pickup' + station_name cho
     * test đơn nhận-tại-trạm).
     */
    private function makeOrderForFarm(
        Farm $farm,
        Customer $buyer,
        array $orderAttrs = [],
        array $deliveryAttrs = []
    ): ZaloOrder
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

        ZaloDelivery::create(array_merge([
            'order_id' => $order->id,
            'type'     => 'shipping',
            'name'     => 'Nguyễn Văn A',
            'phone'    => '0937456739',
            'address'  => '12 Lê Lợi, P. Bến Nghé, Q.1, TP.HCM',
        ], $deliveryAttrs));

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

    public function test_hub_owner_packs_entire_multi_farm_order(): void
    {
        // Mô hình Package Hub: đơn nhiều farm chỉ có 1 phiếu thuộc HUB. Hub đóng
        // gói TOÀN BỘ đơn (cả phần hàng farm khác) trong 1 phiếu, rồi bàn giao.
        [$hubOwner, $hub] = $this->makeFarmPartner(true);
        $hubStaff = $this->makeStaff($hub);
        [, $otherFarm]    = $this->makeFarmPartner(false);
        $buyer = $this->makeCustomer();

        // Đơn chứa item của hub + 1 farm khác.
        $product2 = $this->makeProduct();
        $order = $this->makeOrderForFarm($hub, $buyer, ['status' => 'preparing']);
        ZaloOrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $product2->id,
            'farm_id'    => $otherFarm->id,
            'name'       => $product2->name,
            'price'      => '20000',
            'quantity'   => 3,
        ]);

        // 1 phiếu hub duy nhất, gán cho nhân viên hub.
        $this->assignmentFor($order->id, $hub->id)->update([
            'assigned_customer_id' => $hubStaff->id,
            'status'               => 'assigned',
        ]);

        // Nhân viên hub đóng gói xong cả đơn.
        $this->withHeaders($this->authHeader($hubStaff))
            ->postJson("/api/farm/orders/{$order->id}/confirm-packed")
            ->assertOk()
            ->assertJsonPath('data.assignment_status', 'packed');

        // Reset token JWTAuth facade giữ giữa 2 request (test harness — đổi
        // người gọi từ staff sang owner). Production không cần (mỗi request 1 process).
        JWTAuth::unsetToken();

        // Chủ hub bàn giao ship → đơn sang delivering (chỉ 1 phiếu, đã packed).
        $this->withHeaders($this->authHeader($hubOwner))
            ->postJson("/api/farm/orders/{$order->id}/handoff-ship")
            ->assertOk()
            ->assertJsonPath('data.order_status', 'delivering');

        $order->refresh();
        $this->assertEquals('delivering', $order->status);

        // Chỉ đúng 1 phiếu cho đơn (thuộc hub) — không có phiếu per-farm.
        $this->assertDatabaseCount('order_farm_assignments', 1);
        $this->assertDatabaseHas('order_farm_assignments', [
            'order_id' => $order->id,
            'farm_id'  => $hub->id,
        ]);
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

    // ─── F. Package Hub: phân quyền hub vs farm thường ────────────────────────

    public function test_non_hub_farm_cannot_perform_packing_actions(): void
    {
        // Farm thường (không phải hub) → mọi action thao tác trả 403, kể cả owner.
        [$owner, $farm] = $this->makeFarmPartner(false);
        $buyer = $this->makeCustomer();
        $order = $this->makeOrderForFarm($farm, $buyer, ['status' => 'pending']);

        $this->withHeaders($this->authHeader($owner))
            ->postJson("/api/farm/orders/{$order->id}/confirm-order")
            ->assertStatus(403);
        $this->withHeaders($this->authHeader($owner))
            ->postJson("/api/farm/orders/{$order->id}/claim")
            ->assertStatus(403);
        $this->withHeaders($this->authHeader($owner))
            ->postJson("/api/farm/orders/{$order->id}/start-packing")
            ->assertStatus(403);
        $this->withHeaders($this->authHeader($owner))
            ->postJson("/api/farm/orders/{$order->id}/handoff-ship")
            ->assertStatus(403);
    }

    public function test_non_hub_farm_sees_orders_read_only(): void
    {
        // Farm thường thấy đơn CÓ HÀNG của mình, kèm read_only=true.
        [$owner, $farm] = $this->makeFarmPartner(false);
        $buyer = $this->makeCustomer();
        $order = $this->makeOrderForFarm($farm, $buyer);

        $response = $this->withHeaders($this->authHeader($owner))
            ->getJson('/api/farm/orders/incoming');

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('order_id', $order->id);
        $this->assertNotNull($row);
        $this->assertTrue($row['read_only']);
        $this->assertFalse($row['is_mine']);
    }

    public function test_hub_sees_all_orders_even_without_own_items(): void
    {
        // Hub đóng gói TOÀN BỘ đơn → thấy cả đơn KHÔNG chứa hàng của hub.
        [$hubOwner, $hub] = $this->makeFarmPartner(true);
        [, $otherFarm]    = $this->makeFarmPartner(false);
        $buyer = $this->makeCustomer();

        // Đơn chỉ chứa hàng của farm khác (không có item nào của hub).
        $order = $this->makeOrderForFarm($otherFarm, $buyer);

        $response = $this->withHeaders($this->authHeader($hubOwner))
            ->getJson('/api/farm/orders/incoming');

        $response->assertOk();
        $orderIds = collect($response->json('data'))->pluck('order_id')->unique()->all();
        $this->assertContains($order->id, $orderIds);
    }

    public function test_staff_picker_lists_hub_members_only(): void
    {
        // GET /farm/staff trả thành viên của HUB (người đóng gói), không phải
        // farm đang đăng nhập.
        [$hubOwner, $hub] = $this->makeFarmPartner(true);
        $hubStaff = $this->makeStaff($hub);

        $response = $this->withHeaders($this->authHeader($hubOwner))
            ->getJson('/api/farm/staff');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($hubOwner->id, $ids);
        $this->assertContains($hubStaff->id, $ids);
    }

    // ─── G. Chi tiết đơn đóng gói (show) — PACK-07 ────────────────────────────

    public function test_show_returns_station_name_for_pickup_order(): void
    {
        // Đơn nhận-tại-trạm: detail hiện TÊN TRẠM thay địa chỉ giao.
        [$owner, $farm] = $this->makeFarmPartner();
        $buyer = $this->makeCustomer();
        $order = $this->makeOrderForFarm($farm, $buyer, [], [
            'type'         => 'pickup',
            'address'      => null,
            'station_name' => 'Trạm Quận 1',
        ]);

        $response = $this->withHeaders($this->authHeader($owner))
            ->getJson("/api/farm/orders/{$order->id}");

        $response->assertOk()
            ->assertJsonPath('data.is_pickup', true)
            ->assertJsonPath('data.station_name', 'Trạm Quận 1')
            // delivery_address vẫn = station_name cho pickup (backward-compat).
            ->assertJsonPath('data.delivery_address', 'Trạm Quận 1');
        $this->assertEquals(90000.0, $response->json('data.order_total'));
    }

    public function test_show_masks_phone_and_address_for_shipping_order(): void
    {
        // Đơn giao tận nơi: SĐT + địa chỉ phải bị che (KHÔNG nới lỏng).
        [$owner, $farm] = $this->makeFarmPartner();
        $buyer = $this->makeCustomer();
        $order = $this->makeOrderForFarm($farm, $buyer);

        $response = $this->withHeaders($this->authHeader($owner))
            ->getJson("/api/farm/orders/{$order->id}");

        $response->assertOk()
            ->assertJsonPath('data.is_pickup', false)
            ->assertJsonPath('data.customer_phone', '0937***739')
            ->assertJsonPath('data.delivery_address', 'Q.1, TP.HCM');

        $data = $response->json('data');
        $this->assertNotEquals('0937456739', $data['customer_phone']);
        $this->assertStringNotContainsString('Lê Lợi', $data['delivery_address']);
        $this->assertStringNotContainsString('12 ', $data['delivery_address']);
    }

    public function test_show_includes_packer_name_and_timestamps(): void
    {
        // Phiếu đã gán + đang đóng → detail trả tên người đóng + mốc thời gian.
        // Seed phiếu trực tiếp (1 HTTP request/test) — tránh JWT singleton cache
        // khi đổi actor giữa nhiều request trong cùng test (xem claim test).
        [$owner, $farm] = $this->makeFarmPartner();
        $staff = $this->makeStaff($farm);
        $buyer = $this->makeCustomer();
        $order = $this->makeOrderForFarm($farm, $buyer);

        $this->assignmentFor($order->id, $farm->id)->update([
            'assigned_customer_id' => $staff->id,
            'status'               => 'packing',
            'packing_started_at'   => now(),
        ]);

        // Owner xem mọi phiếu → thấy tên người đóng + mốc thời gian.
        $response = $this->withHeaders($this->authHeader($owner))
            ->getJson("/api/farm/orders/{$order->id}");

        $response->assertOk()
            ->assertJsonPath('data.assignment_status', 'packing')
            ->assertJsonPath('data.assigned_customer_id', $staff->id)
            ->assertJsonPath('data.assigned_customer_name', $staff->name);
        $this->assertNotNull($response->json('data.packing_started_at'));
    }

    public function test_staff_cannot_view_others_assignment_detail(): void
    {
        // Staff chỉ xem được phiếu gán cho mình; phiếu người khác → 403.
        [$owner, $farm] = $this->makeFarmPartner();
        $staffA = $this->makeStaff($farm);
        $staffB = $this->makeStaff($farm);
        $buyer  = $this->makeCustomer();
        $order  = $this->makeOrderForFarm($farm, $buyer);

        // Phiếu gán cho staffA (seed trực tiếp — 1 HTTP request/test).
        $this->assignmentFor($order->id, $farm->id)->update([
            'assigned_customer_id' => $staffA->id,
            'status'               => 'assigned',
        ]);

        $this->withHeaders($this->authHeader($staffB))
            ->getJson("/api/farm/orders/{$order->id}")
            ->assertStatus(403);
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
