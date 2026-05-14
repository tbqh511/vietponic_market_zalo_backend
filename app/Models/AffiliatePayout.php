<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AffiliatePayout extends Model
{
    use HasFactory;

    protected $table = 'affiliate_payouts';

    protected $fillable = [
        'referrer_customer_id',
        'amount',
        'status',
        'method',
        'reference',
        'requested_at',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'amount' => 'integer',
        'requested_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function referrer()
    {
        return $this->belongsTo(Customer::class, 'referrer_customer_id');
    }

    public function scopeRequested($q)
    {
        return $q->where('status', 'requested');
    }

    public function scopePaid($q)
    {
        return $q->where('status', 'paid');
    }
}
