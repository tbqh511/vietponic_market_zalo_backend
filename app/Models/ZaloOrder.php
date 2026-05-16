<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZaloOrder extends Model
{
    use HasFactory;

    protected $table = 'zalo_orders';
    public $timestamps = false;
    protected $fillable = [
        'status','payment_status','created_at','received_at','total','note',
        'customer_id','payment_method','checkout_sdk_order_id',
        'cancelled_at','cancelled_by','cancellation_reason',
        'refund_status','refund_amount','refund_method',
        'refund_transaction_id','refund_provider_id','refunded_at','refund_note',
    ];
    protected $casts = [
        'created_at'   => 'datetime',
        'received_at'  => 'datetime',
        'cancelled_at' => 'datetime',
        'refunded_at'  => 'datetime',
        'refund_amount' => 'decimal:2',
    ];
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
