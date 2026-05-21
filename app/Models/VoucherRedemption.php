<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VoucherRedemption extends Model
{
    use HasFactory;

    protected $table = 'voucher_redemptions';

    protected $fillable = [
        'voucher_id',
        'order_id',
        'customer_id',
        'discount_amount',
        'redeemed_at',
    ];

    protected $casts = [
        'redeemed_at' => 'datetime',
    ];

    public function voucher()
    {
        return $this->belongsTo(Voucher::class, 'voucher_id');
    }

    public function order()
    {
        return $this->belongsTo(ZaloOrder::class, 'order_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
