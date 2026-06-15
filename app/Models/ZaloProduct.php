<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * ZaloProduct — danh mục SKU. Tồn kho KHÔNG còn ở đây (Farm Partner Hub):
 * tồn = SUM(farm_stock_batches.quantity_remaining) WHERE status='active'.
 *
 * Cột stock/stock_reserved/reorder_point đã bị drop ở migration 2026_05_18_000001.
 * StockService cũ phải refactor đọc qua farm_stock_batches.
 *
 * id là unsignedBigInteger (không auto-increment) — mock product dùng id cố định.
 */
class ZaloProduct extends Model
{
    use HasFactory;

    protected $table = 'zalo_products';
    public $timestamps = false;
    protected $fillable = [
        'id', 'category_id', 'name', 'price', 'original_price', 'image', 'detail',
        'unit_id', 'system_unit', 'conversion_factor',
        'weight', 'length', 'width', 'height',
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

    public function productImages()
    {
        return $this->hasMany(ZaloProductImage::class, 'product_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function getImagesUrlsAttribute(): array
    {
        return $this->productImages->map(fn($img) => $img->image_url)->values()->all();
    }

    public function unit()
    {
        return $this->belongsTo(ZaloUnit::class, 'unit_id');
    }

    /**
     * Các farm cung cấp SKU này (many-to-many qua pivot farm_product).
     */
    public function farms()
    {
        return $this->belongsToMany(Farm::class, 'farm_product', 'product_id', 'farm_id')
            ->withPivot('cost_price', 'is_primary')
            ->withTimestamps();
    }

    /**
     * Tất cả batch nhập (mọi farm, mọi status) — dùng cho lịch sử/admin.
     */
    public function stockBatches()
    {
        return $this->hasMany(FarmStockBatch::class, 'product_id');
    }

    /**
     * Batch còn bán được (active + còn remaining). Sắp xếp FEFO để dùng
     * trực tiếp trong allocation đơn hàng.
     */
    public function activeBatches()
    {
        return $this->hasMany(FarmStockBatch::class, 'product_id')
            ->where('status', 'active')
            ->where('quantity_remaining', '>', 0)
            ->orderByRaw('expire_date IS NULL ASC')
            ->orderBy('expire_date', 'asc')
            ->orderBy('batch_date', 'asc')
            ->orderBy('id', 'asc');
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

    /**
     * Tồn kho khả dụng = SUM quantity_remaining trên các batch active.
     * Trả về decimal (string) để giữ chính xác. Caller cần parse khi so sánh.
     *
     * Lưu ý hiệu năng: nếu query nhiều product cùng lúc, dùng eager-load
     * activeBatches() rồi sum trong PHP để tránh N+1.
     */
    public function getStockAvailableAttribute()
    {
        return (float) $this->stockBatches()
            ->where('status', 'active')
            ->where('quantity_remaining', '>', 0)
            ->sum('quantity_remaining');
    }

    public function isAvailable(float $qty): bool
    {
        return $this->stock_available >= $qty;
    }
}
