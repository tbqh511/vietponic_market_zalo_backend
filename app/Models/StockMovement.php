<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $table = 'stock_movements';
    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'order_id',
        'movement_type',
        'quantity_change',
        'quantity_before',
        'quantity_after',
        'note',
        'created_by',
        'farm_customer_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(ZaloProduct::class, 'product_id');
    }

    public function order()
    {
        return $this->belongsTo(ZaloOrder::class, 'order_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeByProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('movement_type', $type);
    }

    public function scopeByDateRange($query, ?string $from, ?string $to)
    {
        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        if ($to) {
            $query->where('created_at', '<=', $to . ' 23:59:59');
        }
        return $query;
    }

    public static function movementTypeLabel(string $type): string
    {
        return match ($type) {
            'import'     => 'Nhập kho',
            'export'     => 'Xuất kho',
            'adjustment' => 'Điều chỉnh',
            'return'     => 'Hoàn trả',
            'damage'     => 'Hỏng/Mất',
            'reserved'   => 'Đặt giữ',
            'unreserved' => 'Giải phóng giữ',
            default      => $type,
        };
    }
}
