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
        'subtotal','shipping_fee','shipping_service_code','shipping_service_name','exchange_weight',
        // Farm Partner Hub — mốc giao xong, set khi status chuyển sang 'delivered'.
        // FarmDashboardService query cutoff theo cột này (KHÔNG dùng created_at).
        'delivered_at',
        // Voucher snapshot — bảo toàn lịch sử khi voucher bị sửa/xoá.
        'voucher_id','voucher_code','discount_amount',
    ];
    protected $casts = [
        'created_at'   => 'datetime',
        'received_at'  => 'datetime',
        'delivered_at' => 'datetime',
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

    public function trackingEvents()
    {
        return $this->hasMany(VtpTrackingEvent::class, 'order_id')->orderBy('status_at', 'desc');
    }

    public function voucher()
    {
        return $this->belongsTo(Voucher::class, 'voucher_id');
    }
}
