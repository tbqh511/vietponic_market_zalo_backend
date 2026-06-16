<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * StockMovement — ledger ghi nhận từng lần nhập/xuất kho của 1 SKU.
 *
 * quantity ký dấu: type='in' lưu +abs, type='out' lưu -abs. Mọi luồng thay đổi
 * tồn (order, huỷ đơn, admin nhập/điều chỉnh, farm nhập/xuất, đóng lô) ghi 1 dòng
 * tại đây qua StockService::recordMovement / recordBatchIn.
 */
class StockMovement extends Model
{
    protected $table = 'stock_movements';

    protected $fillable = [
        'product_id',
        'batch_id',
        'order_id',
        'farm_id',
        'type',
        'source',
        'quantity',
        'note',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'float',
    ];

    /** Nhãn nguồn (tiếng Việt) — dùng chung cho view + search. */
    public const SOURCE_LABELS = [
        'order'        => 'Đơn hàng',
        'order_cancel' => 'Huỷ đơn (hoàn kho)',
        'admin_import' => 'Nhập kho (admin)',
        'admin_adjust' => 'Điều chỉnh (admin)',
        'farm_import'  => 'Nhập từ farm',
        'farm_export'  => 'Xuất từ farm',
        'batch_close'  => 'Đóng/thu hồi lô',
    ];

    public static function sourceLabel(?string $source): string
    {
        return self::SOURCE_LABELS[$source] ?? ($source ?? '—');
    }

    public function product()
    {
        return $this->belongsTo(ZaloProduct::class, 'product_id');
    }

    public function batch()
    {
        return $this->belongsTo(FarmStockBatch::class, 'batch_id');
    }

    public function order()
    {
        return $this->belongsTo(ZaloOrder::class, 'order_id');
    }
}
