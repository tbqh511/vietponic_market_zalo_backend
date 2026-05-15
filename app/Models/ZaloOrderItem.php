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
    ];

    protected $casts = [
        'conversion_factor' => 'decimal:3',
        'system_total'      => 'decimal:3',
    ];

    public function order()
    {
        return $this->belongsTo(ZaloOrder::class, 'order_id');
    }
}
