<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZaloProduct extends Model
{
    use HasFactory;

    protected $table = 'zalo_products';
    public $timestamps = false;
    protected $fillable = ['id', 'category_id', 'name', 'price', 'original_price', 'image', 'detail'];

    // mock products use explicit ids
    public $incrementing = false;
    protected $keyType = 'int';

    public function category()
    {
        return $this->belongsTo(ZaloCategory::class, 'category_id');
    }

    /**
     * Get the full URL for the product image
     */
    public function getImageUrlAttribute()
    {
        $image = $this->attributes['image'] ?? null;
        if (!$image) {
            return config('app.url') . '/images/no-image.png';
        }
        // Already a full URL — return as-is
        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }
        return rtrim(config('app.url'), '/') . '/' . ltrim($image, '/');
    }
}
