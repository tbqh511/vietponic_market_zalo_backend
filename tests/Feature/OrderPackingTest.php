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

    public function test_staff_only_sees_orders_assigned_to_them(): void
    {
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
        $orderIds = collect($response->json('data'))->pluck('order_id')->unique()->all();

        $this->assertContains($orderA->id, $orderIds);
        $this->assertNotContains($orderB->id, $orderIds);
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

    public function test_confirm_packed_moves_order_to_delivering_when_all_farms_done(): void
    {
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

        // Đơn chỉ có 1 farm → tất cả packed → chuyển 'delivering'.
        $order->refresh();
        $this->assertEquals('delivering', $order->status);
        // delivered_at KHÔNG bị set sớm.
        $this->assertNull($order->delivered_at);

        // Có log status_changed preparing → delivering.
        $statusLog = OrderPackingLog::where('order_id', $order->id)
            ->where('action', 'status_changed')
            ->first();
        $this->assertNotNull($statusLog);
        $this->assertEquals('preparing', $statusLog->from_status);
        $this->assertEquals('delivering', $statusLog->to_status);
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
