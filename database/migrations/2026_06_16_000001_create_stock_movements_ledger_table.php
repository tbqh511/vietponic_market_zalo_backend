<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng stock_movements — ledger ghi nhận TỪNG lần nhập/xuất kho của mỗi SKU.
 *
 * Khác với farm_stock_batches (lưu trạng thái tồn theo lô), bảng này lưu lịch sử
 * chuyển động: mỗi dòng là 1 sự kiện nhập (+) hoặc xuất (-) với nguồn rõ ràng
 * (đơn hàng, huỷ đơn, admin nhập/điều chỉnh, farm nhập/xuất, đóng lô).
 *
 * quantity ký dấu: nhập = +abs, xuất = -abs → SUM(quantity) per product khớp tồn.
 *
 * up() còn backfill từ dữ liệu cũ:
 *   - farm_stock_batches  → dòng nhập (in)
 *   - zalo_order_items    → dòng xuất theo đơn (out)
 * Xuất thủ công lịch sử (admin adjust-/farm export) không tái hiện được vì hệ
 * thống cũ chỉ append vào batch.note, không có qty/timestamp riêng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('farm_id')->nullable();

            $table->enum('type', ['in', 'out']);
            $table->enum('source', [
                'order', 'order_cancel',
                'admin_import', 'admin_adjust',
                'farm_import', 'farm_export',
                'batch_close',
            ]);

            $table->decimal('quantity', 10, 2); // signed: + nhập, - xuất

            $table->string('note', 500)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();

            $table->index(['product_id', 'id']);
            $table->index('batch_id');
            $table->index('order_id');
            $table->index(['source', 'id']);

            $table->foreign('product_id')->references('id')->on('zalo_products')->onDelete('cascade');
            $table->foreign('batch_id')->references('id')->on('farm_stock_batches')->nullOnDelete();
            $table->foreign('order_id')->references('id')->on('zalo_orders')->nullOnDelete();
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }

    /**
     * Backfill idempotent — chỉ chạy khi bảng còn rỗng.
     */
    private function backfill(): void
    {
        if (DB::table('stock_movements')->exists()) {
            return;
        }

        // Set farm_id của packing hub để phân biệt admin_import vs farm_import.
        $hubFarmIds = Schema::hasColumn('farms', 'is_packing_hub')
            ? DB::table('farms')->where('is_packing_hub', true)->pluck('id')->all()
            : [];
        $hubSet = array_flip($hubFarmIds);

        // ── Imports: mỗi batch = 1 dòng nhập ─────────────────────────────────
        DB::table('farm_stock_batches')->orderBy('id')->chunk(500, function ($batches) use ($hubSet) {
            $rows = [];
            foreach ($batches as $b) {
                $note = (string) ($b->note ?? '');
                if (str_contains($note, '[adjust]')) {
                    $source = 'admin_adjust';
                } elseif (isset($hubSet[$b->farm_id])) {
                    $source = 'admin_import';
                } else {
                    $source = 'farm_import';
                }

                $ts = $b->created_at ?? ($b->batch_date . ' 00:00:00');
                $rows[] = [
                    'product_id' => $b->product_id,
                    'batch_id'   => $b->id,
                    'order_id'   => null,
                    'farm_id'    => $b->farm_id,
                    'type'       => 'in',
                    'source'     => $source,
                    'quantity'   => (float) $b->quantity_in,
                    'note'       => $note !== '' ? mb_substr($note, 0, 500) : null,
                    'created_by' => null,
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ];
            }
            if ($rows) {
                DB::table('stock_movements')->insert($rows);
            }
        });

        // ── Order exports: mỗi order_item có batch = 1 dòng xuất ──────────────
        DB::table('zalo_order_items')
            ->join('zalo_orders', 'zalo_orders.id', '=', 'zalo_order_items.order_id')
            ->whereNotNull('zalo_order_items.farm_stock_batch_id')
            ->orderBy('zalo_order_items.id')
            ->select([
                'zalo_order_items.id as item_id',
                'zalo_order_items.product_id',
                'zalo_order_items.farm_stock_batch_id',
                'zalo_order_items.farm_id',
                'zalo_order_items.order_id',
                'zalo_order_items.quantity',
                'zalo_orders.created_at as order_created_at',
            ])
            ->chunk(500, function ($items) {
                $rows = [];
                foreach ($items as $it) {
                    $ts = $it->order_created_at ?? now();
                    $rows[] = [
                        'product_id' => $it->product_id,
                        'batch_id'   => $it->farm_stock_batch_id,
                        'order_id'   => $it->order_id,
                        'farm_id'    => $it->farm_id,
                        'type'       => 'out',
                        'source'     => 'order',
                        'quantity'   => -1 * (float) $it->quantity,
                        'note'       => null,
                        'created_by' => null,
                        'created_at' => $ts,
                        'updated_at' => $ts,
                    ];
                }
                if ($rows) {
                    DB::table('stock_movements')->insert($rows);
                }
            });
    }
};
