<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    public $timestamps = false; // using explicit timestamp fields in migration; change if using timestamps()

    protected $fillable = [
        'status','payment_status','received_at','total','note','delivery_id','created_by_user_id'
    ];

    protected $casts = [
        'received_at' => 'datetime',
    ];

    public function delivery()
    {
        return $this->hasOne(Delivery::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
}
