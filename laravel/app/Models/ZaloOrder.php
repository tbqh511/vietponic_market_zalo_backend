<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZaloOrder extends Model
{
    use HasFactory;

    protected $table = 'zalo_orders';
    public $timestamps = false;
    protected $fillable = ['status','payment_status','created_at','received_at','total','note','customer_id'];
    // Enable auto increment for ID
    public $incrementing = true;
    protected $keyType = 'int';

    public function items()
    {
        return $this->hasMany(ZaloOrderItem::class, 'order_id');
    }

    public function delivery()
    {
        return $this->hasOne(ZaloDelivery::class, 'order_id');
    }
}
