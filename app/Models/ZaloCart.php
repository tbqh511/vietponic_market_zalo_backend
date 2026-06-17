<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZaloCart extends Model
{
    use HasFactory;

    protected $table = 'zalo_carts';

    protected $fillable = [
        'customer_id',
        'cart_data',
        'expires_at',
    ];

    protected $casts = [
        'cart_data'  => 'array',
        'expires_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return Carbon::now()->gte($this->expires_at);
    }
}
