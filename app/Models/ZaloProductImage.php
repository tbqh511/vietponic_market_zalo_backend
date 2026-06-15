<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZaloProductImage extends Model
{
    protected $table = 'zalo_product_images';

    protected $fillable = ['product_id', 'image_path', 'sort_order'];

    public function product()
    {
        return $this->belongsTo(ZaloProduct::class, 'product_id');
    }

    public function getImageUrlAttribute(): string
    {
        $path = $this->image_path;
        if (!$path) {
            return config('app.url') . '/images/no-image.png';
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        return rtrim(config('app.url'), '/') . '/' . ltrim($path, '/');
    }
}
