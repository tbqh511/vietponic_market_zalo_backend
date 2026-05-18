<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZaloOrderItem extends Model
{
    use HasFactory;

    protected $table = 'zalo_order_items';
    public $timestamps = false;
    protected $fillable = [
        'order_id','product_id','name','price','quantity','image','detail',
        'unit_label','system_unit','conversion_factor','system_total',
        // Farm Partner Hub — phân bổ batch FEFO:
        // Một order_item có thể được split thành nhiều row (cùng product_id,
        // khác farm_stock_batch_id) nếu cần lấy từ nhiều batch.
        // cost_price_snapshot = batch.cost_price tại thời điểm tạo order,
        // dùng để báo cáo doanh thu farm không bị ảnh hưởng nếu sau này
        // farm điều chỉnh giá vốn.
        'farm_stock_batch_id', 'farm_id', 'cost_price_snapshot',
    ];

    protected $casts = [
        'conversion_factor'   => 'decimal:3',
        'system_total'        => 'decimal:3',
        'cost_price_snapshot' => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(ZaloOrder::class, 'order_id');
    }

    public function product()
    {
        return $this->belongsTo(ZaloProduct::class, 'product_id');
    }

    /**
     * Farm được phân bổ phục vụ row này. Nullable cho đơn cũ trước Hub.
     */
    public function farm()
    {
        return $this->belongsTo(Farm::class, 'farm_id');
    }

    /**
     * Batch được trừ. Nullable cho đơn cũ + trường hợp batch đã bị xoá
     * (FK onDelete set null).
     */
    public function batch()
    {
        return $this->belongsTo(FarmStockBatch::class, 'farm_stock_batch_id');
    }
}
