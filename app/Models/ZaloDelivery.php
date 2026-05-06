<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZaloDelivery extends Model
{
    use HasFactory;

    protected $table = 'zalo_deliveries';
    public $timestamps = false;
    protected $fillable = ['order_id','type','alias','address','name','phone','station_id','station_name','station_image','lat','lng'];

    public function order()
    {
        return $this->belongsTo(ZaloOrder::class, 'order_id');
    }
}
