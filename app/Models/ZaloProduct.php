<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZaloProduct extends Model
{
    use HasFactory;

    protected $table = 'zalo_products';
    public $timestamps = false;
    protected $fillable = [
        'id', 'category_id', 'name', 'price', 'original_price', 'image', 'detail',
        'stock', 'stock_reserved', 'reorder_point',
        'unit_id', 'system_unit', 'conversion_factor',
    ];

    protected $casts = [
        'conversion_factor' => 'decimal:3',
    ];

    // mock products use explicit ids
    public $incrementing = false;
    protected $keyType = 'int';

    public function category()
    {
        return $this->belongsTo(ZaloCategory::class, 'category_id');
    }

    public function unit()
    {
        return $this->belongsTo(ZaloUnit::class, 'unit_id');
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class, 'product_id');
    }

    public function getImageUrlAttribute()
    {
        $image = $this->attributes['image'] ?? null;
        if (!$image) {
            return config('app.url') . '/images/no-image.png';
        }
        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }
        return rtrim(config('app.url'), '/') . '/' . ltrim($image, '/');
    }

    public function getStockAvailableAttribute(): int
    {
        return max(0, ($this->stock ?? 0) - ($this->stock_reserved ?? 0));
    }

    public function scopeLowStock($query)
    {
        return $query->whereRaw('stock <= reorder_point');
    }

    public function isAvailable(int $qty): bool
    {
        return $this->stock_available >= $qty;
    }

    public function reserveStock(int $qty): void
    {
        $this->increment('stock_reserved', $qty);
    }

    public function releaseStock(int $qty): void
    {
        $this->decrement('stock_reserved', max(0, $qty));
    }

    public function deductStock(int $qty): void
    {
        $this->decrement('stock', $qty);
        $this->decrement('stock_reserved', $qty);
    }
}
