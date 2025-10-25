<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZaloOrderItem extends Model
{
    use HasFactory;

    protected $table = 'zalo_order_items';
    public $timestamps = false;
    protected $fillable = ['order_id','product_id','name','price','quantity','image','detail'];

    public function order()
    {
        return $this->belongsTo(ZaloOrder::class, 'order_id');
    }
}
