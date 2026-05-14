<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AffiliateCommission extends Model
{
    use HasFactory;

    protected $table = 'affiliate_commissions';

    protected $fillable = [
        'order_id',
        'referrer_customer_id',
        'referred_customer_id',
        'order_total',
        'commission_rate',
        'commission_amount',
        'status',
        'notes',
        'paid_at',
    ];

    protected $casts = [
        'order_total' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'commission_amount' => 'integer',
        'paid_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(ZaloOrder::class, 'order_id');
    }

    public function referrer()
    {
        return $this->belongsTo(Customer::class, 'referrer_customer_id');
    }

    public function referred()
    {
        return $this->belongsTo(Customer::class, 'referred_customer_id');
    }

    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }

    public function scopeConfirmed($q)
    {
        return $q->where('status', 'confirmed');
    }

    public function scopePaid($q)
    {
        return $q->where('status', 'paid');
    }

    public function scopeCancelled($q)
    {
        return $q->where('status', 'cancelled');
    }
}
