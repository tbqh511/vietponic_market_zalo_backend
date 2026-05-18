<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FarmStockBatch — lô hàng farm nhập vào kho Vietponics.
 *
 * Đây là "đơn vị tồn kho" mới (thay cho cột stock trên zalo_products):
 *   - quantity_in: số lượng farm nhập
 *   - quantity_sold: cộng dồn khi order phân bổ vào batch này
 *   - quantity_remaining: GENERATED STORED COLUMN — DB tự tính = in - sold,
 *     KHÔNG được nằm trong $fillable, app chỉ đọc.
 *
 * FEFO (First-Expired-First-Out): scopeFefo() sắp xếp expire_date tăng dần
 * (NULL xuống cuối) để allocation lấy batch sắp hết hạn trước.
 *
 * status='active' chỉ batch còn hiệu lực. depleted/expired/recalled không
 * tham gia allocation và không hiện trong báo cáo tồn kho.
 */
class FarmStockBatch extends Model
{
    use HasFactory;

    protected $table = 'farm_stock_batches';

    protected $fillable = [
        'farm_id',
        'product_id',
        'batch_date',
        'quantity_in',
        'quantity_sold',
        'cost_price',
        'expire_date',
        'status',
        'note',
        // KHÔNG thêm 'quantity_remaining' — đây là generated stored column.
    ];

    protected $casts = [
        'batch_date'         => 'date',
        'expire_date'        => 'date',
        'quantity_in'        => 'decimal:2',
        'quantity_sold'      => 'decimal:2',
        'quantity_remaining' => 'decimal:2',
        'cost_price'         => 'decimal:2',
    ];

    public function farm()
    {
        return $this->belongsTo(Farm::class);
    }

    public function product()
    {
        return $this->belongsTo(ZaloProduct::class, 'product_id');
    }

    /**
     * Order items đã trừ vào batch này — dùng để truy nguyên gốc batch.
     */
    public function orderItems()
    {
        return $this->hasMany(ZaloOrderItem::class, 'farm_stock_batch_id');
    }

    /**
     * Batch còn hàng & còn hiệu lực để bán.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('quantity_remaining', '>', 0);
    }

    /**
     * FEFO ordering: hết hạn sớm nhất trước, batch không có hạn xuống cuối,
     * tie-break bằng batch_date (lô cũ trước) rồi id để deterministic.
     *
     * Dùng MySQL/MariaDB syntax: ISNULL(expire_date) = 0 trước (= có ngày),
     * sau đó expire_date ASC.
     */
    public function scopeFefo($query)
    {
        return $query
            ->orderByRaw('expire_date IS NULL ASC')
            ->orderBy('expire_date', 'asc')
            ->orderBy('batch_date', 'asc')
            ->orderBy('id', 'asc');
    }
}
