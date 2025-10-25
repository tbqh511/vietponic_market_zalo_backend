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
}
