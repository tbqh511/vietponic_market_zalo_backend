<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Banner extends Model
{
    use HasFactory;

    protected $table = 'banners';
    protected $fillable = [
        'image',
        'intrinsic_width',
        'intrinsic_height',
        'intrinsic_aspect',
    ];

    protected static function booted(): void
    {
        $forget = fn () => Cache::forget('banners.list.v1');
        static::saved($forget);
        static::deleted($forget);
    }
}
