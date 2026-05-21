<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZaloDelivery extends Model
{
    use HasFactory;

    protected $table = 'zalo_deliveries';
    public $timestamps = false;
    protected $fillable = [
        'order_id','type','alias','address','name','phone',
        'station_id','station_name','station_image','lat','lng',
        'province_id','district_id','ward_id',
        'province_name','district_name','ward_name',
        'vtp_order_number','vtp_order_reference',
        'vtp_status_code','vtp_status_name','vtp_location','vtp_status_at',
        'vtp_is_returning','vtp_create_response',
        'vtp_cancel_requested_at','vtp_cancel_attempts',
        'vtp_cancel_failed_at','vtp_cancel_last_error',
    ];

    protected $casts = [
        'vtp_status_at'           => 'datetime',
        'vtp_is_returning'        => 'boolean',
        'vtp_create_response'     => 'array',
        'vtp_cancel_requested_at' => 'datetime',
        'vtp_cancel_failed_at'    => 'datetime',
        'vtp_cancel_attempts'     => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(ZaloOrder::class, 'order_id');
    }
}
