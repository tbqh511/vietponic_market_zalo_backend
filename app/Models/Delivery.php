<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id','type','alias','address','name','phone','station_id','station_image','location_lat','location_lng'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function station()
    {
        return $this->belongsTo(Station::class);
    }
}
